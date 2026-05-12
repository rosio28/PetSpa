#!/bin/bash
# ============================================================
#  PET SPA — INSTALADOR MANUAL (sin Docker)
#  Requiere: PHP 8.1+, PostgreSQL 14+, Apache o Nginx
# ============================================================

set -e
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}"
echo "╔══════════════════════════════════════╗"
echo "║      PET SPA — INSTALADOR           ║"
echo "╚══════════════════════════════════════╝"
echo -e "${NC}"

# ── Verificar dependencias ──────────────────────────────────
echo -e "${YELLOW}[1/5] Verificando dependencias...${NC}"

command -v php  >/dev/null || { echo -e "${RED}ERROR: PHP no instalado. Instala PHP 8.1+${NC}"; exit 1; }
command -v psql >/dev/null || { echo -e "${RED}ERROR: psql no encontrado. Instala PostgreSQL${NC}"; exit 1; }

PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "  ${GREEN}✓ PHP $PHP_VER${NC}"

# Verificar extensión PDO PostgreSQL
php -m | grep -q pdo_pgsql || { echo -e "${RED}ERROR: Extensión pdo_pgsql no habilitada en PHP${NC}"; exit 1; }
echo -e "  ${GREEN}✓ pdo_pgsql OK${NC}"

# ── Configurar variables ────────────────────────────────────
echo ""
echo -e "${YELLOW}[2/5] Configuración de base de datos...${NC}"

read -p "  Host PostgreSQL [localhost]: " DB_HOST; DB_HOST=${DB_HOST:-localhost}
read -p "  Puerto [5432]: " DB_PORT; DB_PORT=${DB_PORT:-5432}
read -p "  Nombre de la BD [petspa]: " DB_NAME; DB_NAME=${DB_NAME:-petspa}
read -p "  Usuario PostgreSQL [petspa_user]: " DB_USER; DB_USER=${DB_USER:-petspa_user}
read -s -p "  Contraseña del usuario: " DB_PASS; echo ""
read -s -p "  Contraseña del superusuario postgres (para crear BD): " SUPER_PASS; echo ""

# Generar JWT secret aleatorio
JWT_SECRET=$(php -r "echo bin2hex(random_bytes(32));")

read -p "  URL de la aplicación [http://localhost:8080]: " APP_URL
APP_URL=${APP_URL:-http://localhost:8080}

# ── Crear base de datos y usuario ──────────────────────────
echo ""
echo -e "${YELLOW}[3/5] Creando base de datos...${NC}"

PGPASSWORD="$SUPER_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres <<SQL
  CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';
  CREATE DATABASE $DB_NAME OWNER $DB_USER;
  GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
SQL
echo -e "  ${GREEN}✓ Base de datos '$DB_NAME' creada${NC}"

# Aplicar schema
PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f database/schema.sql
echo -e "  ${GREEN}✓ Schema aplicado${NC}"

# Aplicar seed
PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f database/seed.sql
echo -e "  ${GREEN}✓ Datos de prueba cargados${NC}"

# ── Generar config ──────────────────────────────────────────
echo ""
echo -e "${YELLOW}[4/5] Generando configuración...${NC}"

cat > .env << EOF
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
JWT_SECRET=$JWT_SECRET
APP_URL=$APP_URL
EOF

# Actualizar config/database.php para leer .env si existe
cat > config/database.php << 'PHPEOF'
<?php
// Cargar .env si existe (desarrollo)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
            putenv(trim($k) . '=' . trim($v));
        }
    }
}

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '5432');
define('DB_NAME',    getenv('DB_NAME')    ?: 'petspa');
define('DB_USER',    getenv('DB_USER')    ?: 'petspa_user');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CAMBIA_ESTO');
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
                die(json_encode(['error' => 'Error de conexión a la base de datos: ' . $e->getMessage()]));
            }
        }
        return self::$conn;
    }
}
PHPEOF

echo -e "  ${GREEN}✓ .env y config generados${NC}"

# ── Permisos ────────────────────────────────────────────────
mkdir -p uploads/fotos
chmod -R 755 uploads/
echo -e "  ${GREEN}✓ Carpeta uploads/ creada${NC}"

# ── Instrucciones finales ───────────────────────────────────
echo ""
echo -e "${YELLOW}[5/5] Configurar servidor web...${NC}"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ INSTALACIÓN COMPLETA${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  🌐 URL: ${YELLOW}$APP_URL${NC}"
echo ""
echo -e "  ${YELLOW}OPCIÓN A — PHP Built-in server (desarrollo rápido):${NC}"
echo -e "  ${GREEN}php -S 0.0.0.0:8080 -t public/${NC}"
echo ""
echo -e "  ${YELLOW}OPCIÓN B — Apache (producción):${NC}"
echo "  Apunta DocumentRoot a: $(pwd)/public"
echo "  Asegúrate de tener AllowOverride All y mod_rewrite"
echo ""
echo -e "  ${YELLOW}Usuarios de prueba:${NC}"
echo -e "  📧 admin@petspa.bo      🔑 Admin@1234  (admin)"
echo -e "  📧 groomer1@petspa.bo   🔑 Admin@1234  (groomer)"
echo -e "  📧 recepcion@petspa.bo  🔑 Admin@1234  (recepción)"
echo -e "  📧 cliente@petspa.bo    🔑 Admin@1234  (cliente)"
echo ""
