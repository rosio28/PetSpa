<?php
// src/Helpers/JWT.php

class JWT {

    public static function generate(array $payload, int $expHours = 8): string {
        $header = self::base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + ($expHours * 3600);
        $body = self::base64url_encode(json_encode($payload));
        $sig  = self::sign("$header.$body");
        return "$header.$body.$sig";
    }

    public static function verify(string $token): ?array {
        $token = trim($token);
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            error_log('JWT: no tiene 3 partes, tiene ' . count($parts));
            return null;
        }

        [$header, $body, $sig] = $parts;

        // Verificar firma
        $expected = self::sign("$header.$body");
        if (!hash_equals($expected, $sig)) {
            error_log('JWT: firma inválida');
            return null;
        }

        // Decodificar payload
        $decoded = json_decode(self::base64url_decode($body), true);
        if (!$decoded) {
            error_log('JWT: payload no decodificable');
            return null;
        }

        // Verificar expiración
        if (!isset($decoded['exp']) || $decoded['exp'] < time()) {
            error_log('JWT: expirado. exp=' . ($decoded['exp'] ?? 'null') . ' now=' . time());
            return null;
        }

        return $decoded;
    }

    private static function sign(string $data): string {
        $raw = hash_hmac('sha256', $data, JWT_SECRET, true);
        return self::base64url_encode($raw);
    }

    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $data): string {
        $pad  = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }
}