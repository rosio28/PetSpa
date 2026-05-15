<?php
// src/Controllers/FichasGroomingController.php

class FichasGroomingController {

    // GET /fichas/{id}
    public static function ver(int $id): void {
        Auth::require(['admin','recepcion','groomer']);
        $db    = DB::get();
        $ficha = self::getFicha($db, $id);
        if (!$ficha) Response::error('Ficha no encontrada', 404);

        $chkStmt = $db->prepare("
            SELECT cf.id, cf.completado, cf.observacion,
                   ci.id AS item_id, ci.nombre, ci.requiere_observacion
            FROM checklist_ficha cf
            JOIN checklist_items_maestro ci ON ci.id = cf.item_id
            WHERE cf.ficha_id = ? ORDER BY ci.id");
        $chkStmt->execute([$id]);
        $items = $chkStmt->fetchAll();

        $fotosStmt = $db->prepare("SELECT id, tipo, url, created_at FROM fotos_grooming WHERE ficha_id = ? ORDER BY tipo, created_at");
        $fotosStmt->execute([$id]);

        $ficha['checklist']         = $items;
        $ficha['fotos']             = $fotosStmt->fetchAll();
        $ficha['items_completados'] = count(array_filter($items, fn($i) => $i['completado']));
        $ficha['items_total']       = count($items);

        Response::ok($ficha);
    }

    // PUT /fichas/{id}
    public static function actualizar(int $id): void {
        Auth::require(['admin','recepcion','groomer']);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $db    = DB::get();
        $ficha = self::getFicha($db, $id);
        if (!$ficha) Response::error('Ficha no encontrada', 404);
        if ($ficha['fecha_cierre']) Response::error('La ficha ya está cerrada');

        $fields = []; $params = [];
        foreach (['raza_momento','tamano_momento','estado_inicial','estado_final','notas_internas'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }
        if (isset($body['temperatura_animal'])) {
            $t = (float)$body['temperatura_animal'];
            if ($t < 35 || $t > 42) Response::error('Temperatura fuera de rango (35–42 °C)');
            $fields[] = "temperatura_animal = ?"; $params[] = $t;
        }
        if ($fields) { $params[] = $id; $db->prepare("UPDATE fichas_grooming SET " . implode(', ', $fields) . " WHERE id=?")->execute($params); }

        if (!empty($body['checklist']) && is_array($body['checklist'])) {
            foreach ($body['checklist'] as $item) {
                $iid = Sanitizer::int($item['item_id'] ?? 0);
                if (!$iid) continue;
                $db->prepare("UPDATE checklist_ficha SET completado=?, observacion=? WHERE ficha_id=? AND item_id=?")
                   ->execute([!empty($item['completado']), Sanitizer::string($item['observacion'] ?? ''), $id, $iid]);
            }
        }
        AuditLog::log("Ficha #$id actualizada", 'fichas_grooming', $id);
        Response::ok(null, 'Ficha actualizada');
    }

    // POST /fichas/{id}/cerrar
    public static function cerrar(int $id): void {
        $user  = Auth::require(['admin','recepcion','groomer']);
        $db    = DB::get();
        $ficha = self::getFicha($db, $id);
        if (!$ficha) Response::error('Ficha no encontrada', 404);
        if ($ficha['fecha_cierre']) Response::error('Esta ficha ya fue cerrada');

        // Regla de negocio: mínimo 5 ítems completados
        $stmtC = $db->prepare("SELECT COUNT(*) FROM checklist_ficha WHERE ficha_id=? AND completado=TRUE");
        $stmtC->execute([$id]);
        $completados = (int)$stmtC->fetchColumn();
        if ($completados < 5) Response::error("Mínimo 5 ítems del checklist deben estar completados ($completados/6).", 422);

        // Regla: fotos antes/después
        $stmtF = $db->prepare("SELECT tipo, COUNT(*) c FROM fotos_grooming WHERE ficha_id=? GROUP BY tipo");
        $stmtF->execute([$id]);
        $fotoMap = [];
        foreach ($stmtF->fetchAll() as $f) $fotoMap[$f['tipo']] = (int)$f['c'];
        if (empty($fotoMap['antes']) || empty($fotoMap['despues'])) {
            Response::error('Se requiere al menos 1 foto "antes" y 1 foto "después".', 422);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $tiempoReal  = isset($body['tiempo_real_min']) ? Sanitizer::int($body['tiempo_real_min']) : null;
        $estadoFinal = Sanitizer::string($body['estado_final']   ?? '');
        $notasFin    = Sanitizer::string($body['notas_internas'] ?? '');

        $upd = ["fecha_cierre=NOW()", "cerrado_por=?", "inventario_descontado=FALSE"];
        $prm = [$user['user_id']];
        if ($estadoFinal) { $upd[] = "estado_final=?";   $prm[] = $estadoFinal; }
        if ($notasFin)    { $upd[] = "notas_internas=?"; $prm[] = $notasFin; }
        $prm[] = $id;
        $db->prepare("UPDATE fichas_grooming SET " . implode(', ', $upd) . " WHERE id=?")->execute($prm);

        self::descontarInsumos($db, $ficha);
        $db->prepare("UPDATE fichas_grooming SET inventario_descontado=TRUE WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE citas SET estado='completada', duracion_real=?, updated_at=NOW() WHERE id=?")
           ->execute([$tiempoReal, $ficha['cita_id']]);

        // Programar encuesta NPS
        $cr = $db->prepare("SELECT cliente_id FROM citas WHERE id=?");
        $cr->execute([$ficha['cita_id']]);
        if ($cita = $cr->fetch()) {
            try {
                $db->prepare("INSERT INTO notificaciones (cita_id,cliente_id,tipo_evento,canal,fecha_programada,estado)
                    VALUES (?,?,'encuesta','email',NOW()+INTERVAL '2 hours','pendiente')")
                   ->execute([$ficha['cita_id'], $cita['cliente_id']]);
            } catch (Throwable $e) { error_log('NPS schedule error: ' . $e->getMessage()); }
        }

        AuditLog::log("Ficha #$id cerrada ($completados ítems)", 'fichas_grooming', $id);
        Response::ok(['items_completados' => $completados, 'fotos_antes' => $fotoMap['antes'] ?? 0, 'fotos_despues' => $fotoMap['despues'] ?? 0], 'Servicio cerrado correctamente');
    }

    // POST /fichas/{id}/fotos
    public static function subirFoto(int $id): void {
        Auth::require(['admin','recepcion','groomer']);

        $tipo = Sanitizer::string($_POST['tipo'] ?? '');
        if (!in_array($tipo, ['antes','despues'])) Response::error("tipo debe ser 'antes' o 'despues'");

        $errCodes = [UPLOAD_ERR_INI_SIZE=>'Excede límite php.ini', UPLOAD_ERR_FORM_SIZE=>'Excede límite formulario',
                     UPLOAD_ERR_PARTIAL=>'Subida parcial', UPLOAD_ERR_NO_FILE=>'Sin archivo', UPLOAD_ERR_NO_TMP_DIR=>'Sin tmp dir',
                     UPLOAD_ERR_CANT_WRITE=>'Error de escritura'];
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $code = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;
            Response::error($errCodes[$code] ?? 'Error al subir archivo');
        }

        $file = $_FILES['foto'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) Response::error('Formato no permitido (jpg/jpeg/png/webp)');
        if ($file['size'] > 8 * 1024 * 1024) Response::error('Tamaño máximo: 8MB');
        if ($file['size'] < 1024) Response::error('Archivo demasiado pequeño o vacío');

        // Verificar MIME real
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'])) Response::error('El archivo no es una imagen válida');

        // Guardar dentro de public/ para que Apache lo sirva directamente
        $uploadDir = __DIR__ . '/../../../public/uploads/fotos/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) Response::error('No se pudo crear directorio de uploads');

        $nombre  = sprintf('ficha%d_%s_%s_%s.%s', $id, $tipo, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
        $destino = $uploadDir . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $destino)) Response::error('Error al guardar imagen. Verifica permisos de public/uploads/');

        $url = (getenv('APP_URL') ?: 'http://localhost:8080') . '/uploads/fotos/' . $nombre;
        DB::get()->prepare("INSERT INTO fotos_grooming (ficha_id, tipo, url) VALUES (?,?,?)")->execute([$id, $tipo, $url]);

        AuditLog::log("Foto $tipo subida a ficha #$id", 'fotos_grooming', $id);
        Response::ok(['url' => $url, 'tipo' => $tipo], 'Foto subida correctamente');
    }

    // ─── Helpers ────────────────────────────────────────────

    private static function getFicha(PDO $db, int $id): array|false {
        // fg.* ya contiene cita_id — NO usar ci.cita_id (campo inexistente en tabla citas)
        $stmt = $db->prepare("
            SELECT fg.*,
                   ci.servicio_id,
                   ci.mascota_id,
                   ci.cliente_id,
                   m.nombre AS mascota_nombre,
                   s.nombre AS servicio_nombre
            FROM fichas_grooming fg
            JOIN citas     ci ON ci.id = fg.cita_id
            JOIN mascotas   m ON m.id  = ci.mascota_id
            JOIN servicios  s ON s.id  = ci.servicio_id
            WHERE fg.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    private static function descontarInsumos(PDO $db, array $ficha): void {
        if (!empty($ficha['inventario_descontado'])) return;
        $srv = $db->prepare("SELECT consumo_insumos FROM servicios WHERE id=?");
        $srv->execute([$ficha['servicio_id']]);
        $servicio = $srv->fetch();
        if (!$servicio || !$servicio['consumo_insumos']) return;

        $insumos = json_decode($servicio['consumo_insumos'], true);
        if (!is_array($insumos)) return;

        foreach ($insumos as $ins) {
            $pid = (int)($ins['producto_id'] ?? 0);
            $qty = (int)($ins['cantidad']    ?? 0);
            if (!$pid || !$qty) continue;
            $db->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id=?")->execute([$qty, $pid]);
            try {
                $db->prepare("INSERT INTO movimiento_inventario (producto_id, tipo, cantidad, origen, fecha)
                    VALUES (?, 'salida', ?, 'grooming', NOW())")->execute([$pid, $qty]);
            } catch (Throwable $e) { /* tabla puede no existir en instancias viejas */ }
            $chk = $db->prepare("SELECT nombre, stock, stock_minimo FROM productos WHERE id=?");
            $chk->execute([$pid]);
            if ($p = $chk->fetch()) {
                if ((int)$p['stock'] <= (int)$p['stock_minimo']) error_log("⚠ STOCK BAJO: {$p['nombre']} ({$p['stock']}/{$p['stock_minimo']})");
            }
        }
    }
}
