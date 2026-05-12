<?php
// src/Helpers/Response.php

class Response {
    public static function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $data = null, string $message = 'OK'): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $code = 400, array $errors = []): void {
        $payload = ['success' => false, 'message' => $message];
        if ($errors) $payload['errors'] = $errors;
        self::json($payload, $code);
    }
}
