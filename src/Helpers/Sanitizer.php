<?php
// src/Helpers/Sanitizer.php

class Sanitizer {
    /** Limpia string contra XSS e inyecciones */
    public static function string(mixed $val): string {
        return htmlspecialchars(trim((string)$val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function email(mixed $val): string|false {
        $clean = filter_var(trim((string)$val), FILTER_SANITIZE_EMAIL);
        return filter_var($clean, FILTER_VALIDATE_EMAIL) ? strtolower($clean) : false;
    }

    public static function int(mixed $val): int {
        return (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function password(string $password): array {
        $errors = [];
        if (strlen($password) < 8)         $errors[] = 'Mínimo 8 caracteres';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Debe incluir al menos una mayúscula';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Debe incluir al menos una minúscula';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Debe incluir al menos un número';
        if (!preg_match('/[*#!@$%^&]/', $password)) $errors[] = 'Debe incluir al menos un símbolo (*#!@$%^&)';
        return $errors;
    }

    public static function passwordStrength(string $pwd): string {
        $score = 0;
        if (strlen($pwd) >= 8)  $score++;
        if (strlen($pwd) >= 12) $score++;
        if (preg_match('/[A-Z]/', $pwd)) $score++;
        if (preg_match('/[0-9]/', $pwd)) $score++;
        if (preg_match('/[*#!@$%^&]/', $pwd)) $score++;
        return match(true) {
            $score <= 2 => 'débil',
            $score <= 3 => 'media',
            default     => 'fuerte',
        };
    }
}
