<?php
// src/Controllers/FichasGroomingController.php

class FichasGroomingController {

    // GET /fichas/{id}
    public static function ver(int $id): void {
        Auth::require(['admin','recepcion','groomer']);
        $db   = DB::get();
        $ficha = self::getFicha($db, $id);
        if (!$ficha) Response::error('Ficha no encontrada', 404);

        $checklist = $db->prepare("SELECT cf.*, ci.nombre, ci.requiere_observacion 
            FROM checklist_ficha cf JOIN checklist_items_maestro ci ON ci.id = cf.item_id
            WHERE cf.ficha_id = ?");
        $checklist->execute([$id]);
        $ficha['checklist'] = $checklist->fetchAll();

        $fotos = $db->prepare("SELECT * FROM fotos_grooming WHERE ficha_id = ? ORDER BY tipo, created_at");
        $fotos->execute([$id]);
        $ficha['fotos'] = $fotos->fetchAll();

        Response::ok($ficha);
    }

    // PUT /fichas/{id}  — actualizar datos de la ficha
    public static function actualizar(int $id): void {
        Auth::require(['admin','recepcion','groomer']);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $db    = DB::get();
        $ficha = self::getFicha($db, $id);
        if (!$ficha) Response::error('Ficha no encontrada', 404);
        if ($ficha['fecha_cierre']) Response::error('La ficha ya está cerrada');

        $fields = [];
        $params = [];
        $map = ['raza_momento','tamano_momento','estado_inicial','estado_final','notas_internas'];
        foreach ($map as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }
        if (isset($body['temperatura_animal'])) { $fields[] = "temperatura_animal = ?"; $params[] = (float)$body['temperatura_animal']; }

        if ($fields) {
            $params[] = $id;
            $db->prepare("UPDATE fichas_grooming SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }

        // Actualizar checklist
        if (!empty($body['checklist']) && is_array($body['checklist'])) {
            foreach ($body['checklist'] as $item) {
                $itemId      = Sanitizer::int($item['item_id'] ?? 0);
                $completado  = isset($item['completado']) ? (bool)$item['completado'] : false;
                $observacion = Sanitizer::string($item['observacion'] ?? '');
                $db->prepare("UPDATE checklist_ficha SET completado=?, observacion=? WHERE ficha_id=? AND item_id=?")
                   ->execute([$completado, $observacion, $id, $itemId]);
            }
        }

        AuditLog::log("Ficha #$id actualizada", 'fichas_grooming', $id);
        Response::ok(null, 'Ficha actualizada');
    }

    // POST /fichas/{id}/cerrar
    public static function cerrar(int $id): void {
        $user = Auth::require(['admin','recepcion','groomer']);
        $db   = DB::get();
        $ficha = self::getFicha($db, $id);
        if (!$ficha) Response::error('Ficha no encontrada', 404);
        if ($ficha['fecha_cierre']) Response::error('Ficha ya cerrada');

        // Validar checklist completo
        $pend = $db->prepare("SELECT COUNT(*) FROM checklist_ficha cf 
            JOIN checklist_items_maestro ci ON ci.id = cf.item_id
            WHERE cf.ficha_id = ? AND cf.completado = FALSE AND ci.requiere_observacion = TRUE");
        $pend->execute([$id]);
        if ((int)$pend->fetchColumn() > 0) Response::error('Hay items obligatorios del checklist sin completar');

        // Validar mínimo 1 foto antes y 1 después
        $fotos = $db->prepare("SELECT tipo, COUNT(*) as c FROM fotos_grooming WHERE ficha_id = ? GROUP BY tipo");
        $fotos->execute([$id]);
        $fotoMap = [];
        foreach ($fotos->fetchAll() as $f) $fotoMap[$f['tipo']] = $f['c'];
        if (empty($fotoMap['antes']) || empty($fotoMap['despues']))
            Response::error('Se requiere al menos 1 foto antes y 1 foto después');

        // Descontar insumos del inventario
        self::descontarInsumos($db, $ficha);

        // Cerrar ficha
        $db->prepare("UPDATE fichas_grooming SET fecha_cierre = NOW(), cerrado_por = ?, inventario_descontado = TRUE WHERE id = ?")
           ->execute([$user['user_id'], $id]);

        // Cambiar estado de cita a completada
        $db->prepare("UPDATE citas SET estado = 'completada', updated_at = NOW() WHERE id = ?")
           ->execute([$ficha['cita_id']]);

        AuditLog::log("Ficha #$id cerrada", 'fichas_grooming', $id);
        Response::ok(null, 'Ficha cerrada correctamente');
    }

    // POST /fichas/{id}/fotos
    public static function subirFoto(int $id): void {
        Auth::require(['admin','recepcion','groomer']);
        $tipo = Sanitizer::string($_POST['tipo'] ?? '');
        if (!in_array($tipo, ['antes','despues'])) Response::error("tipo debe ser 'antes' o 'despues'");
        if (!isset($_FILES['foto'])) Response::error('Archivo requerido');

        $file = $_FILES['foto'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) Response::error('Formato no permitido');
        if ($file['size'] > 8 * 1024 * 1024) Response::error('Archivo muy grande (máx 8MB)');

        $nombre  = uniqid("ficha{$id}_{$tipo}_", true) . ".$ext";
        $destino = __DIR__ . '/../../../uploads/fotos/' . $nombre;
        move_uploaded_file($file['tmp_name'], $destino);

        $url = APP_URL . '/uploads/fotos/' . $nombre;
        DB::get()->prepare("INSERT INTO fotos_grooming (ficha_id, tipo, url) VALUES (?,?,?)")
                 ->execute([$id, $tipo, $url]);

        Response::ok(['url' => $url], 'Foto subida');
    }

    private static function getFicha(PDO $db, int $id): array|false {
        $stmt = $db->prepare("SELECT fg.*, ci.servicio_id, ci.mascota_id 
            FROM fichas_grooming fg JOIN citas ci ON ci.id = fg.cita_id WHERE fg.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    private static function descontarInsumos(PDO $db, array $ficha): void {
        if ($ficha['inventario_descontado']) return;
        $srv = $db->prepare("SELECT consumo_insumos FROM servicios WHERE id = ?");
        $srv->execute([$ficha['servicio_id']]);
        $servicio = $srv->fetch();
        if (!$servicio || !$servicio['consumo_insumos']) return;

        $insumos = json_decode($servicio['consumo_insumos'], true);
        foreach ($insumos as $ins) {
            $pid = (int)($ins['producto_id'] ?? 0);
            $qty = (int)($ins['cantidad'] ?? 0);
            if (!$pid || !$qty) continue;
            $db->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?")
               ->execute([$qty, $pid]);
            // Verificar alerta de bajo inventario
            $p = $db->prepare("SELECT stock, stock_minimo, nombre FROM productos WHERE id = ?");
            $p->execute([$pid]);
            $prod = $p->fetch();
            if ($prod && $prod['stock'] <= $prod['stock_minimo']) {
                // TODO: disparar notificación de bajo stock
            }
        }
    }
}
