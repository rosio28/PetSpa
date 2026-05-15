-- ============================================================
-- MIGRACIÓN: tablas y correcciones faltantes en el schema original
-- Ejecutar UNA sola vez sobre la BD existente:
--   docker exec -i petspa_db psql -U petspa_user -d petspa < database/migrate_001.sql
-- ============================================================

-- 1) Tabla movimiento_inventario (faltaba en schema.sql original)
CREATE TABLE IF NOT EXISTS movimiento_inventario (
    id          BIGSERIAL PRIMARY KEY,
    producto_id INTEGER REFERENCES productos(id) ON DELETE SET NULL,
    tipo        VARCHAR(10) NOT NULL CHECK (tipo IN ('entrada','salida')),
    cantidad    INTEGER     NOT NULL CHECK (cantidad > 0),
    origen      VARCHAR(100),          -- 'grooming', 'compra', 'ajuste_manual', etc.
    usuario_id  INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    fecha       TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_mov_inv_prod ON movimiento_inventario(producto_id);
CREATE INDEX IF NOT EXISTS idx_mov_inv_fecha ON movimiento_inventario(fecha);

-- 2) Unique constraint en checklist_items_maestro para evitar duplicados
--    (el schema original no lo tenía, y seed + schema insertaban los mismos items)
DO $$
BEGIN
  IF NOT EXISTS (
      SELECT 1 FROM pg_constraint
      WHERE conname = 'uq_checklist_items_nombre'
  ) THEN
      ALTER TABLE checklist_items_maestro ADD CONSTRAINT uq_checklist_items_nombre UNIQUE (nombre);
  END IF;
END $$;

-- 3) Eliminar duplicados si ya existen antes de agregar el constraint
DELETE FROM checklist_items_maestro a
WHERE a.id NOT IN (
    SELECT MIN(b.id) FROM checklist_items_maestro b GROUP BY b.nombre
);

-- 4) Columna two_factor_enabled default FALSE (puede no existir en instancias viejas)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE;

-- 5) Columna bloqueado_hasta (puede faltar)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS bloqueado_hasta TIMESTAMP;

-- 6) Columna duracion_real en citas
ALTER TABLE citas
    ADD COLUMN IF NOT EXISTS duracion_real INTEGER;

-- 7) Índice para notificaciones pendientes (si no existe)
CREATE INDEX IF NOT EXISTS idx_notif_estado ON notificaciones(estado) WHERE estado = 'pendiente';

-- 8) Índice para búsquedas de clientes por teléfono
CREATE INDEX IF NOT EXISTS idx_clientes_telefono ON clientes(telefono);

-- 9) Asegurar que ON CONFLICT funcione en mascota_dueno
DO $$
BEGIN
  IF NOT EXISTS (
      SELECT 1 FROM pg_constraint
      WHERE conname = 'mascota_dueno_pkey'
  ) THEN
      ALTER TABLE mascota_dueno ADD PRIMARY KEY (mascota_id, cliente_id);
  END IF;
END $$;

-- 10) Tabla de fidelización (puntos) — preparada para futuro
CREATE TABLE IF NOT EXISTS fidelizacion_movimientos (
    id          SERIAL PRIMARY KEY,
    cliente_id  INTEGER REFERENCES clientes(id) ON DELETE CASCADE,
    cita_id     INTEGER REFERENCES citas(id) ON DELETE SET NULL,
    puntos      INTEGER NOT NULL,           -- positivo = ganados, negativo = canjeados
    concepto    VARCHAR(200),
    created_at  TIMESTAMP DEFAULT NOW()
);

-- Resumen
SELECT 'Migración 001 completada' AS resultado,
       NOW() AS ejecutada_en;
