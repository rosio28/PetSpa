<?php
// src/Controllers/MascotasController.php

class MascotasController {

    // GET /mascotas  — cliente ve sus mascotas; admin/recepcion ven todas
    public static function listar(): void {
        $user = Auth::require(['admin','recepcion','groomer','cliente']);
        $db   = DB::get();

        if ($user['rol'] === 'cliente') {
            $stmt = $db->prepare("
                SELECT m.*, md.es_principal
                FROM mascotas m
                JOIN mascota_dueno md ON md.mascota_id = m.id
                JOIN clientes c ON c.id = md.cliente_id
                WHERE c.usuario_id = ?
                ORDER BY m.nombre");
            $stmt->execute([$user['user_id']]);
        } else {
            $stmt = $db->query("SELECT m.*, c.nombre as dueno_nombre
                FROM mascotas m
                LEFT JOIN mascota_dueno md ON md.mascota_id = m.id AND md.es_principal = TRUE
                LEFT JOIN clientes c ON c.id = md.cliente_id
                ORDER BY m.nombre");
        }
        Response::ok($stmt->fetchAll());
    }

    // GET /mascotas/{id}
    public static function ver(int $id): void {
        $user = Auth::require(['admin','recepcion','groomer','cliente']);
        $db   = DB::get();

        self::verificarAcceso($db, $user, $id);

        $stmt = $db->prepare("SELECT * FROM mascotas WHERE id = ?");
        $stmt->execute([$id]);
        $mascota = $stmt->fetch();
        if (!$mascota) Response::error('Mascota no encontrada', 404);

        // Dueños
        $duenos = $db->prepare("SELECT c.id, c.nombre, c.telefono, md.es_principal FROM clientes c JOIN mascota_dueno md ON md.cliente_id = c.id WHERE md.mascota_id = ?");
        $duenos->execute([$id]);
        $mascota['duenos'] = $duenos->fetchAll();

        // Historial
        $hist = $db->prepare("SELECT * FROM historial_mascota WHERE mascota_id = ? ORDER BY created_at DESC LIMIT 20");
        $hist->execute([$id]);
        $mascota['historial'] = $hist->fetchAll();

        Response::ok($mascota);
    }

    // POST /mascotas
    public static function crear(): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $nombre      = Sanitizer::string($body['nombre'] ?? '');
        $especie     = Sanitizer::string($body['especie'] ?? '');
        $raza        = Sanitizer::string($body['raza'] ?? '');
        $peso        = isset($body['peso_kg']) ? (float)$body['peso_kg'] : null;
        $nacimiento  = Sanitizer::string($body['fecha_nacimiento'] ?? '');
        $temperamento = Sanitizer::string($body['temperamento'] ?? '');
        $alergias    = Sanitizer::string($body['alergias'] ?? '');
        $restricciones = Sanitizer::string($body['restricciones_medicas'] ?? '');
        $vacunas     = $body['vacunas'] ?? [];

        if (!$nombre) Response::error('Nombre de mascota requerido');

        $db = DB::get();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO mascotas 
                (nombre, especie, raza, peso_kg, fecha_nacimiento, temperamento, alergias, restricciones_medicas, vacunas)
                VALUES (?,?,?,?,?,?,?,?,?) RETURNING id");
            $stmt->execute([
                $nombre, $especie, $raza, $peso,
                $nacimiento ?: null, $temperamento, $alergias,
                $restricciones, $vacunas ? json_encode($vacunas) : null
            ]);
            $mascotaId = $stmt->fetchColumn();

            // Vincular al cliente
            $clienteId = null;
            if ($user['rol'] === 'cliente') {
                $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
                $c->execute([$user['user_id']]);
                $clienteId = $c->fetchColumn();
            } elseif (!empty($body['cliente_id'])) {
                $clienteId = Sanitizer::int($body['cliente_id']);
            }

            if ($clienteId) {
                $db->prepare("INSERT INTO mascota_dueno (mascota_id, cliente_id, es_principal) VALUES (?,?,TRUE)")
                   ->execute([$mascotaId, $clienteId]);
            }

            $db->commit();
            AuditLog::log("Mascota creada: $nombre", 'mascotas', $mascotaId);
            Response::ok(['id' => $mascotaId], 'Mascota registrada');
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al crear mascota: ' . $e->getMessage(), 500);
        }
    }

    // PUT /mascotas/{id}
    public static function actualizar(int $id): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $db   = DB::get();
        self::verificarAcceso($db, $user, $id);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [];

        $map = ['nombre','especie','raza','temperamento','alergias','restricciones_medicas'];
        foreach ($map as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }
        if (isset($body['peso_kg']))         { $fields[] = "peso_kg = ?";         $params[] = (float)$body['peso_kg']; }
        if (isset($body['fecha_nacimiento'])) { $fields[] = "fecha_nacimiento = ?"; $params[] = $body['fecha_nacimiento']; }
        if (isset($body['vacunas']))          { $fields[] = "vacunas = ?";          $params[] = json_encode($body['vacunas']); }

        if (!$fields) Response::error('Nada que actualizar');

        $params[] = $id;
        $db->prepare("UPDATE mascotas SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        AuditLog::log("Mascota actualizada", 'mascotas', $id);
        Response::ok(null, 'Mascota actualizada');
    }

    // DELETE /mascotas/{id}  — solo admin (soft delete vía historial)
    public static function eliminar(int $id): void {
        Auth::require(['admin']);
        $db = DB::get();
        $db->prepare("DELETE FROM mascotas WHERE id = ?")->execute([$id]);
        AuditLog::log("Mascota eliminada", 'mascotas', $id);
        Response::ok(null, 'Mascota eliminada');
    }

    // POST /mascotas/{id}/foto
    public static function subirFoto(int $id): void {
        Auth::require(['admin','recepcion','groomer','cliente']);
        if (!isset($_FILES['foto'])) Response::error('Archivo requerido');

        $file = $_FILES['foto'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) Response::error('Formato no permitido');
        if ($file['size'] > 5 * 1024 * 1024) Response::error('Archivo muy grande (máx 5MB)');

        $nombre = uniqid('mascota_', true) . '.' . $ext;
        $destino = __DIR__ . '/../../../uploads/fotos/' . $nombre;
        move_uploaded_file($file['tmp_name'], $destino);

        $url = APP_URL . '/uploads/fotos/' . $nombre;
        DB::get()->prepare("UPDATE mascotas SET foto_url = ? WHERE id = ?")->execute([$url, $id]);

        Response::ok(['url' => $url], 'Foto subida');
    }

    private static function verificarAcceso(PDO $db, array $user, int $mascotaId): void {
        if ($user['rol'] === 'cliente') {
            $stmt = $db->prepare("SELECT 1 FROM mascota_dueno md JOIN clientes c ON c.id=md.cliente_id WHERE md.mascota_id=? AND c.usuario_id=?");
            $stmt->execute([$mascotaId, $user['user_id']]);
            if (!$stmt->fetch()) Response::error('Sin acceso a esta mascota', 403);
        }
    }
}
