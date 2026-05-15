-- ============================================================
-- FIX: desbloquear admin y resetear sesiones
-- Ejecutar con:
--   docker exec -i petspa_db psql -U petspa_user -d petspa < database/fix_admin.sql
-- ============================================================

-- 1. Resetear cuenta admin (desbloquear, resetear intentos, asegurar hash correcto)
UPDATE usuarios SET
    password_hash      = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiPsUMeKDr1yGjWjFHbmR4sSb22i',
    intentos_fallidos  = 0,
    bloqueado_hasta    = NULL,
    estado             = TRUE,
    two_factor_enabled = FALSE
WHERE email = 'admin@petspa.bo';

-- 2. Eliminar sesiones viejas del admin (tokens firmados con el JWT_SECRET anterior)
DELETE FROM user_sessions
WHERE usuario_id = (SELECT id FROM usuarios WHERE email = 'admin@petspa.bo');

-- 3. Resetear también groomer y recepción por si acaso
UPDATE usuarios SET
    intentos_fallidos = 0,
    bloqueado_hasta   = NULL,
    estado            = TRUE
WHERE email IN ('groomer1@petspa.bo', 'recepcion@petspa.bo', 'cliente@petspa.bo');

DELETE FROM user_sessions
WHERE usuario_id IN (
    SELECT id FROM usuarios
    WHERE email IN ('groomer1@petspa.bo','recepcion@petspa.bo','cliente@petspa.bo')
);

-- 4. Verificar resultado
SELECT email,
       estado,
       intentos_fallidos,
       bloqueado_hasta,
       two_factor_enabled,
       LEFT(password_hash, 20) AS hash_inicio
FROM usuarios
ORDER BY id;
