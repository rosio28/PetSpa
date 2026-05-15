<?php
declare(strict_types=1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '' || $uri === '/') { header('Location: /login.html'); exit; }

$apiPrefixes = ['/auth','/admin','/clientes','/groomers','/mascotas',
                '/servicios','/productos','/citas','/fichas','/carrito',
                '/facturas','/pedidos','/reportes','/disponibilidad'];
$isApi = false;
foreach ($apiPrefixes as $p) { if (str_starts_with($uri, $p)) { $isApi = true; break; } }
if (!$isApi) { header('Location: /login.html'); exit; }

header('Content-Type: application/json; charset=UTF-8');
$base = __DIR__ . '/../';

require $base.'config/database.php';
require $base.'src/Helpers/JWT.php';
require $base.'src/Helpers/Response.php';
require $base.'src/Helpers/Sanitizer.php';
require $base.'src/Middleware/Auth.php';
require $base.'src/Middleware/RateLimiter.php';
require $base.'src/Middleware/SessionTimeout.php';
require $base.'src/Controllers/AuthController.php';
require $base.'src/Controllers/UsuariosController.php';
require $base.'src/Controllers/ClientesController.php';
require $base.'src/Controllers/GroomersController.php';
require $base.'src/Controllers/MascotasController.php';
require $base.'src/Controllers/ServiciosController.php';
require $base.'src/Controllers/ProductosController.php';
require $base.'src/Controllers/CitasController.php';
require $base.'src/Controllers/FichasGroomingController.php';
require $base.'src/Controllers/CarritoController.php';
require $base.'src/Controllers/FacturasController.php';
require $base.'src/Controllers/ReportesController.php';

RateLimiter::check(200, 60);

$method = $_SERVER['REQUEST_METHOD'];
$uri    = preg_replace('#^/api#', '', $uri);

// ─── AUTH ──────────────────────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/auth/register')              { AuthController::register(); exit; }
if ($method === 'GET'  && $uri === '/auth/verify')                { AuthController::verify(); exit; }
if ($method === 'POST' && $uri === '/auth/resend-verification')   { AuthController::resendVerification(); exit; }
if ($method === 'POST' && $uri === '/auth/login')                 { RateLimiter::auth(); AuthController::login(); exit; }
if ($method === 'POST' && $uri === '/auth/refresh')               { AuthController::refresh(); exit; }
if ($method === 'POST' && $uri === '/auth/logout')                { AuthController::logout(); exit; }
if ($method === 'POST' && $uri === '/auth/forgot-password')       { AuthController::forgotPassword(); exit; }
if ($method === 'POST' && $uri === '/auth/reset-password')        { AuthController::resetPassword(); exit; }
if ($method === 'POST' && $uri === '/auth/forgot-password-whatsapp') { AuthController::forgotPasswordWhatsapp(); exit; }
if ($method === 'GET'  && $uri === '/auth/google')                { AuthController::googleRedirect(); exit; }
if ($method === 'GET'  && $uri === '/auth/google/callback')       { AuthController::googleCallback(); exit; }
if ($method === 'POST' && $uri === '/auth/2fa/setup')             { AuthController::setup2FA(); exit; }
if ($method === 'POST' && $uri === '/auth/2fa/confirm')           { AuthController::confirm2FA(); exit; }
if ($method === 'GET'  && $uri === '/auth/check')                 { SessionTimeout::check(); exit; }
if ($method === 'PUT'  && $uri === '/auth/change-password')       { UsuariosController::changePassword(); exit; }

// ─── ADMIN USUARIOS ─────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/admin/usuarios')             { UsuariosController::listar(); exit; }
if ($method === 'POST' && $uri === '/admin/usuarios')             { UsuariosController::crearPersonal(); exit; }

if (preg_match('#^/admin/usuarios/(\d+)/estado$#', $uri, $m)) {
    if ($method === 'PUT') { UsuariosController::cambiarEstado((int)$m[1]); exit; }
}
if (preg_match('#^/admin/usuarios/(\d+)/reset-password$#', $uri, $m)) {
    if ($method === 'POST') { UsuariosController::resetPassword((int)$m[1]); exit; }
}
if (preg_match('#^/admin/usuarios/(\d+)/detalle$#', $uri, $m)) {
    if ($method === 'GET') { UsuariosController::detalle((int)$m[1]); exit; }
}

// ─── CLIENTES ───────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/clientes')                   { ClientesController::listar(); exit; }
if (preg_match('#^/clientes/(\d+)$#', $uri, $m)) {
    if ($method === 'GET') { ClientesController::ver((int)$m[1]); exit; }
    if ($method === 'PUT') { ClientesController::actualizar((int)$m[1]); exit; }
}
if (preg_match('#^/clientes/(\d+)/historial$#', $uri, $m)) {
    if ($method === 'GET') { ClientesController::historial((int)$m[1]); exit; }
}

// ─── GROOMERS ───────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/groomers')                   { GroomersController::listar(); exit; }
if (preg_match('#^/groomers/(\d+)$#', $uri, $m)) {
    if ($method === 'GET') { GroomersController::ver((int)$m[1]); exit; }
    if ($method === 'PUT') { GroomersController::actualizar((int)$m[1]); exit; }
}
if (preg_match('#^/groomers/(\d+)/disponibilidad$#', $uri, $m)) {
    if ($method === 'POST') { GroomersController::setDisponibilidad((int)$m[1]); exit; }
    if ($method === 'GET')  { GroomersController::getDisponibilidad((int)$m[1]); exit; }
}
if (preg_match('#^/groomers/(\d+)/bloqueos$#', $uri, $m)) {
    if ($method === 'POST') { GroomersController::crearBloqueo((int)$m[1]); exit; }
    if ($method === 'GET')  { GroomersController::getBloqueos((int)$m[1]); exit; }
}
if (preg_match('#^/groomers/bloqueos/(\d+)$#', $uri, $m)) {
    if ($method === 'DELETE') { GroomersController::eliminarBloqueo((int)$m[1]); exit; }
}

// ─── MASCOTAS ───────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/mascotas')                   { MascotasController::listar(); exit; }
if ($method === 'POST' && $uri === '/mascotas')                   { MascotasController::crear(); exit; }
if (preg_match('#^/mascotas/(\d+)$#', $uri, $m)) {
    if ($method === 'GET')    { MascotasController::ver((int)$m[1]); exit; }
    if ($method === 'PUT')    { MascotasController::actualizar((int)$m[1]); exit; }
    if ($method === 'DELETE') { MascotasController::eliminar((int)$m[1]); exit; }
}
if (preg_match('#^/mascotas/(\d+)/foto$#', $uri, $m)) {
    if ($method === 'POST') { MascotasController::subirFoto((int)$m[1]); exit; }
}

// ─── SERVICIOS ──────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/servicios')                  { ServiciosController::listar(); exit; }
if ($method === 'POST' && $uri === '/servicios')                  { ServiciosController::crear(); exit; }
if (preg_match('#^/servicios/(\d+)$#', $uri, $m)) {
    if ($method === 'GET')    { ServiciosController::ver((int)$m[1]); exit; }
    if ($method === 'PUT')    { ServiciosController::actualizar((int)$m[1]); exit; }
    if ($method === 'DELETE') { ServiciosController::eliminar((int)$m[1]); exit; }
}

// ─── PRODUCTOS ──────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/productos')                  { ProductosController::listar(); exit; }
if ($method === 'POST' && $uri === '/productos')                  { ProductosController::crear(); exit; }
if ($method === 'GET'  && $uri === '/productos/bajo-stock')       { ProductosController::bajoStock(); exit; }
if (preg_match('#^/productos/(\d+)$#', $uri, $m)) {
    if ($method === 'GET') { ProductosController::ver((int)$m[1]); exit; }
    if ($method === 'PUT') { ProductosController::actualizar((int)$m[1]); exit; }
}

// ─── CITAS ──────────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/citas')                      { CitasController::listar(); exit; }
if ($method === 'POST' && $uri === '/citas')                      { CitasController::crear(); exit; }
if ($method === 'GET'  && $uri === '/disponibilidad')             { CitasController::disponibilidad(); exit; }
if (preg_match('#^/citas/(\d+)$#', $uri, $m)) {
    if ($method === 'GET') { CitasController::ver((int)$m[1]); exit; }
}
if (preg_match('#^/citas/(\d+)/estado$#', $uri, $m)) {
    if ($method === 'PUT') { CitasController::cambiarEstado((int)$m[1]); exit; }
}
if (preg_match('#^/citas/(\d+)/reprogramar$#', $uri, $m)) {
    if ($method === 'PUT') { CitasController::reprogramar((int)$m[1]); exit; }
}

// ─── FICHAS GROOMING ────────────────────────────────────────────────────────
if (preg_match('#^/fichas/(\d+)$#', $uri, $m)) {
    if ($method === 'GET') { FichasGroomingController::ver((int)$m[1]); exit; }
    if ($method === 'PUT') { FichasGroomingController::actualizar((int)$m[1]); exit; }
}
if (preg_match('#^/fichas/(\d+)/cerrar$#', $uri, $m)) {
    if ($method === 'POST') { FichasGroomingController::cerrar((int)$m[1]); exit; }
}
if (preg_match('#^/fichas/(\d+)/fotos$#', $uri, $m)) {
    if ($method === 'POST') { FichasGroomingController::subirFoto((int)$m[1]); exit; }
}

// ─── CARRITO / TIENDA ───────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/carrito')                    { CarritoController::ver(); exit; }
if ($method === 'POST' && $uri === '/carrito/agregar')            { CarritoController::agregar(); exit; }
if ($method === 'POST' && $uri === '/carrito/pedido')             { CarritoController::crearPedido(); exit; }
if (preg_match('#^/carrito/item/(\d+)$#', $uri, $m)) {
    if ($method === 'DELETE') { CarritoController::eliminarItem((int)$m[1]); exit; }
}

// ─── FACTURAS ───────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/facturas')                   { FacturasController::listar(); exit; }
if ($method === 'POST' && $uri === '/facturas')                   { FacturasController::crear(); exit; }
if (preg_match('#^/facturas/(\d+)$#', $uri, $m)) {
    if ($method === 'GET') { FacturasController::ver((int)$m[1]); exit; }
}
if (preg_match('#^/facturas/(\d+)/pago$#', $uri, $m)) {
    if ($method === 'POST') { FacturasController::registrarPago((int)$m[1]); exit; }
}

// ─── REPORTES ───────────────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/reportes/dashboard')              { ReportesController::dashboard(); exit; }
if ($method === 'GET' && $uri === '/reportes/ocupacion-groomers')     { ReportesController::ocupacionGroomers(); exit; }
if ($method === 'GET' && $uri === '/reportes/top-servicios')          { ReportesController::topServicios(); exit; }
if ($method === 'GET' && $uri === '/reportes/top-productos')          { ReportesController::topProductos(); exit; }
if ($method === 'GET' && $uri === '/reportes/clientes-frecuentes')    { ReportesController::clientesFrecuentes(); exit; }
if ($method === 'GET' && $uri === '/reportes/cancelaciones')          { ReportesController::cancelaciones(); exit; }
if ($method === 'GET' && $uri === '/reportes/ingresos-diarios')       { ReportesController::ingresosDiarios(); exit; }
if ($method === 'GET' && $uri === '/reportes/horas-pico')             { ReportesController::horasPico(); exit; }

// ─── 404 ────────────────────────────────────────────────────────────────────
Response::error("Ruta no encontrada: [$method] $uri", 404);
