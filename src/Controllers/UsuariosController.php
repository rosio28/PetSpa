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
            $userId = $stmt->fetchColumn();

            if ($rol === 'groomer') {
                $especialidad = Sanitizer::string($body['especialidad'] ?? '');
                $turno        = Sanitizer::string($body['turno'] ?? '');
                $db->prepare("INSERT INTO groomers (usuario_id, nombre, telefono, especialidad, turno) VALUES (?,?,?,?,?)")
                   ->execute([$userId, $nombre, $telefono, $especialidad, $turno]);
            } else {
                // recepcion — guardamos info básica en clientes o tabla futura
                // por ahora registramos el usuario solamente
            }

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
        $stmt = $db->query("SELECT u.id, u.email, u.estado, u.ultimo_acceso, r.nombre as rol
            FROM usuarios u JOIN roles r ON r.id = u.rol_id ORDER BY u.id DESC");
        Response::ok($stmt->fetchAll());
    }

    // PUT /admin/usuarios/{id}/estado
    public static function cambiarEstado(int $id): void {
        Auth::require(['admin']);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $activo = isset($body['activo']) ? (bool)$body['activo'] : null;
        if ($activo === null) Response::error('Campo activo requerido');

        DB::get()->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")
                 ->execute([$activo, $id]);

        AuditLog::log("Estado de usuario $id cambiado a " . ($activo ? 'activo' : 'inactivo'), 'usuarios', $id);
        Response::ok(null, 'Estado actualizado');
    }

    // PUT /auth/change-password  (usuario autenticado cambia su propia clave)
    public static function changePassword(): void {
        $user = Auth::require();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $actual = $body['password_actual'] ?? '';
        $nueva  = $body['password_nueva']  ?? '';

        $db   = DB::get();
        $stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$user['user_id']]);
        $row  = $stmt->fetch();

        if (!password_verify($actual, $row['password_hash'])) Response::error('Contraseña actual incorrecta', 401);

        $pwErrors = Sanitizer::password($nueva);
        if ($pwErrors) Response::error('Nueva contraseña no válida', 422, $pwErrors);

        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$hash, $user['user_id']]);

        AuditLog::log('Cambio de contraseña', 'usuarios', $user['user_id']);
        Response::ok(null, 'Contraseña actualizada');
    }

    // POST /admin/usuarios/{id}/reset-password
    public static function resetPassword(int $id): void {
        Auth::require(['admin']);

        // Generar contraseña temporal segura
        $chars   = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
        $tempPwd = '';
        for ($i = 0; $i < 10; $i++) $tempPwd .= $chars[random_int(0, strlen($chars)-1)];

        $hash = password_hash($tempPwd, PASSWORD_BCRYPT, ['cost'=>12]);

        $db = DB::get();

        // Obtener datos del usuario
        $stmt = $db->prepare("SELECT u.email, c.nombre FROM usuarios u LEFT JOIN clientes c ON c.usuario_id=u.id WHERE u.id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no encontrado', 404);

        $db->prepare("UPDATE usuarios SET password_hash=?, intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?")
           ->execute([$hash, $id]);

        // Invalidar todas sus sesiones
        $db->prepare("DELETE FROM user_sessions WHERE usuario_id=?")->execute([$id]);

        // Enviar email con la nueva contraseña temporal
        $emailEnviado = false;
        if (getenv('MAIL_USER') && $user['email']) {
            require_once __DIR__ . '/../../src/Helpers/Mailer.php';
            $emailEnviado = Mailer::enviarPasswordReseteada($user['email'], $user['nombre'] ?? $user['email'], $tempPwd);
        }

        AuditLog::log("Admin reseteó contraseña del usuario #$id", 'usuarios', $id);

        Response::ok([
            'password_temporal' => $tempPwd, // el admin la ve en pantalla
            'email_enviado'     => $emailEnviado,
            'mensaje'           => 'Contraseña reseteada. ' . ($emailEnviado ? 'Se envió al correo del usuario.' : 'Entrégala manualmente.'),
        ], 'Contraseña reseteada');
    }

    // GET /admin/usuarios/{id}/detalle — admin ve info completa del usuario
    public static function detalle(int $id): void {
        Auth::require(['admin']);
        $db   = DB::get();
        $stmt = $db->prepare("SELECT u.id, u.email, u.estado, u.oauth_provider,
            u.intentos_fallidos, u.bloqueado_hasta, u.ultimo_acceso, u.created_at,
            r.nombre as rol,
            c.nombre as nombre_cliente, c.telefono, c.ci, c.direccion,
            g.nombre as nombre_groomer, g.especialidad, g.turno,
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

        // Últimas acciones del audit log
        $logs = $db->prepare("SELECT accion, ip_address, created_at FROM audit_log WHERE usuario_id=? ORDER BY id DESC LIMIT 10");
        $logs->execute([$id]);
        $user['ultimas_acciones'] = $logs->fetchAll();

        Response::ok($user);
    }
}
