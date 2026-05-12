<?php
class Auth {
    private static ?array $currentUser = null;

    public static function require(array $rolesPermitidos = []): array {
        // Obtener Authorization header — múltiples métodos para compatibilidad Apache
        $header = '';

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $allHeaders = apache_request_headers();
            foreach ($allHeaders as $k => $v) {
                if (strtolower($k) === 'authorization') { $header = $v; break; }
            }
        }

        $header = trim($header);

        if (empty($header) || stripos($header, 'Bearer ') !== 0) {
            Response::error('No autenticado', 401);
        }

        $token = trim(substr($header, 7));

        if (empty($token)) {
            Response::error('Token vacío', 401);
        }

        $payload = JWT::verify($token);
        if (!$payload) {
            Response::error('Token inválido o expirado', 401);
        }

        $db   = DB::get();
        $stmt = $db->prepare(
            "SELECT s.id, u.estado, r.nombre as rol
             FROM user_sessions s
             JOIN usuarios u ON u.id = s.usuario_id
             JOIN roles r ON r.id = u.rol_id
             WHERE s.jwt_token = ?
               AND s.expires_at > NOW()"
        );
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            Response::error('Sesión expirada, inicia sesión nuevamente', 401);
        }

        if (!$session['estado']) {
            Response::error('Cuenta inactiva', 401);
        }

        if ($rolesPermitidos && !in_array($session['rol'], $rolesPermitidos)) {
            Response::error('Sin permisos. Se requiere rol: ' . implode(' o ', $rolesPermitidos), 403);
        }

        self::$currentUser = [
            'user_id'    => (int)$payload['user_id'],
            'email'      => $payload['email'],
            'rol'        => $session['rol'],
            'session_id' => $session['id'],
        ];
        AuditLog::setUser((int)$payload['user_id'], $session['rol']);
        return self::$currentUser;
    }

    public static function user(): ?array { return self::$currentUser; }
}

class AuditLog {
    private static int    $userId = 0;
    private static string $rol    = '';

    public static function setUser(int $id, string $rol): void {
        self::$userId = $id;
        self::$rol    = $rol;
    }

    public static function log(string $accion, string $tabla = '', int $registroId = 0,
                               array $anterior = [], array $nuevo = []): void {
        try {
            DB::get()->prepare(
                "INSERT INTO audit_log
                 (usuario_id, rol, ip_address, user_agent, accion, tabla, registro_id, datos_anteriores, datos_nuevos)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                self::$userId ?: null,
                self::$rol    ?: 'sistema',
                $_SERVER['REMOTE_ADDR']     ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $accion,
                $tabla      ?: null,
                $registroId ?: null,
                $anterior   ? json_encode($anterior) : null,
                $nuevo      ? json_encode($nuevo)    : null,
            ]);
        } catch (Throwable $e) {
            error_log('AuditLog: ' . $e->getMessage());
        }
    }
}