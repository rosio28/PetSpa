# 🐾 Pet Spa — Sistema de Gestión

PHP 8.1+ · PostgreSQL 16 · Sin frameworks · Docker incluido

---

## ⚡ INICIO RÁPIDO (Docker — 4 comandos)

    cd petspa
    docker-compose up -d --build
    docker exec -i petspa_db psql -U petspa_user -d petspa < database/seed.sql
    # Abrir: http://localhost:8080/login.html

---

## 🔑 Usuarios de prueba (todos: Admin@1234)

| Email | Rol |
|-------|-----|
| admin@petspa.bo | Administrador |
| groomer1@petspa.bo | Groomer |
| recepcion@petspa.bo | Recepción |
| cliente@petspa.bo | Cliente |

---

## 🖥️ SIN DOCKER (Ubuntu/Debian)

    sudo apt install php8.2 php8.2-pgsql postgresql apache2
    sudo a2enmod rewrite && sudo systemctl restart apache2

    sudo -u postgres psql -c "CREATE USER petspa_user WITH PASSWORD '12345678';"
    sudo -u postgres psql -c "CREATE DATABASE petspa OWNER petspa_user;"

    psql -U petspa_user -d petspa -f database/schema.sql
    psql -U petspa_user -d petspa -f database/seed.sql

    cp .env.example .env
    mkdir -p uploads/fotos && chmod 755 uploads/

    # Desarrollo rápido:
    php -S 0.0.0.0:8080 -t public/
    # Abrir: http://localhost:8080/login.html

---

## 🐳 Docker — Comandos útiles

    docker-compose up -d --build          # Levantar
    docker-compose logs -f app            # Ver logs PHP
    docker-compose logs -f db             # Ver logs Postgres
    docker-compose down -v                # Bajar + borrar datos
    docker exec -it petspa_db psql -U petspa_user -d petspa   # psql

---

## 📁 Estructura

    petspa/
    ├── public/           ← Document root
    │   ├── index.php     ← Router API
    │   ├── login.html    ← Login / Registro
    │   ├── dashboard.html← App SPA principal
    │   ├── verify.html   ← Verificar email
    │   ├── reset-password.html
    │   ├── css/style.css
    │   └── js/app.js  js/pages.js
    ├── src/Controllers/  ← 12 controladores
    ├── src/Helpers/      ← JWT, Response, Sanitizer
    ├── src/Middleware/   ← Auth, RateLimiter
    ├── config/database.php
    ├── database/schema.sql  ← 33 tablas
    ├── database/seed.sql
    ├── uploads/fotos/
    ├── .env.example
    ├── docker-compose.yml
    └── Dockerfile

---

## ❗ Problemas comunes

BD no conecta → revisar .env y que PostgreSQL esté corriendo
Ruta 404 Apache → sudo a2enmod rewrite, AllowOverride All
Fotos no suben → chmod -R 777 uploads/
Login siempre 401 → ejecutar seed.sql primero
