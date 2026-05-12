<?php
// src/Middleware/RateLimiter.php

class RateLimiter {
    /**
     * Limita peticiones usando un archivo de estado por IP.
     * Para producción se recomienda Redis; esto es suficiente para empezar.
     * 
     * @param int $maxRequests  peticiones permitidas
     * @param int $windowSeconds ventana de tiempo en segundos
     */
    public static function check(int $maxRequests = 100, int $windowSeconds = 60): void {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key     = md5($ip);
        $dir     = sys_get_temp_dir() . '/petspa_rl/';
        $file    = $dir . $key . '.json';

        if (!is_dir($dir)) mkdir($dir, 0700, true);

        $now  = time();
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : ['count' => 0, 'window_start' => $now];

        // Reiniciar ventana si expiró
        if ($now - $data['window_start'] >= $windowSeconds) {
            $data = ['count' => 0, 'window_start' => $now];
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);

        if ($data['count'] > $maxRequests) {
            $retry = $windowSeconds - ($now - $data['window_start']);
            header("Retry-After: $retry");
            Response::error("Demasiadas peticiones. Intenta en $retry segundos.", 429);
        }
    }

    /** Rate limiter más estricto para endpoints de autenticación (10 req/min) */
    public static function auth(): void {
        self::check(10, 60);
    }
}
