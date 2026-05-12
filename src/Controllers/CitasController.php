<?php
// src/Controllers/CitasController.php

class CitasController {

    // GET /citas
    public static function listar(): void {
        $user = Auth::require(['admin','recepcion','groomer','cliente']);
        $db   = DB::get();

        $where  = [];
        $params = [];

        if ($user['rol'] === 'cliente') {
            $where[]  = "c.usuario_id = ?";
            $params[] = $user['user_id'];
        } elseif ($user['rol'] === 'groomer') {
            $where[]  = "g.usuario_id = ?";
            $params[] = $user['user_id'];
        }

        // Filtros opcionales via query string
        if (!empty($_GET['fecha'])) {
            $where[]  = "DATE(ci.fecha_hora_inicio) = ?";
            $params[] = Sanitizer::string($_GET['fecha']);
        }
        if (!empty($_GET['estado'])) {
            $where[]  = "ci.estado = ?";
            $params[] = Sanitizer::string($_GET['estado']);
        }
        if (!empty($_GET['groomer_id'])) {
            $where[]  = "ci.groomer_id = ?";
            $params[] = Sanitizer::int($_GET['groomer_id']);
        }

        $sql = "SELECT ci.*, 
                    m.nombre as mascota_nombre, m.raza, m.peso_kg,
                    g.nombre as groomer_nombre,
                    s.nombre as servicio_nombre, s.precio_base,
                    cl.nombre as cliente_nombre, cl.telefono
                FROM citas ci
                JOIN mascotas m ON m.id = ci.mascota_id
                JOIN groomers g ON g.id = ci.groomer_id
                JOIN servicios s ON s.id = ci.servicio_id
                JOIN clientes cl ON cl.id = ci.cliente_id
                LEFT JOIN usuarios c ON c.id = cl.usuario_id"
              . ($where ? " WHERE " . implode(' AND ', $where) : '')
              . " ORDER BY ci.fecha_hora_inicio DESC LIMIT 200";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::ok($stmt->fetchAll());
    }

    // GET /citas/{id}
    public static function ver(int $id): void {
        $user = Auth::require(['admin','recepcion','groomer','cliente']);
        $db   = DB::get();
        $cita = self::getCita($db, $id);
        if (!$cita) Response::error('Cita no encontrada', 404);
        self::checkAcceso($user, $cita);
        Response::ok($cita);
    }

    // POST /citas
    public static function crear(): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $mascotaId  = Sanitizer::int($body['mascota_id']  ?? 0);
        $groromerId = Sanitizer::int($body['groomer_id']  ?? 0);
        $servicioId = Sanitizer::int($body['servicio_id'] ?? 0);
        $fechaHora  = Sanitizer::string($body['fecha_hora_inicio'] ?? '');
        $notas      = Sanitizer::string($body['notas'] ?? '');

        if (!$mascotaId || !$groromerId || !$servicioId || !$fechaHora)
            Response::error('mascota_id, groomer_id, servicio_id y fecha_hora_inicio son requeridos');

        $db = DB::get();

        // Obtener cliente_id
        $clienteId = null;
        if ($user['rol'] === 'cliente') {
            $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
            $c->execute([$user['user_id']]);
            $clienteId = $c->fetchColumn();
        } else {
            $clienteId = Sanitizer::int($body['cliente_id'] ?? 0);
        }
        if (!$clienteId) Response::error('cliente_id requerido');

        // Obtener servicio y duración
        $srv = $db->prepare("SELECT * FROM servicios WHERE id = ? AND activo = TRUE");
        $srv->execute([$servicioId]);
        $servicio = $srv->fetch();
        if (!$servicio) Response::error('Servicio no encontrado');

        // Calcular duración con ajuste por tamaño
        $mascotaStmt = $db->prepare("SELECT peso_kg, raza FROM mascotas WHERE id = ?");
        $mascotaStmt->execute([$mascotaId]);
        $mascota = $mascotaStmt->fetch();
        $duracion = self::calcularDuracion($servicio, $mascota);
        $fechaFin = date('Y-m-d H:i:s', strtotime($fechaHora) + $duracion * 60);

        // Validar disponibilidad groomer
        self::validarDisponibilidad($db, $groromerId, $fechaHora, $fechaFin, $servicioId);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO citas 
                (mascota_id, groomer_id, servicio_id, cliente_id, fecha_hora_inicio, fecha_hora_fin, duracion_estimada, estado, creado_por, notas)
                VALUES (?,?,?,?,?,?,?,'agendada',?,?) RETURNING id");
            $stmt->execute([$mascotaId, $groromerId, $servicioId, $clienteId,
                            $fechaHora, $fechaFin, $duracion, $user['user_id'], $notas]);
            $citaId = $stmt->fetchColumn();

            // Crear notificaciones automáticas
            self::programarNotificaciones($db, $citaId, $clienteId, $fechaHora);

            $db->commit();
            AuditLog::log("Cita creada #$citaId", 'citas', $citaId);
            Response::ok(['id' => $citaId], 'Cita agendada exitosamente');
        } catch (Throwable $e) {
            $db->rollBack();
            if (str_contains($e->getMessage(), 'idx_citas_groomer_horario'))
                Response::error('El groomer ya tiene una cita en ese horario', 409);
            Response::error('Error al crear cita: ' . $e->getMessage(), 500);
        }
    }

    // PUT /citas/{id}/estado
    public static function cambiarEstado(int $id): void {
        $user = Auth::require(['admin','recepcion','groomer']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $estado = Sanitizer::string($body['estado'] ?? '');
        $estados = ['agendada','confirmada','en_progreso','completada','cancelada','no_asistio'];

        if (!in_array($estado, $estados)) Response::error('Estado inválido');

        $db   = DB::get();
        $cita = self::getCita($db, $id);
        if (!$cita) Response::error('Cita no encontrada', 404);

        // No se puede cambiar groomer en cita confirmada
        if ($cita['estado'] === 'confirmada' && isset($body['groomer_id'])) {
            Response::error('No se puede cambiar el groomer de una cita confirmada');
        }

        $db->prepare("UPDATE citas SET estado = ?, updated_at = NOW() WHERE id = ?")->execute([$estado, $id]);
        AuditLog::log("Cita #$id cambió estado a $estado", 'citas', $id);

        // Si completada, crear ficha de grooming automáticamente
        if ($estado === 'en_progreso') {
            $existe = $db->prepare("SELECT id FROM fichas_grooming WHERE cita_id = ?");
            $existe->execute([$id]);
            if (!$existe->fetch()) {
                $db->prepare("INSERT INTO fichas_grooming (cita_id) VALUES (?)")->execute([$id]);
                // Insertar checklist items estándar
                $items = $db->query("SELECT id FROM checklist_items_maestro");
                $fichaStmt = $db->prepare("SELECT id FROM fichas_grooming WHERE cita_id = ?");
                $fichaStmt->execute([$id]);
                $fichaId = $fichaStmt->fetchColumn();
                foreach ($items->fetchAll() as $item) {
                    $db->prepare("INSERT INTO checklist_ficha (ficha_id, item_id) VALUES (?,?) ON CONFLICT DO NOTHING")
                       ->execute([$fichaId, $item['id']]);
                }
            }
        }

        // Si completada → programar encuesta
        if ($estado === 'completada') {
            $cita2 = self::getCita($db, $id);
            $db->prepare("INSERT INTO notificaciones (cita_id, cliente_id, tipo_evento, canal, fecha_programada)
                VALUES (?,?,'encuesta','email', NOW() + INTERVAL '2 hours')")
               ->execute([$id, $cita2['cliente_id'] ?? null]);
        }

        Response::ok(null, 'Estado actualizado');
    }

    // PUT /citas/{id}/reprogramar
    public static function reprogramar(int $id): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $nuevaFecha = Sanitizer::string($body['fecha_hora_inicio'] ?? '');
        if (!$nuevaFecha) Response::error('Nueva fecha requerida');

        $db   = DB::get();
        $cita = self::getCita($db, $id);
        if (!$cita) Response::error('Cita no encontrada', 404);

        $srv = $db->prepare("SELECT duracion_base_minutos FROM servicios WHERE id = ?");
        $srv->execute([$cita['servicio_id']]);
        $servicio = $srv->fetch();
        $fechaFin = date('Y-m-d H:i:s', strtotime($nuevaFecha) + $servicio['duracion_base_minutos'] * 60);

        self::validarDisponibilidad($db, $cita['groomer_id'], $nuevaFecha, $fechaFin, $cita['servicio_id'], $id);

        $db->prepare("UPDATE citas SET fecha_hora_inicio=?, fecha_hora_fin=?, reprogramado_por=?, fecha_reprogramacion=NOW(), estado='agendada', updated_at=NOW() WHERE id=?")
           ->execute([$nuevaFecha, $fechaFin, $user['user_id'], $id]);

        AuditLog::log("Cita #$id reprogramada a $nuevaFecha", 'citas', $id);
        Response::ok(null, 'Cita reprogramada');
    }

    // GET /disponibilidad?groomer_id=1&fecha=2025-05-10&servicio_id=1
    public static function disponibilidad(): void {
        Auth::require(['admin','recepcion','cliente']);
        $groId  = Sanitizer::int($_GET['groomer_id'] ?? 0);
        $fecha  = Sanitizer::string($_GET['fecha'] ?? '');
        $srvId  = Sanitizer::int($_GET['servicio_id'] ?? 0);
        if (!$groId || !$fecha) Response::error('groomer_id y fecha requeridos');

        $db = DB::get();
        $diaSemana = (int)date('w', strtotime($fecha));

        // Horario del groomer ese día
        $dispoStmt = $db->prepare("SELECT * FROM disponibilidad_groomer WHERE groomer_id = ? AND dia_semana = ?");
        $dispoStmt->execute([$groId, $diaSemana]);
        $dispo = $dispoStmt->fetch();

        if (!$dispo) { Response::ok(['slots' => [], 'mensaje' => 'Groomer no disponible ese día']); return; }

        // Bloqueos del día
        $bloqStmt = $db->prepare("SELECT 1 FROM bloqueos_calendario 
            WHERE (groomer_id = ? OR groomer_id IS NULL) 
              AND fecha_inicio <= ? AND fecha_fin >= ?");
        $bloqStmt->execute([$groId, "$fecha 23:59:59", "$fecha 00:00:00"]);
        if ($bloqStmt->fetch()) { Response::ok(['slots' => [], 'mensaje' => 'Groomer bloqueado ese día']); return; }

        // Citas ya agendadas
        $citasStmt = $db->prepare("SELECT fecha_hora_inicio, fecha_hora_fin FROM citas 
            WHERE groomer_id = ? AND DATE(fecha_hora_inicio) = ? AND estado NOT IN ('cancelada','no_asistio')");
        $citasStmt->execute([$groId, $fecha]);
        $citasDelDia = $citasStmt->fetchAll();

        $duracion = 60;
        if ($srvId) {
            $s = $db->prepare("SELECT duracion_base_minutos FROM servicios WHERE id=?");
            $s->execute([$srvId]);
            $duracion = (int)($s->fetchColumn() ?: 60);
        }

        $slots   = [];
        $inicio  = strtotime("$fecha " . $dispo['hora_inicio']);
        $fin     = strtotime("$fecha " . $dispo['hora_fin']);
        $buffer  = ($dispo['buffer_minutos'] ?? 15) * 60;
        $descanso = $dispo['descanso'] ? json_decode($dispo['descanso'], true) : null;

        for ($t = $inicio; $t + $duracion * 60 <= $fin; $t += 900) { // pasos de 15min
            $slotFin = $t + $duracion * 60;
            $slotStr = date('H:i', $t);
            $libre = true;

            // Verificar descanso
            if ($descanso) {
                $dIni = strtotime("$fecha " . $descanso['inicio']);
                $dFin = strtotime("$fecha " . $descanso['fin']);
                if ($t < $dFin && $slotFin > $dIni) { $libre = false; }
            }

            // Verificar solapamiento con citas
            if ($libre) {
                foreach ($citasDelDia as $c) {
                    $ci = strtotime($c['fecha_hora_inicio']);
                    $cf = strtotime($c['fecha_hora_fin']) + $buffer;
                    if ($t < $cf && $slotFin > $ci) { $libre = false; break; }
                }
            }

            if ($libre) $slots[] = $slotStr;
        }

        Response::ok(['slots' => $slots, 'fecha' => $fecha, 'groomer_id' => $groId]);
    }

    // ── Helpers privados ────────────────────────────────────

    private static function getCita(PDO $db, int $id): array|false {
        $stmt = $db->prepare("SELECT ci.*, m.nombre as mascota_nombre, g.nombre as groomer_nombre,
                s.nombre as servicio_nombre, cl.nombre as cliente_nombre, cl.usuario_id as cliente_usuario_id
            FROM citas ci
            JOIN mascotas m ON m.id = ci.mascota_id
            JOIN groomers g ON g.id = ci.groomer_id
            JOIN servicios s ON s.id = ci.servicio_id
            JOIN clientes cl ON cl.id = ci.cliente_id
            WHERE ci.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    private static function checkAcceso(array $user, array $cita): void {
        if ($user['rol'] === 'cliente' && $cita['cliente_usuario_id'] != $user['user_id'])
            Response::error('Sin acceso a esta cita', 403);
    }

    private static function calcularDuracion(array $servicio, array|false $mascota): int {
        $base = (int)$servicio['duracion_base_minutos'];
        if (!$mascota || !$servicio['factor_tamano_raza']) return $base;
        $factores = json_decode($servicio['factor_tamano_raza'], true);
        $peso = (float)($mascota['peso_kg'] ?? 0);
        $key  = $peso <= 10 ? 'pequeno' : ($peso <= 25 ? 'mediano' : 'grande');
        return $base + (int)($factores[$key] ?? 0);
    }

    private static function validarDisponibilidad(PDO $db, int $groomerId, string $ini, string $fin, int $servicioId, int $excluirCitaId = 0): void {
        // Bloqueos
        $b = $db->prepare("SELECT 1 FROM bloqueos_calendario WHERE (groomer_id=? OR groomer_id IS NULL) AND fecha_inicio<=? AND fecha_fin>=?");
        $b->execute([$groomerId, $ini, $ini]);
        if ($b->fetch()) Response::error('Groomer bloqueado en ese horario', 409);

        // Solapamiento
        $sql = "SELECT 1 FROM citas WHERE groomer_id=? AND estado NOT IN ('cancelada','no_asistio')
                AND fecha_hora_inicio < ? AND fecha_hora_fin > ?";
        $p = [$groomerId, $fin, $ini];
        if ($excluirCitaId) { $sql .= " AND id != ?"; $p[] = $excluirCitaId; }
        $s = $db->prepare($sql);
        $s->execute($p);
        if ($s->fetch()) Response::error('El groomer tiene una cita que se solapa con ese horario', 409);
    }

    private static function programarNotificaciones(PDO $db, int $citaId, int $clienteId, string $fechaHora): void {
        $ts = strtotime($fechaHora);
        $eventos = [
            ['confirmacion',    date('Y-m-d H:i:s')],
            ['recordatorio_24h', date('Y-m-d H:i:s', $ts - 86400)],
            ['recordatorio_2h',  date('Y-m-d H:i:s', $ts - 7200)],
        ];
        foreach ($eventos as [$tipo, $prog]) {
            $db->prepare("INSERT INTO notificaciones (cita_id, cliente_id, tipo_evento, canal, fecha_programada) VALUES (?,?,?,'email',?)")
               ->execute([$citaId, $clienteId, $tipo, $prog]);
        }
    }
}
