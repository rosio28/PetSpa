-- ============================================================
-- PET SPA — SEED DATA (versión corregida, sin duplicados)
-- ============================================================

-- Servicios
INSERT INTO servicios (nombre, descripcion, duracion_base_minutos, precio_base, factor_tamano_raza, consumo_insumos) VALUES
('Baño completo',   'Baño, secado y cepillado profesional', 60,  80.00, '{"pequeno":0,"mediano":15,"grande":30}', '[{"producto_id":1,"cantidad":1}]'),
('Corte estándar',  'Corte de pelo según raza',             90,  120.00,'{"pequeno":0,"mediano":15,"grande":30}', NULL),
('Baño + Corte',    'Servicio completo',                    120, 180.00,'{"pequeno":0,"mediano":30,"grande":45}', '[{"producto_id":1,"cantidad":1}]'),
('Corte de uñas',   'Corte y limado profesional',           30,  30.00,  NULL, NULL),
('Limpieza dental', 'Limpieza básica con ultrasonido',      45,  60.00,  NULL, NULL),
('Desparasitación', 'Tratamiento antipulgas y desparasitación', 30, 50.00, NULL, NULL)
ON CONFLICT DO NOTHING;

-- Categorías de productos
INSERT INTO categorias_producto (nombre) VALUES
('Shampoos'),('Alimentos'),('Juguetes'),('Accesorios'),('Insumos Internos')
ON CONFLICT DO NOTHING;

-- Productos
INSERT INTO productos (nombre, descripcion, precio_base, stock, stock_minimo, sku, categoria_id) VALUES
('Shampoo Neutro 500ml',      'Hipoalergénico, apto para piel sensible', 35.00, 50, 10, 'SHP-001', 1),
('Shampoo de Avena 500ml',    'Calmante y humectante',                   40.00, 30,  8, 'SHP-002', 1),
('Shampoo Anti-pulgas 500ml', 'Con permetrina al 0.05%',                 45.00, 20,  8, 'SHP-003', 1),
('Croquetas Premium 1kg',     'Alimento balanceado adulto',              85.00,100, 20, 'ALI-001', 2),
('Croquetas Premium 3kg',     'Pack familiar — descuento incluido',     220.00, 40, 10, 'ALI-002', 2),
('Collar Reflectante LED',    'Con LED de seguridad nocturna',           45.00, 25,  5, 'ACC-001', 4),
('Cepillo Cerdas Suaves',     'Para pelaje corto y fino',                28.00, 30,  5, 'ACC-002', 4),
('Juguete Kong Classic',      'Resistente y rellenable',                 55.00, 15,  3, 'JUG-001', 3)
ON CONFLICT (sku) DO NOTHING;

-- ============================================================
-- USUARIOS DE PRUEBA  (contraseña de todos: Admin@1234)
-- BCrypt cost=12 de "Admin@1234"
-- ============================================================
-- Admin
INSERT INTO usuarios (email, password_hash, rol_id, estado, two_factor_enabled)
SELECT 'admin@petspa.bo',
       '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiPsUMeKDr1yGjWjFHbmR4sSb22i',
       r.id, TRUE, FALSE
FROM roles r WHERE r.nombre='admin'
ON CONFLICT (email) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    estado = TRUE;

-- Groomer
INSERT INTO usuarios (email, password_hash, rol_id, estado)
SELECT 'groomer1@petspa.bo',
       '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiPsUMeKDr1yGjWjFHbmR4sSb22i',
       r.id, TRUE
FROM roles r WHERE r.nombre='groomer'
ON CONFLICT (email) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    estado = TRUE;

INSERT INTO groomers (usuario_id, nombre, telefono, especialidad, turno, capacidad_simultanea)
SELECT u.id, 'María López', '70012345', 'Corte fino y razas pequeñas', 'mañana', 2
FROM usuarios u WHERE u.email='groomer1@petspa.bo'
ON CONFLICT DO NOTHING;

-- Recepción
INSERT INTO usuarios (email, password_hash, rol_id, estado)
SELECT 'recepcion@petspa.bo',
       '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiPsUMeKDr1yGjWjFHbmR4sSb22i',
       r.id, TRUE
FROM roles r WHERE r.nombre='recepcion'
ON CONFLICT (email) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    estado = TRUE;

-- Cliente de prueba
INSERT INTO usuarios (email, password_hash, rol_id, estado)
SELECT 'cliente@petspa.bo',
       '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiPsUMeKDr1yGjWjFHbmR4sSb22i',
       r.id, TRUE
FROM roles r WHERE r.nombre='cliente'
ON CONFLICT (email) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    estado = TRUE;

INSERT INTO clientes (usuario_id, nombre, telefono, ci, direccion, canal_notif)
SELECT u.id, 'Carlos Mamani', '71234567', '7654321', 'Av. Camacho 1234, La Paz', 'email'
FROM usuarios u WHERE u.email='cliente@petspa.bo'
ON CONFLICT DO NOTHING;

-- Mascota del cliente de prueba
INSERT INTO mascotas (nombre, especie, raza, peso_kg, temperamento, alergias, fecha_nacimiento)
VALUES ('Firulais', 'Perro', 'Labrador', 25.5, 'jugueton', 'Ninguna', '2021-03-15')
ON CONFLICT DO NOTHING;

INSERT INTO mascota_dueno (mascota_id, cliente_id, es_principal)
SELECT m.id, c.id, TRUE
FROM mascotas m, clientes c
WHERE m.nombre='Firulais' AND c.nombre='Carlos Mamani'
ON CONFLICT DO NOTHING;

-- ============================================================
-- DISPONIBILIDAD GROOMER (Lun–Vie)
-- ============================================================
INSERT INTO disponibilidad_groomer (groomer_id, dia_semana, hora_inicio, hora_fin, descanso, buffer_minutos)
SELECT g.id, d.dia, '09:00', '18:00', '{"inicio":"13:00","fin":"14:00"}', 15
FROM groomers g CROSS JOIN (VALUES (1),(2),(3),(4),(5)) AS d(dia)
WHERE g.nombre='María López'
ON CONFLICT (groomer_id, dia_semana) DO NOTHING;

-- ============================================================
-- CHECKLIST ITEMS (con ON CONFLICT para no duplicar)
-- ============================================================
INSERT INTO checklist_items_maestro (nombre, requiere_observacion) VALUES
('Baño',               FALSE),
('Corte de pelo',      FALSE),
('Corte de uñas',      TRUE),
('Limpieza de oídos',  TRUE),
('Glándulas anales',   TRUE),
('Perfume',            FALSE)
ON CONFLICT (nombre) DO NOTHING;

-- ============================================================
-- VERIFICACIÓN FINAL
-- ============================================================
SELECT 'Usuarios' AS tabla, COUNT(*) AS total FROM usuarios
UNION ALL
SELECT 'Clientes',   COUNT(*) FROM clientes
UNION ALL
SELECT 'Groomers',   COUNT(*) FROM groomers
UNION ALL
SELECT 'Mascotas',   COUNT(*) FROM mascotas
UNION ALL
SELECT 'Servicios',  COUNT(*) FROM servicios
UNION ALL
SELECT 'Productos',  COUNT(*) FROM productos
UNION ALL
SELECT 'Checklist items', COUNT(*) FROM checklist_items_maestro;
