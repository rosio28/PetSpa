-- ============================================================
-- PET SPA - SCHEMA COMPLETO PostgreSQL
-- ============================================================

-- EXTENSIONES
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================
-- ROLES Y USUARIOS
-- ============================================================
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL, -- admin, recepcion, groomer, cliente
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO roles (nombre, descripcion) VALUES
    ('admin',      'Acceso total al sistema'),
    ('recepcion',  'Gestión de citas y clientes'),
    ('groomer',    'Atención de mascotas'),
    ('cliente',    'Autogestión de mascotas y citas');

CREATE TABLE usuarios (
    id            SERIAL PRIMARY KEY,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255),                    -- BCRYPT, NULL si usa OAuth
    rol_id        INTEGER REFERENCES roles(id) ON DELETE SET NULL,
    estado        BOOLEAN DEFAULT FALSE,           -- activo solo tras verificar email
    oauth_provider VARCHAR(50),                    -- 'google' o NULL
    oauth_id       VARCHAR(255),
    two_factor_secret  VARCHAR(255),
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    token_verificacion VARCHAR(255),
    token_expiracion   TIMESTAMP,
    token_recuperacion VARCHAR(255),
    token_rec_expiracion TIMESTAMP,
    intentos_fallidos  INTEGER DEFAULT 0,
    bloqueado_hasta    TIMESTAMP,
    ultimo_acceso      TIMESTAMP,
    created_at    TIMESTAMP DEFAULT NOW(),
    updated_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE user_sessions (
    id            SERIAL PRIMARY KEY,
    usuario_id    INTEGER REFERENCES usuarios(id) ON DELETE CASCADE,
    jwt_token     TEXT NOT NULL,
    refresh_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address    VARCHAR(45),
    user_agent    TEXT,
    expires_at    TIMESTAMP NOT NULL,
    created_at    TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- CLIENTES Y GROOMERS (extensión de usuarios)
-- ============================================================
CREATE TABLE clientes (
    id          SERIAL PRIMARY KEY,
    usuario_id  INTEGER UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    nombre      VARCHAR(150) NOT NULL,
    telefono    VARCHAR(20),
    ci          VARCHAR(20),
    direccion   TEXT,
    canal_notif VARCHAR(20) DEFAULT 'email', -- email, whatsapp, sms
    horario_pref VARCHAR(100),
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE groomers (
    id          SERIAL PRIMARY KEY,
    usuario_id  INTEGER UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    nombre      VARCHAR(150) NOT NULL,
    telefono    VARCHAR(20),
    especialidad VARCHAR(150),
    turno       VARCHAR(50),
    capacidad_simultanea INTEGER DEFAULT 1,
    horario_trabajo JSONB,  -- {"lunes":{"inicio":"09:00","fin":"18:00","almuerzo":{"inicio":"13:00","fin":"14:00"}}}
    estado_activo BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- DISPONIBILIDAD Y BLOQUEOS
-- ============================================================
CREATE TABLE disponibilidad_groomer (
    id          SERIAL PRIMARY KEY,
    groomer_id  INTEGER REFERENCES groomers(id) ON DELETE CASCADE,
    dia_semana  SMALLINT NOT NULL CHECK (dia_semana BETWEEN 0 AND 6), -- 0=Dom
    hora_inicio TIME NOT NULL,
    hora_fin    TIME NOT NULL,
    descanso    JSONB, -- {"inicio":"13:00","fin":"14:00"}
    buffer_minutos INTEGER DEFAULT 15,
    UNIQUE (groomer_id, dia_semana)
);

CREATE TABLE bloqueos_calendario (
    id          SERIAL PRIMARY KEY,
    groomer_id  INTEGER REFERENCES groomers(id) ON DELETE CASCADE, -- NULL = global
    tipo        VARCHAR(50) NOT NULL, -- feriado, vacaciones, mantenimiento, ausencia
    fecha_inicio TIMESTAMP NOT NULL,
    fecha_fin    TIMESTAMP NOT NULL,
    descripcion  TEXT,
    CHECK (fecha_fin > fecha_inicio),
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- MASCOTAS
-- ============================================================
CREATE TABLE mascotas (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    especie     VARCHAR(50),
    raza        VARCHAR(100),
    peso_kg     DECIMAL(5,2),
    fecha_nacimiento DATE,
    temperamento VARCHAR(50), -- tranquilo, jugueton, agresivo
    alergias    TEXT,
    vacunas     JSONB, -- [{"nombre":"Rabia","fecha_aplicacion":"2024-01-01","vencimiento":"2025-01-01"}]
    restricciones_medicas TEXT,
    foto_url    VARCHAR(500),
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE mascota_dueno (
    mascota_id  INTEGER REFERENCES mascotas(id) ON DELETE CASCADE,
    cliente_id  INTEGER REFERENCES clientes(id) ON DELETE CASCADE,
    es_principal BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (mascota_id, cliente_id)
);

CREATE TABLE historial_mascota (
    id          SERIAL PRIMARY KEY,
    mascota_id  INTEGER REFERENCES mascotas(id) ON DELETE CASCADE,
    tipo_evento VARCHAR(50), -- servicio, recomendacion, alerta
    descripcion TEXT,
    usuario_id  INTEGER REFERENCES usuarios(id),
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- SERVICIOS
-- ============================================================
CREATE TABLE servicios (
    id              SERIAL PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    descripcion     TEXT,
    duracion_base_minutos INTEGER NOT NULL CHECK (duracion_base_minutos % 15 = 0),
    precio_base     DECIMAL(10,2) NOT NULL CHECK (precio_base >= 0),
    permite_doble_booking   BOOLEAN DEFAULT FALSE,
    requiere_bloqueo_consecutivo BOOLEAN DEFAULT FALSE,
    factor_tamano_raza JSONB, -- {"pequeno":0,"mediano":15,"grande":30}
    consumo_insumos JSONB,    -- [{"producto_id":1,"cantidad":2}]
    activo          BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- CATEGORÍAS Y PRODUCTOS
-- ============================================================
CREATE TABLE categorias_producto (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    padre_id    INTEGER REFERENCES categorias_producto(id),
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE productos (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio_base DECIMAL(10,2) NOT NULL CHECK (precio_base >= 0),
    stock       INTEGER DEFAULT 0,
    stock_minimo INTEGER DEFAULT 5,
    sku         VARCHAR(100) UNIQUE NOT NULL,
    imagen_url  VARCHAR(500),
    categoria_id INTEGER REFERENCES categorias_producto(id),
    activo      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE variantes_producto (
    id          SERIAL PRIMARY KEY,
    producto_id INTEGER REFERENCES productos(id) ON DELETE CASCADE,
    atributo    VARCHAR(50),  -- talla, fragancia, peso
    valor       VARCHAR(100), -- S, Lavanda, 1kg
    precio_extra DECIMAL(10,2) DEFAULT 0,
    stock       INTEGER DEFAULT 0,
    sku_variante VARCHAR(100) UNIQUE NOT NULL,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- CITAS
-- ============================================================
CREATE TABLE citas (
    id              SERIAL PRIMARY KEY,
    mascota_id      INTEGER REFERENCES mascotas(id),
    groomer_id      INTEGER REFERENCES groomers(id),
    servicio_id     INTEGER REFERENCES servicios(id),
    cliente_id      INTEGER REFERENCES clientes(id),
    fecha_hora_inicio TIMESTAMP NOT NULL,
    fecha_hora_fin  TIMESTAMP,
    duracion_estimada INTEGER,
    duracion_real   INTEGER,
    estado          VARCHAR(30) DEFAULT 'agendada', -- agendada, confirmada, en_progreso, completada, cancelada, no_asistio
    creado_por      INTEGER REFERENCES usuarios(id),
    reprogramado_por INTEGER REFERENCES usuarios(id),
    fecha_reprogramacion TIMESTAMP,
    notas           TEXT,
    CHECK (fecha_hora_fin > fecha_hora_inicio),
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
);

-- Evitar solapamientos por groomer
CREATE UNIQUE INDEX idx_citas_groomer_horario 
    ON citas(groomer_id, fecha_hora_inicio) 
    WHERE estado NOT IN ('cancelada','no_asistio');

-- ============================================================
-- FICHA DE GROOMING
-- ============================================================
CREATE TABLE fichas_grooming (
    id              SERIAL PRIMARY KEY,
    cita_id         INTEGER UNIQUE REFERENCES citas(id),
    raza_momento    VARCHAR(100),
    tamano_momento  VARCHAR(30),
    temperatura_animal DECIMAL(4,1),
    estado_inicial  TEXT,
    estado_final    TEXT,
    notas_internas  TEXT,
    inventario_descontado BOOLEAN DEFAULT FALSE,
    fecha_cierre    TIMESTAMP,
    cerrado_por     INTEGER REFERENCES usuarios(id),
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE TABLE checklist_items_maestro (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    requiere_observacion BOOLEAN DEFAULT FALSE
);

INSERT INTO checklist_items_maestro (nombre, requiere_observacion) VALUES
    ('Baño', false), ('Corte de pelo', false), ('Corte de uñas', true),
    ('Limpieza de oídos', true), ('Glándulas anales', true), ('Perfume', false);

CREATE TABLE checklist_ficha (
    id              SERIAL PRIMARY KEY,
    ficha_id        INTEGER REFERENCES fichas_grooming(id) ON DELETE CASCADE,
    item_id         INTEGER REFERENCES checklist_items_maestro(id),
    completado      BOOLEAN DEFAULT FALSE,
    observacion     TEXT,
    UNIQUE (ficha_id, item_id)
);

CREATE TABLE fotos_grooming (
    id          SERIAL PRIMARY KEY,
    ficha_id    INTEGER REFERENCES fichas_grooming(id) ON DELETE CASCADE,
    tipo        VARCHAR(10) NOT NULL CHECK (tipo IN ('antes','despues')),
    url         VARCHAR(500) NOT NULL,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- CARRITO Y PEDIDOS
-- ============================================================
CREATE TABLE carritos (
    id              SERIAL PRIMARY KEY,
    cliente_id      INTEGER REFERENCES clientes(id),
    session_token   VARCHAR(255) UNIQUE,
    expires_at      TIMESTAMP DEFAULT (NOW() + INTERVAL '7 days'),
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE TABLE detalle_carrito (
    id              SERIAL PRIMARY KEY,
    carrito_id      INTEGER REFERENCES carritos(id) ON DELETE CASCADE,
    producto_id     INTEGER REFERENCES productos(id),
    variante_id     INTEGER REFERENCES variantes_producto(id),
    cantidad        INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL -- precio congelado
);

CREATE TABLE pedidos (
    id              SERIAL PRIMARY KEY,
    carrito_id      INTEGER REFERENCES carritos(id),
    cliente_id      INTEGER REFERENCES clientes(id),
    metodo_contacto VARCHAR(20), -- whatsapp, telegram
    subtotal        DECIMAL(10,2),
    descuento       DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2),
    estado          VARCHAR(30) DEFAULT 'pendiente', -- pendiente, enviado, confirmado, pagado, entregado
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE TABLE detalle_pedido (
    id              SERIAL PRIMARY KEY,
    pedido_id       INTEGER REFERENCES pedidos(id) ON DELETE CASCADE,
    producto_id     INTEGER REFERENCES productos(id),
    variante_id     INTEGER REFERENCES variantes_producto(id),
    cantidad        INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL
);

-- ============================================================
-- FACTURAS Y PAGOS
-- ============================================================
CREATE TABLE facturas (
    id              SERIAL PRIMARY KEY,
    numero          SERIAL,
    cita_id         INTEGER REFERENCES citas(id),
    pedido_id       INTEGER REFERENCES pedidos(id),
    cliente_id      INTEGER REFERENCES clientes(id),
    subtotal        DECIMAL(10,2),
    impuesto        DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2),
    estado          VARCHAR(20) DEFAULT 'pendiente', -- pendiente, pagada, cancelada
    metodo_pago     VARCHAR(30), -- efectivo, qr, transferencia
    fecha_emision   TIMESTAMP DEFAULT NOW(),
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE TABLE detalle_factura (
    id              SERIAL PRIMARY KEY,
    factura_id      INTEGER REFERENCES facturas(id) ON DELETE CASCADE,
    descripcion     VARCHAR(200),
    cantidad        INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL
);

CREATE TABLE pagos (
    id              SERIAL PRIMARY KEY,
    factura_id      INTEGER REFERENCES facturas(id),
    monto           DECIMAL(10,2) NOT NULL,
    metodo          VARCHAR(30),
    referencia      VARCHAR(200),
    estado          VARCHAR(20) DEFAULT 'completado', -- completado, pendiente, fallido
    created_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- NOTIFICACIONES
-- ============================================================
CREATE TABLE notificaciones (
    id              SERIAL PRIMARY KEY,
    cita_id         INTEGER REFERENCES citas(id),
    cliente_id      INTEGER REFERENCES clientes(id),
    tipo_evento     VARCHAR(50), -- confirmacion, recordatorio_24h, recordatorio_2h, listo_recoger, encuesta, promocion
    canal           VARCHAR(20), -- email, whatsapp, sms
    destino         VARCHAR(200),
    fecha_programada TIMESTAMP,
    fecha_envio     TIMESTAMP,
    estado          VARCHAR(20) DEFAULT 'pendiente', -- pendiente, enviado, fallido
    intentos        INTEGER DEFAULT 0,
    created_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE audit_log (
    id          BIGSERIAL PRIMARY KEY,
    usuario_id  INTEGER REFERENCES usuarios(id),
    rol         VARCHAR(50),
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    accion      VARCHAR(200),
    tabla       VARCHAR(100),
    registro_id INTEGER,
    datos_anteriores JSONB,
    datos_nuevos     JSONB,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- ÍNDICES PARA PERFORMANCE
-- ============================================================
CREATE INDEX idx_usuarios_email    ON usuarios(email);
CREATE INDEX idx_citas_fecha       ON citas(fecha_hora_inicio);
CREATE INDEX idx_citas_groomer     ON citas(groomer_id);
CREATE INDEX idx_citas_mascota     ON citas(mascota_id);
CREATE INDEX idx_notif_programada  ON notificaciones(fecha_programada) WHERE estado = 'pendiente';
CREATE INDEX idx_productos_sku     ON productos(sku);
CREATE INDEX idx_audit_usuario     ON audit_log(usuario_id);
CREATE INDEX idx_sessions_token    ON user_sessions(jwt_token);
