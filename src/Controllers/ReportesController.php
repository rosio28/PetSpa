<?php
// src/Controllers/ReportesController.php

class ReportesController {

    // GET /reportes/dashboard
    public static function dashboard(): void {
    // Permitir todos los roles autenticados
    $user = Auth::require(['admin','recepcion','groomer','cliente']);
    $db   = DB::get();
    $rol  = $user['rol'];
    $hoy  = date('Y-m-d');

    // Dashboard para CLIENTE
    if ($rol === 'cliente') {
        // Obtener cliente_id
        $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
        $c->execute([$user['user_id']]);
        $clienteId = $c->fetchColumn();

        if (!$clienteId) {
            Response::ok([
                'rol'           => 'cliente',
                'citas_proximas'=> [],
                'total_mascotas'=> 0,
                'citas_total'   => 0,
            ]);
            return;
        }

        $proximas = $db->prepare("SELECT ci.id, ci.fecha_hora_inicio, ci.estado,
                m.nombre as mascota, s.nombre as servicio, g.nombre as groomer
            FROM citas ci
            JOIN mascotas m ON m.id = ci.mascota_id
            JOIN servicios s ON s.id = ci.servicio_id
            JOIN groomers g ON g.id = ci.groomer_id
            WHERE ci.cliente_id = ? AND ci.fecha_hora_inicio >= NOW()
              AND ci.estado NOT IN ('cancelada','no_asistio')
            ORDER BY ci.fecha_hora_inicio LIMIT 5");
        $proximas->execute([$clienteId]);

        $totalMascotas = $db->prepare("SELECT COUNT(*) FROM mascota_dueno WHERE cliente_id = ?");
        $totalMascotas->execute([$clienteId]);

        $totalCitas = $db->prepare("SELECT COUNT(*) FROM citas WHERE cliente_id = ? AND estado = 'completada'");
        $totalCitas->execute([$clienteId]);

        Response::ok([
            'rol'            => 'cliente',
            'citas_proximas' => $proximas->fetchAll(),
            'total_mascotas' => (int)$totalMascotas->fetchColumn(),
            'citas_total'    => (int)$totalCitas->fetchColumn(),
        ]);
        return;
    }

    // Dashboard para GROOMER
    if ($rol === 'groomer') {
        $g = $db->prepare("SELECT id FROM groomers WHERE usuario_id = ?");
        $g->execute([$user['user_id']]);
        $groomerId = $g->fetchColumn();

        $citasHoy = $db->prepare("SELECT COUNT(*) FROM citas WHERE groomer_id = ? AND DATE(fecha_hora_inicio) = ? AND estado NOT IN ('cancelada','no_asistio')");
        $citasHoy->execute([$groomerId, $hoy]);

        $proximas = $db->prepare("SELECT ci.id, ci.fecha_hora_inicio, ci.estado,
                m.nombre as mascota, m.raza, s.nombre as servicio
            FROM citas ci
            JOIN mascotas m ON m.id = ci.mascota_id
            JOIN servicios s ON s.id = ci.servicio_id
            WHERE ci.groomer_id = ? AND ci.fecha_hora_inicio >= NOW()
              AND ci.estado NOT IN ('cancelada','no_asistio')
            ORDER BY ci.fecha_hora_inicio LIMIT 10");
        $proximas->execute([$groomerId]);

        Response::ok([
            'rol'            => 'groomer',
            'citas_hoy'      => (int)$citasHoy->fetchColumn(),
            'proximas_citas' => $proximas->fetchAll(),
        ]);
        return;
    }

    // Dashboard para ADMIN y RECEPCION
    $data = [];

    $s = $db->prepare("SELECT COUNT(*) FROM citas WHERE DATE(fecha_hora_inicio)=? AND estado NOT IN ('cancelada','no_asistio')");
    $s->execute([$hoy]); $data['citas_hoy'] = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COALESCE(SUM(total),0) FROM facturas WHERE DATE(created_at)=? AND estado='pagada'");
    $s->execute([$hoy]); $data['ingresos_hoy'] = (float)$s->fetchColumn();

    $s = $db->prepare("SELECT COALESCE(SUM(total),0) FROM facturas WHERE DATE_TRUNC('month',created_at)=DATE_TRUNC('month',NOW()) AND estado='pagada'");
    $s->execute(); $data['ingresos_mes'] = (float)$s->fetchColumn();

    $s = $db->query("SELECT COUNT(*) FROM clientes"); $data['total_clientes'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM mascotas"); $data['total_mascotas'] = (int)$s->fetchColumn();

    $s = $db->query("SELECT COUNT(*) FROM productos WHERE activo=TRUE AND stock <= stock_minimo");
    $data['productos_bajo_stock'] = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT ci.id, ci.fecha_hora_inicio, ci.estado,
            m.nombre as mascota, g.nombre as groomer, s.nombre as servicio
        FROM citas ci
        JOIN mascotas m ON m.id=ci.mascota_id
        JOIN groomers g ON g.id=ci.groomer_id
        JOIN servicios s ON s.id=ci.servicio_id
        WHERE ci.fecha_hora_inicio BETWEEN NOW() AND NOW() + INTERVAL '7 days'
          AND ci.estado NOT IN ('cancelada','no_asistio')
        ORDER BY ci.fecha_hora_inicio LIMIT 20");
    $s->execute(); $data['proximas_citas'] = $s->fetchAll();
    $data['rol'] = $rol;

    Response::ok($data);
}

    // GET /reportes/ocupacion-groomers?fecha_inicio=&fecha_fin=
    public static function ocupacionGroomers(): void {
        Auth::require(['admin','recepcion']);
        $db  = DB::get();
        $ini = Sanitizer::string($_GET['fecha_inicio'] ?? date('Y-m-01'));
        $fin = Sanitizer::string($_GET['fecha_fin']    ?? date('Y-m-d'));

        $stmt = $db->prepare("SELECT g.nombre as groomer,
                COUNT(ci.id) as total_citas,
                SUM(ci.duracion_estimada) as minutos_trabajados,
                COUNT(CASE WHEN ci.estado='completada' THEN 1 END) as completadas,
                COUNT(CASE WHEN ci.estado='cancelada'  THEN 1 END) as canceladas,
                COUNT(CASE WHEN ci.estado='no_asistio' THEN 1 END) as no_asistio
            FROM groomers g
            LEFT JOIN citas ci ON ci.groomer_id = g.id
                AND DATE(ci.fecha_hora_inicio) BETWEEN ? AND ?
            GROUP BY g.id, g.nombre ORDER BY total_citas DESC");
        $stmt->execute([$ini, $fin]);
        Response::ok($stmt->fetchAll());
    }

    // GET /reportes/top-servicios?limite=10
    public static function topServicios(): void {
        Auth::require(['admin','recepcion']);
        $limite = min(Sanitizer::int($_GET['limite'] ?? 10), 50);
        $stmt = DB::get()->prepare("SELECT s.nombre, COUNT(ci.id) as total_citas, COALESCE(SUM(s.precio_base),0) as ingreso_estimado
            FROM servicios s LEFT JOIN citas ci ON ci.servicio_id=s.id AND ci.estado='completada'
            GROUP BY s.id, s.nombre ORDER BY total_citas DESC LIMIT ?");
        $stmt->execute([$limite]);
        Response::ok($stmt->fetchAll());
    }

    // GET /reportes/top-productos?limite=10
    public static function topProductos(): void {
        Auth::require(['admin','recepcion']);
        $limite = min(Sanitizer::int($_GET['limite'] ?? 10), 50);
        $stmt = DB::get()->prepare("SELECT p.nombre, p.sku, SUM(dp.cantidad) as unidades_vendidas,
                SUM(dp.cantidad * dp.precio_unitario) as total_ventas
            FROM productos p JOIN detalle_pedido dp ON dp.producto_id=p.id
            JOIN pedidos pd ON pd.id=dp.pedido_id AND pd.estado IN ('pagado','entregado')
            GROUP BY p.id, p.nombre, p.sku ORDER BY unidades_vendidas DESC LIMIT ?");
        $stmt->execute([$limite]);
        Response::ok($stmt->fetchAll());
    }

    // GET /reportes/clientes-frecuentes?limite=10
    public static function clientesFrecuentes(): void {
        Auth::require(['admin','recepcion']);
        $limite = min(Sanitizer::int($_GET['limite'] ?? 10), 50);
        $stmt = DB::get()->prepare("SELECT c.nombre, c.telefono,
                COUNT(ci.id) as total_citas,
                COALESCE(SUM(f.total),0) as gasto_total
            FROM clientes c
            LEFT JOIN citas ci ON ci.cliente_id=c.id AND ci.estado='completada'
            LEFT JOIN facturas f ON f.cliente_id=c.id AND f.estado='pagada'
            GROUP BY c.id, c.nombre, c.telefono ORDER BY total_citas DESC, gasto_total DESC LIMIT ?");
        $stmt->execute([$limite]);
        Response::ok($stmt->fetchAll());
    }

    // GET /reportes/cancelaciones?fecha_inicio=&fecha_fin=
    public static function cancelaciones(): void {
        Auth::require(['admin','recepcion']);
        $ini = Sanitizer::string($_GET['fecha_inicio'] ?? date('Y-m-01'));
        $fin = Sanitizer::string($_GET['fecha_fin']    ?? date('Y-m-d'));

        $stmt = DB::get()->prepare("SELECT g.nombre as groomer,
                COUNT(CASE WHEN ci.estado='cancelada'  THEN 1 END) as canceladas,
                COUNT(CASE WHEN ci.estado='no_asistio' THEN 1 END) as no_asistio,
                COUNT(*) as total
            FROM groomers g LEFT JOIN citas ci ON ci.groomer_id=g.id AND DATE(ci.fecha_hora_inicio) BETWEEN ? AND ?
            GROUP BY g.id, g.nombre ORDER BY canceladas DESC");
        $stmt->execute([$ini, $fin]);
        Response::ok($stmt->fetchAll());
    }

    // GET /reportes/ingresos-diarios?fecha_inicio=&fecha_fin=
    public static function ingresosDiarios(): void {
        Auth::require(['admin']);
        $ini = Sanitizer::string($_GET['fecha_inicio'] ?? date('Y-m-01'));
        $fin = Sanitizer::string($_GET['fecha_fin']    ?? date('Y-m-d'));

        $stmt = DB::get()->prepare("SELECT DATE(created_at) as fecha,
                COUNT(*) as facturas, SUM(total) as total_dia
            FROM facturas WHERE DATE(created_at) BETWEEN ? AND ? AND estado='pagada'
            GROUP BY DATE(created_at) ORDER BY fecha");
        $stmt->execute([$ini, $fin]);
        Response::ok($stmt->fetchAll());
    }

    // GET /reportes/horas-pico?fecha_inicio=&fecha_fin=
    public static function horasPico(): void {
        Auth::require(['admin','recepcion']);
        $ini = Sanitizer::string($_GET['fecha_inicio'] ?? date('Y-m-01'));
        $fin = Sanitizer::string($_GET['fecha_fin']    ?? date('Y-m-d'));

        $stmt = DB::get()->prepare("SELECT EXTRACT(HOUR FROM fecha_hora_inicio) as hora,
                COUNT(*) as total_citas
            FROM citas WHERE DATE(fecha_hora_inicio) BETWEEN ? AND ? AND estado NOT IN ('cancelada','no_asistio')
            GROUP BY hora ORDER BY hora");
        $stmt->execute([$ini, $fin]);
        Response::ok($stmt->fetchAll());
    }
}
