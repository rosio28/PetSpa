<?php
// src/Middleware/SessionTimeout.php

/**
 * Verifica inactividad de 30 minutos en el frontend.
 * El backend ya invalida tokens por expiración de JWT (1h) y sesión (8h).
 * Este endpoint permite al cliente verificar si su sesión sigue activa.
 */
class SessionTimeout {

    /** GET /auth/check  — verifica si el token sigue válido */
    public static function check(): void {
        $user = Auth::require();
        // Si llega aquí, el token es válido
        Response::ok(['user_id' => $user['user_id'], 'rol' => $user['rol']], 'Sesión activa');
    }

    /**
     * El frontend debe llamar a /auth/check periódicamente.
     * Si recibe 401, debe redirigir al login (sesión expirada o inactividad de 30min).
     * 
     * Lógica sugerida en JS del cliente:
     * 
     * setInterval(() => {
     *   fetch('/auth/check', { headers: { Authorization: 'Bearer ' + token } })
     *     .then(r => { if (r.status === 401) window.location = '/login'; })
     * }, 5 * 60 * 1000); // cada 5 minutos
     * 
     * Al detectar inactividad de 30min, llamar a /auth/logout.
     */
}
