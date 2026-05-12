<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if (!getenv($k)) { putenv("$k=$v"); $_ENV[$k] = $v; }
        }
    }
}
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '5432');
define('DB_NAME',    getenv('DB_NAME')    ?: 'petspa');
define('DB_USER',    getenv('DB_USER')    ?: 'petspa_user');
define('DB_PASS',    getenv('DB_PASS')    ?: '12345678');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CAMBIA_ESTO_32_CHARS_MINIMO_XYZ_ABC');
define('APP_URL',    getenv('APP_URL')    ?: 'http://localhost:8080');

class DB {
    private static ?PDO $conn = null;
    public static function get(): PDO {
        if (self::$conn === null) {
            $dsn = "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME;
            try {
                self::$conn = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
            }
        }
        return self::$conn;
    }
}
