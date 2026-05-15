<?php
// src/Controllers/UsuariosController.php

class UsuariosController {

    // POST /admin/usuarios  — Admin crea personal (recepcion / groomer)
    public static function crearPersonal(): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $email    = Sanitizer::email($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $rol      = Sanitizer::string($body['rol'] ?? '');
        $nombre   = Sanitizer::string($body['nombre'] ?? '');
        $telefono = Sanitizer::string($body['telefono'] ?? '');

        if (!$email) Response::error('Email inválido');
        if (!in_array($rol, ['recepcion', 'groomer'])) Response::error('Rol inválido. Use: recepcion, groomer');

        $pwErrors = Sanitizer::password($password);
        if ($pwErrors) Response::error('Contraseña no válida', 422, $pwErrors);

        $db = DB::get();
        $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) Response::error('Email ya registrado', 409);

        $rolStmt = $db->prepare("SELECT id FROM roles WHERE nombre = ?");
        $rolStmt->execute([$rol]);
        $rolId = $rolStmt->fetchColumn();
        if (!$rolId) Response::error('Rol no encontrado', 404);

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO usuarios (email, password_hash, rol_id, estado) VALUES (?,?,?,TRUE) RETURNING id");
            $stmt->execute([$email, $hash, $rolId]);
            $userId = (int)$stmt->fetchColumn();

            if ($rol === 'groomer') {
                $especialidad = Sanitizer::string($body['especialidad'] ?? '');
                $turno        = Sanitizer::string($body['turno'] ?? 'mañana');
                $capacidad    = Sanitizer::int($body['capacidad_simultanea'] ?? 1);
                $db->prepare("INSERT INTO groomers (usuario_id, nombre, telefono, especialidad, turno, capacidad_simultanea) VALUES (?,?,?,?,?,?)")
                   ->execute([$userId, $nombre ?: $email, $telefono, $especialidad, $turno, $capacidad]);
            }
            // Para recepcion no se crea tabla adicional en este esquema

            $db->commit();
            AuditLog::log("Admin creó personal: $email ($rol)", 'usuarios', $userId);
            Response::ok(['id' => $userId], 'Usuario creado exitosamente');
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al crear usuario: ' . $e->getMessage(), 500);
        }
    }

    // GET /admin/usuarios
    public static function listar(): void {
        Auth::require(['admin']);
        $db   = DB::get();

        $where  = [];
        $params = [];

        if (!empty($_GET['buscar'])) {
            $q = '%' . Sanitizer::string($_GET['buscar']) . '%';
            $where[]  = "(u.email ILIKE ? OR r.nombre ILIKE ?)";
            $params[] = $q;
            $params[] = $q;
        }
        if (!empty($_GET['rol'])) {
            $where[]  = "r.nombre = ?";
            $params[] = Sanitizer::string($_GET['rol']);
        }
        if (isset($_GET['estado'])) {
            $where[]  = "u.estado = ?";
            $params[] = filter_var($_GET['estado'], FILTER_VALIDATE_BOOLEAN);
        }

        $sql = "SELECT u.id, u.email, u.estado, u.ultimo_acceso, u.oauth_provider,
                    u.intentos_fallidos, u.bloqueado_hasta, u.created_at,
                    r.nombre as rol,
                    COALESCE(c.nombre, g.nombre) as nombre_perfil,
                    COALESCE(c.telefono, g.telefono) as telefono
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                LEFT JOIN clientes c ON c.usuario_id = u.id
                LEFT JOIN groomers g ON g.usuario_id = u.id"
            . ($where ? " WHERE " . implode(' AND ', $where) : '')
            . " ORDER BY u.id DESC LIMIT 200";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::ok($stmt->fetchAll());
    }

    // GET /admin/usuarios/{id}/detalle
    public static function detalle(int $id): void {
        Auth::require(['admin']);
        $db   = DB::get();

        $stmt = $db->prepare("SELECT u.id, u.email, u.estado, u.oauth_provider,
                u.intentos_fallidos, u.bloqueado_hasta, u.ultimo_acceso, u.created_at,
                u.two_factor_enabled,
                r.nombre as rol,
                c.nombre as nombre_cliente, c.telefono, c.ci, c.direccion, c.canal_notif,
                g.nombre as nombre_groomer, g.especialidad, g.turno, g.capacidad_simultanea,
                (SELECT COUNT(*) FROM user_sessions s WHERE s.usuario_id=u.id AND s.expires_at>NOW()) as sesiones_activas,
                (SELECT COUNT(*) FROM audit_log a WHERE a.usuario_id=u.id) as total_acciones
            FROM usuarios u
            JOIN roles r ON r.id=u.rol_id
            LEFT JOIN clientes c ON c.usuario_id=u.id
            LEFT JOIN groomers g ON g.usuario_id=u.id
            WHERE u.id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no encontrado', 404);

        // Últimas 10 acciones del audit log
        $logs = $db->prepare("SELECT accion, ip_address, created_at, tabla FROM audit_log WHERE usuario_id=? ORDER BY id DESC LIMIT 10");
        $logs->execute([$id]);
        $user['ultimas_acciones'] = $logs->fetchAll();

        // Mascotas si es cliente
        if ($user['rol'] === 'cliente') {
            $cl = $db->prepare("SELECT id FROM clientes WHERE usuario_id=?");
            $cl->execute([$id]);
            $cId = $cl->fetchColumn();
            if ($cId) {
                $m = $db->prepare("SELECT m.nombre, m.especie, m.raza FROM mascotas m JOIN mascota_dueno md ON md.mascota_id=m.id WHERE md.cliente_id=?");
                $m->execute([$cId]);
                $user['mascotas'] = $m->fetchAll();
            }
        }

        Response::ok($user);
    }

    // PUT /admin/usuarios/{id}/estado
    public static function cambiarEstado(int $id): void {
        Auth::require(['admin']);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $activo = isset($body['activo']) ? (bool)$body['activo'] : null;
        if ($activo === null) Response::error('Campo activo requerido');

        $db = DB::get();

        // No permitir desactivar el propio admin
        // (asumimos que el user_id está en el payload del token, disponible via Auth)
        $db->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")->execute([$activo, $id]);

        // Si se desactiva, invalidar todas sus sesiones
        if (!$activo) {
            $db->prepare("DELETE FROM user_sessions WHERE usuario_id=?")->execute([$id]);
        }

        AuditLog::log("Estado de usuario $id cambiado a " . ($activo ? 'activo' : 'inactivo'), 'usuarios', $id);
        Response::ok(null, 'Estado actualizado');
    }

    // POST /admin/usuarios/{id}/reset-password
    public static function resetPassword(int $id): void {
        Auth::require(['admin']);

        // Generar contraseña temporal segura que cumpla los requisitos
        $chars   = 'abcdefghjkmnpqrstuvwxyz';
        $upper   = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $numbers = '23456789';
        $symbols = '!@#$';

        // Garantizar al menos 1 de cada tipo requerido
        $tempPwd = '';
        $tempPwd .= $upper[random_int(0, strlen($upper)-1)];
        $tempPwd .= $numbers[random_int(0, strlen($numbers)-1)];
        $tempPwd .= $symbols[random_int(0, strlen($symbols)-1)];

        $all = $chars . $upper . $numbers . $symbols;
        for ($i = 0; $i < 7; $i++) $tempPwd .= $all[random_int(0, strlen($all)-1)];

        // Mezclar caracteres
        $tempPwd = str_shuffle($tempPwd);

        $hash = password_hash($tempPwd, PASSWORD_BCRYPT, ['cost' => 12]);

        $db = DB::get();

        // Obtener datos del usuario
        $stmt = $db->prepare("SELECT u.email, COALESCE(c.nombre, g.nombre, u.email) as nombre
            FROM usuarios u
            LEFT JOIN clientes c ON c.usuario_id=u.id
            LEFT JOIN groomers g ON g.usuario_id=u.id
            WHERE u.id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no encontrado', 404);

        $db->prepare("UPDATE usuarios SET password_hash=?, intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?")
           ->execute([$hash, $id]);

        // Invalidar todas sus sesiones activas
        $db->prepare("DELETE FROM user_sessions WHERE usuario_id=?")->execute([$id]);

        // Intentar enviar email con la contraseña temporal
        $emailEnviado = false;
        try {
            $autoload = self::findAutoload();
            if ($autoload && getenv('MAIL_USER')) {
                require_once $autoload;
                require_once __DIR__ . '/../Helpers/Mailer.php';
                $emailEnviado = Mailer::enviarPasswordReseteada($user['email'], $user['nombre'], $tempPwd);
            }
        } catch (Throwable $e) {
            error_log('Error enviando email de reset: ' . $e->getMessage());
        }

        AuditLog::log("Admin reseteó contraseña del usuario #$id", 'usuarios', $id);

        Response::ok([
            'password_temporal' => $tempPwd,
            'email_enviado'     => $emailEnviado,
            'email'             => $user['email'],
            'mensaje'           => 'Contraseña reseteada. ' . ($emailEnviado ? 'Se envió al correo.' : 'Entrégala manualmente.'),
        ], 'Contraseña reseteada');
    }

    // PUT /auth/change-password — usuario autenticado cambia su propia clave
    public static function changePassword(): void {
        $user = Auth::require();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $actual = $body['password_actual'] ?? '';
        $nueva  = $body['password_nueva']  ?? '';

        if (!$actual || !$nueva) Response::error('Ambas contraseñas son requeridas');

        $db   = DB::get();
        $stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$user['user_id']]);
        $row  = $stmt->fetch();

        if (!$row || !password_verify($actual, $row['password_hash'])) {
            Response::error('Contraseña actual incorrecta', 401);
        }

        $pwErrors = Sanitizer::password($nueva);
        if ($pwErrors) Response::error('Nueva contraseña no válida', 422, $pwErrors);

        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$hash, $user['user_id']]);

        AuditLog::log('Cambio de contraseña', 'usuarios', $user['user_id']);
        Response::ok(null, 'Contraseña actualizada');
    }

    private static function findAutoload(): string|false {
        $paths = [
            '/var/www/html/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
        ];
        foreach ($paths as $p) { if (file_exists($p)) return $p; }
        return false;
    }
}
