<?php
// src/Controllers/FacturasController.php

class FacturasController {

    // POST /facturas  — generar factura para una cita o pedido
    public static function crear(): void {
        Auth::require(['admin','recepcion']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $citaId   = isset($body['cita_id'])   ? Sanitizer::int($body['cita_id'])   : null;
        $pedidoId = isset($body['pedido_id']) ? Sanitizer::int($body['pedido_id']) : null;
        $clienteId = Sanitizer::int($body['cliente_id'] ?? 0);
        $metodo   = Sanitizer::string($body['metodo_pago'] ?? 'efectivo');
        $items    = $body['items'] ?? [];   // [{descripcion, cantidad, precio_unitario}]

        if (!$clienteId || !$items) Response::error('cliente_id e items son requeridos');

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float)($item['precio_unitario'] ?? 0) * (int)($item['cantidad'] ?? 1);
        }
        $impuesto = round($subtotal * 0.13, 2); // 13% IVA Bolivia
        $total    = round($subtotal + $impuesto, 2);

        $db = DB::get();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO facturas (cita_id, pedido_id, cliente_id, subtotal, impuesto, total, metodo_pago, estado)
                VALUES (?,?,?,?,?,?,?,'pendiente') RETURNING id, numero");
            $stmt->execute([$citaId, $pedidoId, $clienteId, $subtotal, $impuesto, $total, $metodo]);
            $row = $stmt->fetch();
            $facturaId = $row['id'];

            foreach ($items as $item) {
                $desc  = Sanitizer::string($item['descripcion'] ?? '');
                $qty   = Sanitizer::int($item['cantidad'] ?? 1);
                $precio = (float)($item['precio_unitario'] ?? 0);
                $db->prepare("INSERT INTO detalle_factura (factura_id, descripcion, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)")
                   ->execute([$facturaId, $desc, $qty, $precio, $qty * $precio]);
            }

            $db->commit();
            AuditLog::log("Factura #$facturaId creada. Total: $total", 'facturas', $facturaId);
            Response::ok(['id' => $facturaId, 'numero' => $row['numero'], 'total' => $total], 'Factura generada');
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al crear factura: ' . $e->getMessage(), 500);
        }
    }

    // GET /facturas/{id}
    public static function ver(int $id): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $db   = DB::get();
        $stmt = $db->prepare("SELECT f.*, c.nombre as cliente_nombre, c.ci, c.direccion
            FROM facturas f JOIN clientes c ON c.id = f.cliente_id WHERE f.id = ?");
        $stmt->execute([$id]);
        $factura = $stmt->fetch();
        if (!$factura) Response::error('Factura no encontrada', 404);

        // Verificar acceso si es cliente
        if ($user['rol'] === 'cliente') {
            $chk = $db->prepare("SELECT 1 FROM clientes WHERE id=? AND usuario_id=?");
            $chk->execute([$factura['cliente_id'], $user['user_id']]);
            if (!$chk->fetch()) Response::error('Sin acceso a esta factura', 403);
        }

        $detalle = $db->prepare("SELECT * FROM detalle_factura WHERE factura_id = ?");
        $detalle->execute([$id]);
        $factura['items'] = $detalle->fetchAll();

        $pagos = $db->prepare("SELECT * FROM pagos WHERE factura_id = ?");
        $pagos->execute([$id]);
        $factura['pagos'] = $pagos->fetchAll();

        Response::ok($factura);
    }

    // POST /facturas/{id}/pago  — registrar pago (permite pagos parciales)
    public static function registrarPago(int $id): void {
        Auth::require(['admin','recepcion']);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $monto = (float)($body['monto'] ?? 0);
        $metodo = Sanitizer::string($body['metodo'] ?? 'efectivo');
        $ref    = Sanitizer::string($body['referencia'] ?? '');

        if ($monto <= 0) Response::error('Monto inválido');

        $db = DB::get();
        $stmt = $db->prepare("SELECT total, (SELECT COALESCE(SUM(monto),0) FROM pagos WHERE factura_id=? AND estado='completado') as pagado FROM facturas WHERE id=?");
        $stmt->execute([$id, $id]);
        $factura = $stmt->fetch();
        if (!$factura) Response::error('Factura no encontrada', 404);

        $pendiente = (float)$factura['total'] - (float)$factura['pagado'];
        if ($monto > $pendiente + 0.01) Response::error("Monto excede lo pendiente (Bs $pendiente)");

        $db->prepare("INSERT INTO pagos (factura_id, monto, metodo, referencia, estado) VALUES (?,?,?,?,'completado')")
           ->execute([$id, $monto, $metodo, $ref]);

        // Si pagado completo → actualizar estado factura
        $nuevoPagado = (float)$factura['pagado'] + $monto;
        if ($nuevoPagado >= (float)$factura['total'] - 0.01) {
            $db->prepare("UPDATE facturas SET estado='pagada' WHERE id=?")->execute([$id]);
        }

        AuditLog::log("Pago registrado en factura #$id: Bs $monto", 'pagos', $id);
        Response::ok(null, 'Pago registrado');
    }

    // GET /facturas?cliente_id=x  — listar facturas
    public static function listar(): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $db   = DB::get();

        if ($user['rol'] === 'cliente') {
            $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id=?");
            $c->execute([$user['user_id']]);
            $clienteId = $c->fetchColumn();
            $stmt = $db->prepare("SELECT f.*, c.nombre as cliente_nombre FROM facturas f JOIN clientes c ON c.id=f.cliente_id WHERE f.cliente_id=? ORDER BY f.created_at DESC");
            $stmt->execute([$clienteId]);
        } else {
            $where = []; $params = [];
            if (!empty($_GET['cliente_id'])) { $where[] = "f.cliente_id=?"; $params[] = Sanitizer::int($_GET['cliente_id']); }
            if (!empty($_GET['estado']))     { $where[] = "f.estado=?";     $params[] = Sanitizer::string($_GET['estado']); }
            $sql = "SELECT f.*, c.nombre as cliente_nombre FROM facturas f JOIN clientes c ON c.id=f.cliente_id"
                 . ($where ? " WHERE " . implode(' AND ', $where) : '')
                 . " ORDER BY f.created_at DESC LIMIT 100";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        Response::ok($stmt->fetchAll());
    }
}
