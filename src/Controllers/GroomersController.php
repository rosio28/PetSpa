<?php
// src/Controllers/GroomersController.php

class GroomersController {

    // GET /groomers
    public static function listar(): void {
        Auth::require(['admin','recepcion','cliente']);
        $stmt = DB::get()->query("SELECT g.*, u.email FROM groomers g JOIN usuarios u ON u.id=g.usuario_id WHERE g.estado_activo=TRUE ORDER BY g.nombre");
        Response::ok($stmt->fetchAll());
    }

    // GET /groomers/{id}
    public static function ver(int $id): void {
        Auth::require(['admin','recepcion','groomer','cliente']);
        $stmt = DB::get()->prepare("SELECT g.*, u.email FROM groomers g JOIN usuarios u ON u.id=g.usuario_id WHERE g.id=?");
        $stmt->execute([$id]);
        $g = $stmt->fetch();
        if (!$g) Response::error('Groomer no encontrado', 404);
        Response::ok($g);
    }

    // PUT /groomers/{id}
    public static function actualizar(int $id): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db   = DB::get();

        $fields = []; $params = [];
        foreach (['nombre','telefono','especialidad','turno'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }
        if (isset($body['capacidad_simultanea'])) { $fields[] = "capacidad_simultanea = ?"; $params[] = Sanitizer::int($body['capacidad_simultanea']); }
        if (isset($body['horario_trabajo']))       { $fields[] = "horario_trabajo = ?";       $params[] = json_encode($body['horario_trabajo']); }
        if (isset($body['estado_activo']))         { $fields[] = "estado_activo = ?";         $params[] = (bool)$body['estado_activo']; }

        if ($fields) {
            $params[] = $id;
            $db->prepare("UPDATE groomers SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        AuditLog::log("Groomer #$id actualizado", 'groomers', $id);
        Response::ok(null, 'Groomer actualizado');
    }

    // POST /groomers/{id}/disponibilidad  — configurar horarios por día
    public static function setDisponibilidad(int $groomerId): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        // body: [{dia_semana:1, hora_inicio:"09:00", hora_fin:"18:00", descanso:{inicio:"13:00",fin:"14:00"}, buffer_minutos:15}]

        if (!is_array($body)) Response::error('Se espera un array de disponibilidad');

        $db = DB::get();
        $db->beginTransaction();
        try {
            foreach ($body as $d) {
                $dia  = Sanitizer::int($d['dia_semana'] ?? -1);
                $ini  = Sanitizer::string($d['hora_inicio'] ?? '');
                $fin  = Sanitizer::string($d['hora_fin'] ?? '');
                if ($dia < 0 || $dia > 6 || !$ini || !$fin) continue;

                $descanso = isset($d['descanso']) ? json_encode($d['descanso']) : null;
                $buffer   = Sanitizer::int($d['buffer_minutos'] ?? 15);

                $db->prepare("INSERT INTO disponibilidad_groomer (groomer_id, dia_semana, hora_inicio, hora_fin, descanso, buffer_minutos)
                    VALUES (?,?,?,?,?,?)
                    ON CONFLICT (groomer_id, dia_semana) DO UPDATE SET
                        hora_inicio=EXCLUDED.hora_inicio, hora_fin=EXCLUDED.hora_fin,
                        descanso=EXCLUDED.descanso, buffer_minutos=EXCLUDED.buffer_minutos")
                   ->execute([$groomerId, $dia, $ini, $fin, $descanso, $buffer]);
            }
            $db->commit();
            Response::ok(null, 'Disponibilidad configurada');
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al guardar disponibilidad: ' . $e->getMessage(), 500);
        }
    }

    // GET /groomers/{id}/disponibilidad
    public static function getDisponibilidad(int $groomerId): void {
        Auth::require(['admin','recepcion','cliente']);
        $stmt = DB::get()->prepare("SELECT * FROM disponibilidad_groomer WHERE groomer_id=? ORDER BY dia_semana");
        $stmt->execute([$groomerId]);
        Response::ok($stmt->fetchAll());
    }

    // POST /groomers/{id}/bloqueos
    public static function crearBloqueo(int $groomerId): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $tipo = Sanitizer::string($body['tipo'] ?? '');
        $ini  = Sanitizer::string($body['fecha_inicio'] ?? '');
        $fin  = Sanitizer::string($body['fecha_fin'] ?? '');

        if (!$tipo || !$ini || !$fin) Response::error('tipo, fecha_inicio y fecha_fin requeridos');
        if (strtotime($fin) <= strtotime($ini)) Response::error('fecha_fin debe ser mayor a fecha_inicio');

        $db   = DB::get();
        $gid  = $groomerId > 0 ? $groomerId : null; // 0 = global
        $stmt = $db->prepare("INSERT INTO bloqueos_calendario (groomer_id, tipo, fecha_inicio, fecha_fin, descripcion) VALUES (?,?,?,?,?) RETURNING id");
        $stmt->execute([$gid, $tipo, $ini, $fin, Sanitizer::string($body['descripcion'] ?? '')]);

        AuditLog::log("Bloqueo creado para groomer $groomerId: $tipo", 'bloqueos_calendario', (int)$stmt->fetchColumn());
        Response::ok(null, 'Bloqueo registrado');
    }

    // GET /groomers/{id}/bloqueos
    public static function getBloqueos(int $groomerId): void {
        Auth::require(['admin','recepcion']);
        $stmt = DB::get()->prepare("SELECT * FROM bloqueos_calendario WHERE groomer_id=? OR groomer_id IS NULL ORDER BY fecha_inicio");
        $stmt->execute([$groomerId ?: null]);
        Response::ok($stmt->fetchAll());
    }

    // DELETE /groomers/bloqueos/{id}
    public static function eliminarBloqueo(int $bloqueoId): void {
        Auth::require(['admin']);
        DB::get()->prepare("DELETE FROM bloqueos_calendario WHERE id=?")->execute([$bloqueoId]);
        Response::ok(null, 'Bloqueo eliminado');
    }
}
