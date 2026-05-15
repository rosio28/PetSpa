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

            $autoload = self::findAutoload();
            if ($autoload && getenv('MAIL_USER')) {
                require_once $autoload;
                require_once __DIR__ . '/../Helpers/Mailer.php';
                try {
                    $emailEnviado = Mailer::enviarActivacion($email, $nombre ?: $email, $token);
                } catch (Throwable $e) {
                    $emailError = $e->getMessage();
                    error_log('Email error en register: ' . $emailError);
                }
            } else {
                $emailError = !$autoload ? 'vendor/autoload.php no encontrado' : 'MAIL_USER no configurado';
            }

            Response::ok([
                'mensaje'             => $emailEnviado
                    ? "¡Cuenta creada! Revisa tu correo $email para activarla."
                    : "Cuenta creada. No se pudo enviar el email. Usa el link de desarrollo.",
                'email_enviado'       => $emailEnviado,
                'email'               => $email,
                'activation_link_dev' => $activationLink,
            ], 'Registro exitoso');

        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al registrar: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================
    // VERIFY EMAIL
    // =========================================================
    public static function verify(): void {
        $token = Sanitizer::string($_GET['token'] ?? '');
        if (!$token) Response::error('Token requerido');

        $db   = DB::get();
        $stmt = $db->prepare("SELECT id FROM usuarios
            WHERE token_verificacion = ? AND token_expiracion > NOW() AND estado = FALSE");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) Response::error('Token inválido o expirado', 410);

        $db->prepare("UPDATE usuarios SET estado = TRUE, token_verificacion = NULL, token_expiracion = NULL WHERE id = ?")
           ->execute([$user['id']]);

        AuditLog::log('Verificación de email', 'usuarios', (int)$user['id']);
        Response::ok(null, 'Cuenta activada correctamente');
    }

    // =========================================================
    // RESEND VERIFICATION
    // =========================================================
    public static function resendVerification(): void {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = Sanitizer::email($body['email'] ?? '');

        if (!$email) { Response::ok(null, 'Si el correo existe, recibirás el link.'); return; }

        $db   = DB::get();
        $stmt = $db->prepare("SELECT id, email FROM usuarios WHERE email = ? AND estado = FALSE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $exp   = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $db->prepare("UPDATE usuarios SET token_verificacion=?, token_expiracion=? WHERE id=?")
               ->execute([$token, $exp, $user['id']]);

            $link = (getenv('APP_URL') ?: 'http://localhost:8080') . '/verify.html?token=' . $token;
            error_log("RESEND ACTIVATION LINK for $email: $link");

            $emailEnviado = false;
            $autoload = self::findAutoload();
            if ($autoload && getenv('MAIL_USER')) {
                require_once $autoload;
                require_once __DIR__ . '/../Helpers/Mailer.php';
                try { $emailEnviado = Mailer::enviarActivacion($email, $email, $token); } catch (Throwable $e) {}
            }

            if (!$emailEnviado) {
                Response::ok(['activation_link_dev' => $link], 'Link generado (email no enviado)');
                return;
            }
        }

        Response::ok(null, 'Si el correo existe y no está activado, recibirás un nuevo link.');
    }

    // =========================================================
    // LOGIN
    // =========================================================
    public static function login(): void {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = Sanitizer::email($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $totpCode = Sanitizer::string($body['totp_code'] ?? '');

        if (!$email || !$password) Response::error('Email y contraseña requeridos');

        $db   = DB::get();
        $stmt = $db->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u
            JOIN roles r ON r.id = u.rol_id WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) Response::error('Credenciales inválidas', 401);

        // Verificar bloqueo por intentos fallidos
        if ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
            $resta = ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
            Response::error("Cuenta bloqueada temporalmente. Intenta en $resta minuto(s).", 429);
        }

        if (!$user['estado']) Response::error('Cuenta no activada. Revisa tu email.', 401);

        if (!$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            // Incrementar intentos fallidos
            $intentos = (int)$user['intentos_fallidos'] + 1;
            $bloqueo  = $intentos >= 5 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
            $db->prepare("UPDATE usuarios SET intentos_fallidos=?, bloqueado_hasta=? WHERE id=?")
               ->execute([$intentos, $bloqueo, $user['id']]);
            Response::error('Credenciales inválidas', 401);
        }

        // Verificar 2FA si está habilitado
        if ($user['two_factor_enabled']) {
            if (!$totpCode) {
                Response::json(['success' => false, 'require_2fa' => true, 'message' => 'Se requiere código 2FA']);
            }
            if (!self::verifyTOTP($user['two_factor_secret'], $totpCode)) {
                Response::error('Código 2FA inválido', 401);
            }
        }

        // Reset intentos y actualizar último acceso
        $db->prepare("UPDATE usuarios SET intentos_fallidos=0, bloqueado_hasta=NULL, ultimo_acceso=NOW() WHERE id=?")
           ->execute([$user['id']]);

        $payload = ['user_id' => (int)$user['id'], 'email' => $user['email'], 'rol' => $user['rol_nombre']];
        $jwt     = JWT::generate($payload, 8);
        $refresh = bin2hex(random_bytes(32));
        $exp     = date('Y-m-d H:i:s', strtotime('+8 hours'));

        // Limpiar sesiones viejas del mismo usuario (máx 5 sesiones activas)
        $db->prepare("DELETE FROM user_sessions WHERE usuario_id=? AND expires_at < NOW()")->execute([$user['id']]);

        $db->prepare("INSERT INTO user_sessions (usuario_id,jwt_token,refresh_token,ip_address,user_agent,expires_at)
            VALUES (?,?,?,?,?,?)")->execute([
            (int)$user['id'], $jwt, $refresh,
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $exp
        ]);

        AuditLog::log('Login exitoso', 'usuarios', (int)$user['id']);
        Response::ok([
            'token'        => $jwt,
            'refresh_token'=> $refresh,
            'expires_in'   => 28800,
            'usuario'      => ['id' => (int)$user['id'], 'email' => $user['email'], 'rol' => $user['rol_nombre']],
        ]);
    }

    // =========================================================
    // REFRESH TOKEN
    // =========================================================
    public static function refresh(): void {
        $body         = json_decode(file_get_contents('php://input'), true) ?? [];
        $refreshToken = $body['refresh_token'] ?? '';

        if (!$refreshToken) Response::error('refresh_token requerido', 401);

        $db   = DB::get();
        $stmt = $db->prepare("SELECT s.*, u.email, u.estado, r.nombre as rol_nombre
            FROM user_sessions s
            JOIN usuarios u ON u.id = s.usuario_id
            JOIN roles r ON r.id = u.rol_id
            WHERE s.refresh_token = ? AND s.expires_at > NOW()");
        $stmt->execute([$refreshToken]);
        $session = $stmt->fetch();

        if (!$session) Response::error('Refresh token inválido o expirado', 401);
        if (!$session['estado']) Response::error('Cuenta inactiva', 401);

        $payload    = ['user_id' => (int)$session['usuario_id'], 'email' => $session['email'], 'rol' => $session['rol_nombre']];
        $newToken   = JWT::generate($payload, 8);
        $newRefresh = bin2hex(random_bytes(32));
        $exp        = date('Y-m-d H:i:s', strtotime('+8 hours'));

        $db->prepare("UPDATE user_sessions SET jwt_token=?, refresh_token=?, expires_at=? WHERE id=?")
           ->execute([$newToken, $newRefresh, $exp, $session['id']]);

        Response::ok(['token' => $newToken, 'refresh_token' => $newRefresh, 'expires_in' => 28800]);
    }

    // =========================================================
    // LOGOUT
    // =========================================================
    public static function logout(): void {
        // Obtener token del header
        $header = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strtolower($k) === 'authorization') { $header = $v; break; }
            }
        }

        $db = DB::get();

        if (str_starts_with(trim($header), 'Bearer ')) {
            $token = trim(substr(trim($header), 7));
            $db->prepare("DELETE FROM user_sessions WHERE jwt_token = ?")->execute([$token]);
        }

        // También por refresh_token si viene en body
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($body['refresh_token'])) {
            $db->prepare("DELETE FROM user_sessions WHERE refresh_token = ?")->execute([$body['refresh_token']]);
        }

        Response::ok(null, 'Sesión cerrada correctamente');
    }

    // =========================================================
    // FORGOT PASSWORD
    // =========================================================
    public static function forgotPassword(): void {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = Sanitizer::email($body['email'] ?? '');

        if (!$email) { Response::ok(null, 'Si el correo existe, recibirás instrucciones.'); return; }

        $db   = DB::get();
        $stmt = $db->prepare("SELECT id, email FROM usuarios WHERE email = ? AND estado = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $exp   = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $db->prepare("UPDATE usuarios SET token_recuperacion=?, token_rec_expiracion=? WHERE id=?")
               ->execute([$token, $exp, $user['id']]);

            AuditLog::log("Solicitud recuperación contraseña: $email", 'usuarios', (int)$user['id']);

            $resetLink    = (getenv('APP_URL') ?: 'http://localhost:8080') . '/reset-password.html?token=' . $token;
            $emailEnviado = false;

            $autoload = self::findAutoload();
            if ($autoload && getenv('MAIL_USER')) {
                require_once $autoload;
                require_once __DIR__ . '/../Helpers/Mailer.php';
                try { $emailEnviado = Mailer::enviarRecuperacion($email, $token); } catch (Throwable $e) {
                    error_log('Email recuperacion error: ' . $e->getMessage());
                }
            }

            error_log("RESET LINK para $email: $resetLink");

            if (!$emailEnviado) {
                Response::ok(['reset_link_dev' => $resetLink, 'mensaje' => 'Email no configurado. Usa el link de desarrollo.'],
                    'Instrucciones generadas');
                return;
            }
        }

        Response::ok(null, 'Si el correo existe, recibirás instrucciones en breve.');
    }

    // =========================================================
    // RESET PASSWORD
    // =========================================================
    public static function resetPassword(): void {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $token    = Sanitizer::string($body['token'] ?? '');
        $password = $body['password'] ?? '';

        if (!$token) Response::error('Token requerido');

        $pwErrors = Sanitizer::password($password);
        if ($pwErrors) Response::error('Contraseña no válida', 422, $pwErrors);

        $db   = DB::get();
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE token_recuperacion=? AND token_rec_expiracion>NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) Response::error('Token inválido o expirado. Solicita uno nuevo.', 410);

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE usuarios SET password_hash=?, token_recuperacion=NULL, token_rec_expiracion=NULL,
            intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?")->execute([$hash, $user['id']]);

        // Invalidar TODAS las sesiones activas por seguridad
        $db->prepare("DELETE FROM user_sessions WHERE usuario_id=?")->execute([$user['id']]);

        AuditLog::log('Contraseña restablecida via token', 'usuarios', (int)$user['id']);
        Response::ok(null, 'Contraseña actualizada correctamente');
    }

    // =========================================================
    // FORGOT PASSWORD VIA WHATSAPP
    // =========================================================
    public static function forgotPasswordWhatsapp(): void {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $telefono = Sanitizer::string($body['telefono'] ?? '');

        if (!$telefono) Response::error('Teléfono requerido');

        $tel = preg_replace('/\D/', '', $telefono);

        $db   = DB::get();
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
                . "🔑 Haz clic en este link:\n$link\n\n"
                . "⏰ *Expira en 15 minutos.*\n\n"
                . "Si no solicitaste esto, ignora este mensaje."
            );

            $telCompleto = '591' . ltrim($tel, '0');
            $waLink = "https://wa.me/$telCompleto?text=$mensaje";

            Response::ok(['whatsapp_link' => $waLink, 'tel_formateado' => $telCompleto]);
        } else {
            Response::ok(['mensaje' => 'Si el teléfono está registrado, podrás enviar el link por WhatsApp.']);
        }
    }

    // =========================================================
    // GOOGLE OAuth
    // =========================================================
    public static function googleRedirect(): void {
        $clientId    = getenv('GOOGLE_CLIENT_ID');
        $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: (getenv('APP_URL') . '/auth/google/callback');

        if (!$clientId) Response::error('GOOGLE_CLIENT_ID no configurado', 500);

        $params = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'offline',
            'prompt'        => 'select_account',
        ]);

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;
    }

    public static function googleCallback(): void {
        $code = $_GET['code'] ?? '';
        if (!$code) {
            header('Location: ' . (getenv('APP_URL') ?: 'http://localhost:8080') . '/login.html?google_error=cancelado');
            exit;
        }

        $clientId     = getenv('GOOGLE_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
        $redirectUri  = getenv('GOOGLE_REDIRECT_URI') ?: (getenv('APP_URL') . '/auth/google/callback');

        $tokenData = self::httpPost('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $clientId, 'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
        ]);

        if (!$tokenData || !isset($tokenData['access_token'])) {
            header('Location: ' . (getenv('APP_URL') ?: 'http://localhost:8080') . '/login.html?google_error=token');
            exit;
        }

        $googleUser = self::httpGet('https://www.googleapis.com/oauth2/v3/userinfo', $tokenData['access_token']);
        if (!$googleUser || !isset($googleUser['email'])) {
            header('Location: ' . (getenv('APP_URL') ?: 'http://localhost:8080') . '/login.html?google_error=userinfo');
            exit;
        }

        $email    = strtolower(trim($googleUser['email']));
        $nombre   = $googleUser['name'] ?? $email;
        $googleId = $googleUser['sub'] ?? '';

        $db   = DB::get();
        $stmt = $db->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $rolStmt = $db->prepare("SELECT id FROM roles WHERE nombre='cliente'");
            $rolStmt->execute();
            $rolId = $rolStmt->fetchColumn();

            $db->beginTransaction();
            try {
                $ins = $db->prepare("INSERT INTO usuarios (email,rol_id,estado,oauth_provider,oauth_id) VALUES (?,?,TRUE,'google',?) RETURNING id");
                $ins->execute([$email, $rolId, $googleId]);
                $userId = $ins->fetchColumn();

                $db->prepare("INSERT INTO clientes (usuario_id,nombre) VALUES (?,?)")->execute([$userId, $nombre]);
                $db->commit();

                $reload = $db->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=?");
                $reload->execute([$userId]);
                $user = $reload->fetch();
            } catch (Throwable $e) {
                $db->rollBack();
                header('Location: ' . (getenv('APP_URL') ?: 'http://localhost:8080') . '/login.html?google_error=registro');
                exit;
            }
        }

        $payload = ['user_id' => (int)$user['id'], 'email' => $user['email'], 'rol' => $user['rol_nombre']];
        $jwt     = JWT::generate($payload, 8);
        $refresh = bin2hex(random_bytes(32));
        $exp     = date('Y-m-d H:i:s', strtotime('+8 hours'));

        $db->prepare("INSERT INTO user_sessions (usuario_id,jwt_token,refresh_token,ip_address,user_agent,expires_at) VALUES (?,?,?,?,?,?)")
           ->execute([(int)$user['id'], $jwt, $refresh, $_SERVER['REMOTE_ADDR']??'', $_SERVER['HTTP_USER_AGENT']??'', $exp]);

        $appUrl   = getenv('APP_URL') ?: 'http://localhost:8080';
        $userData = urlencode(json_encode(['id' => (int)$user['id'], 'email' => $user['email'], 'rol' => $user['rol_nombre']]));

        header('Location: ' . $appUrl . '/oauth-callback.html?token=' . urlencode($jwt) . '&refresh=' . urlencode($refresh) . '&user=' . $userData);
        exit;
    }

    // =========================================================
    // 2FA SETUP
    // =========================================================
    public static function setup2FA(): void {
        $user = Auth::require();

        // Generar secret Base32 compatible con Google Authenticator
        $secret = self::generateBase32Secret(20);

        $db = DB::get();
        $db->prepare("UPDATE usuarios SET two_factor_secret=? WHERE id=?")->execute([$secret, $user['user_id']]);

        $email   = $user['email'];
        $issuer  = urlencode('PetSpa');
        $otpauth = "otpauth://totp/$issuer:" . urlencode($email) . "?secret=$secret&issuer=$issuer&algorithm=SHA1&digits=6&period=30";

        Response::ok([
            'secret'      => $secret,
            'otpauth_uri' => $otpauth,
            'mensaje'      => 'Escanea el QR con Google Authenticator o Authy',
        ], '2FA configurado. Verifica con tu app.');
    }

    // =========================================================
    // 2FA CONFIRM
    // =========================================================
    public static function confirm2FA(): void {
        $user = Auth::require();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $code = Sanitizer::string($body['code'] ?? '');

        if (!$code || !preg_match('/^\d{6}$/', $code)) Response::error('Código de 6 dígitos requerido');

        $db   = DB::get();
        $stmt = $db->prepare("SELECT two_factor_secret FROM usuarios WHERE id=?");
        $stmt->execute([$user['user_id']]);
        $row  = $stmt->fetch();

        if (!$row || !$row['two_factor_secret']) Response::error('Primero configura 2FA con /auth/2fa/setup');

        if (!self::verifyTOTP($row['two_factor_secret'], $code)) {
            Response::error('Código inválido. Verifica la hora de tu dispositivo.', 401);
        }

        $db->prepare("UPDATE usuarios SET two_factor_enabled=TRUE WHERE id=?")->execute([$user['user_id']]);
        Response::ok(null, '2FA activado correctamente');
    }

    // =========================================================
    // HELPERS PRIVADOS
    // =========================================================
    private static function findAutoload(): string|false {
        $paths = [
            '/var/www/html/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
        ];
        foreach ($paths as $p) { if (file_exists($p)) return $p; }
        return false;
    }

    private static function httpPost(string $url, array $data): ?array {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 15,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res ? json_decode($res, true) : null;
    }

    private static function httpGet(string $url, string $accessToken): ?array {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $accessToken\r\n",
            'timeout' => 15,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res ? json_decode($res, true) : null;
    }

    /**
     * Genera secret Base32 compatible con Google Authenticator
     */
    private static function generateBase32Secret(int $bytes = 20): string {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $random = random_bytes($bytes);
        $secret = '';
        $n      = strlen($random);
        $bits   = '';
        for ($i = 0; $i < $n; $i++) { $bits .= str_pad(decbin(ord($random[$i])), 8, '0', STR_PAD_LEFT); }
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $secret .= $chars[bindec($chunk)];
        }
        return $secret;
    }

    /**
     * Verifica código TOTP (RFC 6238) — compatible con Google Authenticator
     * Acepta ±1 período de 30s para tolerancia de reloj
     */
    private static function verifyTOTP(string $secret, string $code): bool {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        // Decodificar Base32
        $secret = strtoupper(str_replace(' ', '', $secret));
        $bits   = '';
        foreach (str_split($secret) as $c) {
            $pos  = strpos($chars, $c);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $b) {
            if (strlen($b) === 8) $key .= chr(bindec($b));
        }

        $time = (int)floor(time() / 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            $t    = $time + $offset;
            $msg  = pack('N*', 0) . pack('N*', $t);
            $hash = hash_hmac('sha1', $msg, $key, true);
            $ofs  = ord($hash[19]) & 0x0F;
            $otp  = (
                ((ord($hash[$ofs])   & 0x7F) << 24)
                | ((ord($hash[$ofs+1]) & 0xFF) << 16)
                | ((ord($hash[$ofs+2]) & 0xFF) << 8)
                |  (ord($hash[$ofs+3]) & 0xFF)
            ) % 1000000;
            if (str_pad((string)$otp, 6, '0', STR_PAD_LEFT) === $code) return true;
        }
        return false;
    }
}
