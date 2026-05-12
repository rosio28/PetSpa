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

match(true) {
    $method==='POST' && $uri==='/auth/register'            => AuthController::register(),
    $method==='GET'  && $uri==='/auth/verify'              => AuthController::verify(),
    $method==='POST' && $uri==='/auth/resend-verification' => AuthController::resendVerification(),
    $method==='POST' && $uri==='/auth/login'               => (function(){ RateLimiter::auth(); AuthController::login(); })(),
    $method==='POST' && $uri==='/auth/refresh'             => AuthController::refresh(),
    $method==='POST' && $uri==='/auth/logout'              => AuthController::logout(),
    $method==='POST' && $uri==='/auth/forgot-password'     => AuthController::forgotPassword(),
    $method==='POST' && $uri==='/auth/reset-password'      => AuthController::resetPassword(),
    $method==='GET'  && $uri==='/auth/google'              => AuthController::googleRedirect(),
    $method==='GET'  && $uri==='/auth/google/callback'     => AuthController::googleCallback(),
    $method==='POST' && $uri==='/auth/2fa/setup'           => AuthController::setup2FA(),
    $method==='POST' && $uri==='/auth/2fa/confirm'         => AuthController::confirm2FA(),
    $method==='GET'  && $uri==='/auth/check'               => SessionTimeout::check(),
    $method==='PUT'  && $uri==='/auth/change-password'     => UsuariosController::changePassword(),
    $method==='GET'  && $uri==='/admin/usuarios'           => UsuariosController::listar(),
    $method==='POST' && $uri==='/admin/usuarios'           => UsuariosController::crearPersonal(),
    $method==='PUT'  && preg_match('#^/admin/usuarios/(\d+)/estado$#',$uri,$m) => UsuariosController::cambiarEstado((int)$m[1]),
    $method==='GET'  && $uri==='/clientes'                 => ClientesController::listar(),
    $method==='GET'  && preg_match('#^/clientes/(\d+)$#',$uri,$m)             => ClientesController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/clientes/(\d+)$#',$uri,$m)             => ClientesController::actualizar((int)$m[1]),
    $method==='GET'  && preg_match('#^/clientes/(\d+)/historial$#',$uri,$m)   => ClientesController::historial((int)$m[1]),
    $method==='GET'  && $uri==='/groomers'                 => GroomersController::listar(),
    $method==='GET'  && preg_match('#^/groomers/(\d+)$#',$uri,$m)             => GroomersController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/groomers/(\d+)$#',$uri,$m)             => GroomersController::actualizar((int)$m[1]),
    $method==='POST' && preg_match('#^/groomers/(\d+)/disponibilidad$#',$uri,$m) => GroomersController::setDisponibilidad((int)$m[1]),
    $method==='GET'  && preg_match('#^/groomers/(\d+)/disponibilidad$#',$uri,$m) => GroomersController::getDisponibilidad((int)$m[1]),
    $method==='POST' && preg_match('#^/groomers/(\d+)/bloqueos$#',$uri,$m)    => GroomersController::crearBloqueo((int)$m[1]),
    $method==='GET'  && preg_match('#^/groomers/(\d+)/bloqueos$#',$uri,$m)    => GroomersController::getBloqueos((int)$m[1]),
    $method==='DELETE'&& preg_match('#^/groomers/bloqueos/(\d+)$#',$uri,$m)   => GroomersController::eliminarBloqueo((int)$m[1]),
    $method==='GET'  && $uri==='/mascotas'                 => MascotasController::listar(),
    $method==='POST' && $uri==='/mascotas'                 => MascotasController::crear(),
    $method==='GET'  && preg_match('#^/mascotas/(\d+)$#',$uri,$m)             => MascotasController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/mascotas/(\d+)$#',$uri,$m)             => MascotasController::actualizar((int)$m[1]),
    $method==='DELETE'&& preg_match('#^/mascotas/(\d+)$#',$uri,$m)            => MascotasController::eliminar((int)$m[1]),
    $method==='POST' && preg_match('#^/mascotas/(\d+)/foto$#',$uri,$m)        => MascotasController::subirFoto((int)$m[1]),
    $method==='GET'  && $uri==='/servicios'                => ServiciosController::listar(),
    $method==='POST' && $uri==='/servicios'                => ServiciosController::crear(),
    $method==='GET'  && preg_match('#^/servicios/(\d+)$#',$uri,$m)            => ServiciosController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/servicios/(\d+)$#',$uri,$m)            => ServiciosController::actualizar((int)$m[1]),
    $method==='DELETE'&& preg_match('#^/servicios/(\d+)$#',$uri,$m)           => ServiciosController::eliminar((int)$m[1]),
    $method==='GET'  && $uri==='/productos'                => ProductosController::listar(),
    $method==='POST' && $uri==='/productos'                => ProductosController::crear(),
    $method==='GET'  && $uri==='/productos/bajo-stock'     => ProductosController::bajoStock(),
    $method==='GET'  && preg_match('#^/productos/(\d+)$#',$uri,$m)            => ProductosController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/productos/(\d+)$#',$uri,$m)            => ProductosController::actualizar((int)$m[1]),
    $method==='GET'  && $uri==='/citas'                    => CitasController::listar(),
    $method==='POST' && $uri==='/citas'                    => CitasController::crear(),
    $method==='GET'  && $uri==='/disponibilidad'           => CitasController::disponibilidad(),
    $method==='GET'  && preg_match('#^/citas/(\d+)$#',$uri,$m)                => CitasController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/citas/(\d+)/estado$#',$uri,$m)         => CitasController::cambiarEstado((int)$m[1]),
    $method==='PUT'  && preg_match('#^/citas/(\d+)/reprogramar$#',$uri,$m)    => CitasController::reprogramar((int)$m[1]),
    $method==='GET'  && preg_match('#^/fichas/(\d+)$#',$uri,$m)               => FichasGroomingController::ver((int)$m[1]),
    $method==='PUT'  && preg_match('#^/fichas/(\d+)$#',$uri,$m)               => FichasGroomingController::actualizar((int)$m[1]),
    $method==='POST' && preg_match('#^/fichas/(\d+)/cerrar$#',$uri,$m)        => FichasGroomingController::cerrar((int)$m[1]),
    $method==='POST' && preg_match('#^/fichas/(\d+)/fotos$#',$uri,$m)         => FichasGroomingController::subirFoto((int)$m[1]),
    $method==='GET'  && $uri==='/carrito'                  => CarritoController::ver(),
    $method==='POST' && $uri==='/carrito/agregar'          => CarritoController::agregar(),
    $method==='POST' && $uri==='/carrito/pedido'           => CarritoController::crearPedido(),
    $method==='DELETE'&& preg_match('#^/carrito/item/(\d+)$#',$uri,$m)        => CarritoController::eliminarItem((int)$m[1]),
    $method==='GET'  && $uri==='/facturas'                 => FacturasController::listar(),
    $method==='POST' && $uri==='/facturas'                 => FacturasController::crear(),
    $method==='GET'  && preg_match('#^/facturas/(\d+)$#',$uri,$m)             => FacturasController::ver((int)$m[1]),
    $method==='POST' && preg_match('#^/facturas/(\d+)/pago$#',$uri,$m)        => FacturasController::registrarPago((int)$m[1]),
    $method==='GET'  && $uri==='/reportes/dashboard'           => ReportesController::dashboard(),
    $method==='GET'  && $uri==='/reportes/ocupacion-groomers'  => ReportesController::ocupacionGroomers(),
    $method==='GET'  && $uri==='/reportes/top-servicios'       => ReportesController::topServicios(),
    $method==='GET'  && $uri==='/reportes/top-productos'       => ReportesController::topProductos(),
    $method==='GET'  && $uri==='/reportes/clientes-frecuentes' => ReportesController::clientesFrecuentes(),
    $method==='GET'  && $uri==='/reportes/cancelaciones'       => ReportesController::cancelaciones(),
    $method==='GET'  && $uri==='/reportes/ingresos-diarios'    => ReportesController::ingresosDiarios(),
    $method==='GET'  && $uri==='/reportes/horas-pico'          => ReportesController::horasPico(),
    default => Response::error("Ruta no encontrada: [$method] $uri", 404),
};
