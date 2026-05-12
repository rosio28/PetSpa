<?php
// src/Controllers/AuthController.php

class AuthController {

    // =========================================================
    // REGISTER
    // =========================================================
    public static function register(): void {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $email    = Sanitizer::email($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $nombre   = Sanitizer::string($body['nombre'] ?? '');
    $telefono = Sanitizer::string($body['telefono'] ?? '');
    $ci       = Sanitizer::string($body['ci'] ?? '');
    $direccion= Sanitizer::string($body['direccion'] ?? '');

    if (!$email) Response::error('Email inválido');

    $pwErrors = Sanitizer::password($password);
    if ($pwErrors) Response::error('Contraseña no cumple requisitos', 422, $pwErrors);

    $db = DB::get();
    $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) Response::error('El correo ya está registrado', 409);

    $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $token = bin2hex(random_bytes(32));
    $exp   = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $rolStmt = $db->prepare("SELECT id FROM roles WHERE nombre = 'cliente'");
    $rolStmt->execute();
    $rolId = $rolStmt->fetchColumn();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO usuarios
            (email, password_hash, rol_id, estado, token_verificacion, token_expiracion)
            VALUES (?, ?, ?, FALSE, ?, ?) RETURNING id");
        $stmt->execute([$email, $hash, $rolId, $token, $exp]);
        $userId = $stmt->fetchColumn();

        $db->prepare("INSERT INTO clientes (usuario_id, nombre, telefono, ci, direccion) VALUES (?,?,?,?,?)")
           ->execute([$userId, $nombre ?: $email, $telefono, $ci, $direccion]);

        $db->commit();
        AuditLog::log("Registro de cliente: $email", 'usuarios', (int)$userId);

        $activationLink = (getenv('APP_URL') ?: 'http://localhost:8080') . '/verify.html?token=' . $token;
        $emailEnviado   = false;
        $emailError     = '';

        // Cargar PHPMailer y enviar
        $autoload = '/var/www/html/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        }

        if (file_exists($autoload) && getenv('MAIL_USER')) {
            require_once $autoload;
            require_once __DIR__ . '/../Helpers/Mailer.php';
            try {
                $emailEnviado = Mailer::enviarActivacion($email, $nombre ?: $email, $token);
            } catch (Throwable $e) {
                $emailError = $e->getMessage();
                error_log('Email error en register: ' . $emailError);
            }
        } else {
            $emailError = !file_exists($autoload)
                ? 'vendor/autoload.php no encontrado'
                : 'MAIL_USER no configurado';
            error_log('Email no enviado: ' . $emailError);
        }

        Response::ok([
            'mensaje'             => $emailEnviado
                ? "¡Cuenta creada! Revisa tu correo $email para activarla. El link expira en 15 minutos."
                : "Cuenta creada. No se pudo enviar el email ($emailError). Usa el link de desarrollo.",
            'email_enviado'       => $emailEnviado,
            'fuerza_password'     => Sanitizer::passwordStrength($password),
            'activation_link_dev' => $activationLink,
        ], 'Registro exitoso');

    } catch (Throwable $e) {
        $db->rollBack();
        Response::error('Error al registrar: ' . $e->getMessage(), 500);
    }
}

    // =========================================================
    // VERIFY
    // =========================================================
    public static function verify(): void {

        $token = Sanitizer::string($_GET['token'] ?? '');

        if (!$token) {
            Response::error('Token requerido');
        }

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id
            FROM usuarios
            WHERE token_verificacion = ?
            AND token_expiracion > NOW()
            AND estado = FALSE
        ");

        $stmt->execute([$token]);

        $user = $stmt->fetch();

        if (!$user) {
            Response::error(
                'Token inválido o expirado',
                410
            );
        }

        $db->prepare("
            UPDATE usuarios
            SET
                estado = TRUE,
                token_verificacion = NULL,
                token_expiracion = NULL
            WHERE id = ?
        ")->execute([$user['id']]);

        AuditLog::log(
            'Verificación de email',
            'usuarios',
            (int)$user['id']
        );

        Response::ok(
            null,
            'Cuenta activada correctamente'
        );
    }

    // =========================================================
    // LOGIN
    // =========================================================
    public static function login(): void {

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $email    = Sanitizer::email($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            Response::error(
                'Email y contraseña requeridos'
            );
        }

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                u.*,
                r.nombre as rol_nombre
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            WHERE u.email = ?
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if (!$user) {
            Response::error(
                'Credenciales inválidas',
                401
            );
        }

        if (!$user['estado']) {
            Response::error(
                'Cuenta no activada',
                401
            );
        }

        if (
            !$user['password_hash']
            || !password_verify(
                $password,
                $user['password_hash']
            )
        ) {

            Response::error(
                'Credenciales inválidas',
                401
            );
        }

        // reset intentos
        $db->prepare("
            UPDATE usuarios
            SET
                intentos_fallidos = 0,
                bloqueado_hasta = NULL,
                ultimo_acceso = NOW()
            WHERE id = ?
        ")->execute([$user['id']]);

        // jwt
        $payload = [
            'user_id' => (int)$user['id'],
            'email'   => $user['email'],
            'rol'     => $user['rol_nombre']
        ];

        $jwt = JWT::generate($payload, 8);

        $refresh = bin2hex(random_bytes(32));

        $exp = date(
            'Y-m-d H:i:s',
            strtotime('+8 hours')
        );

        // guardar sesión
        $db->prepare("
            INSERT INTO user_sessions (
                usuario_id,
                jwt_token,
                refresh_token,
                ip_address,
                user_agent,
                expires_at
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            (int)$user['id'],
            $jwt,
            $refresh,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $exp
        ]);

        AuditLog::log(
            'Login exitoso',
            'usuarios',
            (int)$user['id']
        );

        Response::ok([
            'token' => $jwt,
            'refresh_token' => $refresh,
            'expires_in' => 28800,
            'usuario' => [
                'id'    => (int)$user['id'],
                'email' => $user['email'],
                'rol'   => $user['rol_nombre']
            ]
        ]);
    }

    // =========================================================
    // GOOGLE REDIRECT
    // =========================================================
    public static function googleRedirect(): void {

        $clientId = getenv('GOOGLE_CLIENT_ID');

        $redirectUri =
            getenv('GOOGLE_REDIRECT_URI')
            ?: (
                getenv('APP_URL')
                . '/auth/google/callback'
            );

        if (!$clientId) {
            Response::error(
                'GOOGLE_CLIENT_ID no configurado',
                500
            );
        }

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'select_account'
        ]);

        header(
            'Location: https://accounts.google.com/o/oauth2/v2/auth?'
            . $params
        );

        exit;
    }

    // =========================================================
    // GOOGLE CALLBACK
    // =========================================================
    public static function googleCallback(): void {

        $code = $_GET['code'] ?? '';

        if (!$code) {
            Response::error('Código OAuth inválido');
        }

        $clientId     = getenv('GOOGLE_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET');

        $redirectUri =
            getenv('GOOGLE_REDIRECT_URI')
            ?: (
                getenv('APP_URL')
                . '/auth/google/callback'
            );

        // token
        $tokenData = self::httpPost(
            'https://oauth2.googleapis.com/token',
            [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ]
        );

        if (
            !$tokenData
            || !isset($tokenData['access_token'])
        ) {
            Response::error(
                'Error obteniendo token Google',
                500
            );
        }

        // user info
        $googleUser = self::httpGet(
            'https://www.googleapis.com/oauth2/v3/userinfo',
            $tokenData['access_token']
        );

        if (
            !$googleUser
            || !isset($googleUser['email'])
        ) {
            Response::error(
                'Error obteniendo usuario Google',
                500
            );
        }

        $email = strtolower(trim($googleUser['email']));
        $nombre = $googleUser['name'] ?? $email;
        $googleId = $googleUser['sub'] ?? '';

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                u.*,
                r.nombre as rol_nombre
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            WHERE u.email = ?
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        // crear usuario si no existe
        if (!$user) {

            $rolStmt = $db->prepare("
                SELECT id
                FROM roles
                WHERE nombre='cliente'
            ");

            $rolStmt->execute();

            $rolId = $rolStmt->fetchColumn();

            $db->beginTransaction();

            try {

                $stmtInsert = $db->prepare("
                    INSERT INTO usuarios (
                        email,
                        rol_id,
                        estado,
                        oauth_provider,
                        oauth_id
                    )
                    VALUES (?, ?, TRUE, 'google', ?)
                    RETURNING id
                ");

                $stmtInsert->execute([
                    $email,
                    $rolId,
                    $googleId
                ]);

                $userId = $stmtInsert->fetchColumn();

                $db->prepare("
                    INSERT INTO clientes (
                        usuario_id,
                        nombre
                    )
                    VALUES (?, ?)
                ")->execute([
                    $userId,
                    $nombre
                ]);

                $db->commit();

                $stmtReload = $db->prepare("
                    SELECT
                        u.*,
                        r.nombre as rol_nombre
                    FROM usuarios u
                    JOIN roles r ON r.id = u.rol_id
                    WHERE u.id = ?
                ");

                $stmtReload->execute([$userId]);

                $user = $stmtReload->fetch();

            } catch (Throwable $e) {

                $db->rollBack();

                Response::error(
                    'Error creando usuario Google: '
                    . $e->getMessage(),
                    500
                );
            }
        }

        // JWT
        $payload = [
            'user_id' => (int)$user['id'],
            'email'   => $user['email'],
            'rol'     => $user['rol_nombre']
        ];

        $jwt = JWT::generate($payload, 8);

        $refresh = bin2hex(random_bytes(32));

        $exp = date(
            'Y-m-d H:i:s',
            strtotime('+8 hours')
        );

        // guardar sesión
        $db->prepare("
            INSERT INTO user_sessions (
                usuario_id,
                jwt_token,
                refresh_token,
                ip_address,
                user_agent,
                expires_at
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            (int)$user['id'],
            $jwt,
            $refresh,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $exp
        ]);

        // redirigir frontend
        $appUrl =
            getenv('APP_URL')
            ?: 'http://localhost:8080';

        $userData = urlencode(json_encode([
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'rol' => $user['rol_nombre']
        ]));

        header(
            'Location: '
            . $appUrl
            . '/oauth-callback.html'
            . '?token=' . urlencode($jwt)
            . '&refresh=' . urlencode($refresh)
            . '&user=' . $userData
        );

        exit;
    }

    public static function forgotPassword(): void {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = Sanitizer::email($body['email'] ?? '');

    if (!$email) {
        Response::ok(null, 'Si el correo existe, recibirás instrucciones.');
        return;
    }

    $db   = DB::get();
    $stmt = $db->prepare("SELECT id, email FROM usuarios WHERE email = ? AND estado = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $exp   = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $db->prepare("UPDATE usuarios SET token_recuperacion = ?, token_rec_expiracion = ? WHERE id = ?")
           ->execute([$token, $exp, $user['id']]);

        AuditLog::log("Solicitud recuperación contraseña: $email", 'usuarios', (int)$user['id']);

        $emailEnviado = false;
        $resetLink    = (getenv('APP_URL') ?: 'http://localhost:8080') . '/reset-password.html?token=' . $token;

        $autoload = '/var/www/html/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        }

        if (file_exists($autoload) && getenv('MAIL_USER')) {
            require_once $autoload;
            require_once __DIR__ . '/../Helpers/Mailer.php';
            try {
                $emailEnviado = Mailer::enviarRecuperacion($email, $token);
            } catch (Throwable $e) {
                error_log('Email recuperacion error: ' . $e->getMessage());
            }
        }

        // En desarrollo siempre loguear el link
        error_log("RESET LINK para $email: $resetLink");

        if (!$emailEnviado) {
            // Si no hay email configurado, devolver el link en la respuesta (solo desarrollo)
            Response::ok([
                'reset_link_dev' => $resetLink,
                'mensaje'        => 'Email no configurado. Usa el link de desarrollo.'
            ], 'Instrucciones generadas');
            return;
        }
    }

    Response::ok(null, 'Si el correo existe, recibirás instrucciones en breve.');
}


// POST /auth/forgot-password-whatsapp
public static function forgotPasswordWhatsapp(): void {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $telefono = Sanitizer::string($body['telefono'] ?? '');

    if (!$telefono) Response::error('Teléfono requerido');

    // Limpiar el número — solo dígitos
    $tel = preg_replace('/\D/', '', $telefono);

    $db   = DB::get();
    // Buscar cliente por teléfono
    $stmt = $db->prepare("SELECT u.id, u.email, c.nombre FROM usuarios u
        JOIN clientes c ON c.usuario_id = u.id
        WHERE c.telefono LIKE ? AND u.estado = TRUE LIMIT 1");
    $stmt->execute(['%' . $tel . '%']);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $exp   = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $db->prepare("UPDATE usuarios SET token_recuperacion=?, token_rec_expiracion=? WHERE id=?")
           ->execute([$token, $exp, $user['id']]);

        AuditLog::log("Recuperación vía WhatsApp solicitada", 'usuarios', (int)$user['id']);

        $appUrl  = getenv('APP_URL') ?: 'http://localhost:8080';
        $link    = $appUrl . '/reset-password.html?token=' . $token;
        $nombre  = $user['nombre'] ?? 'Usuario';

        $mensaje = urlencode(
            "🐾 *Pet Spa* — Recuperación de contraseña\n\n"
            . "Hola *$nombre*, recibimos una solicitud para cambiar tu contraseña.\n\n"
            . "🔑 Haz clic en este link para crear una nueva contraseña:\n"
            . "$link\n\n"
            . "⏰ *Expira en 15 minutos.*\n\n"
            . "Si no solicitaste esto, ignora este mensaje."
        );

        // Número destino — agregar código de país Bolivia (591)
        $telCompleto = '591' . ltrim($tel, '0');

        $waLink = "https://wa.me/$telCompleto?text=$mensaje";

        Response::ok([
            'whatsapp_link' => $waLink,
            'tel_formateado' => $telCompleto,
            'mensaje'        => 'Haz clic en el link para enviar el mensaje de recuperación por WhatsApp.',
        ]);
    } else {
        // Respuesta genérica
        Response::ok([
            'mensaje' => 'Si el teléfono está registrado, podrás enviar el link por WhatsApp.'
        ]);
    }
}
    // =========================================================
    // HELPERS
    // =========================================================
    private static function httpPost(
        string $url,
        array $data
    ): ?array {

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'timeout' => 15
            ]
        ]);

        $res = @file_get_contents(
            $url,
            false,
            $ctx
        );

        return $res
            ? json_decode($res, true)
            : null;
    }

    private static function httpGet(
        string $url,
        string $accessToken
    ): ?array {

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' =>
                    "Authorization: Bearer $accessToken\r\n",
                'timeout' => 15
            ]
        ]);

        $res = @file_get_contents(
            $url,
            false,
            $ctx
        );

        return $res
            ? json_decode($res, true)
            : null;
    }
}