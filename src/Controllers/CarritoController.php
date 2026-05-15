<?php
// src/Controllers/CarritoController.php

class CarritoController {

    // POST /carrito/agregar
    public static function agregar(): void {
        $db   = DB::get();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $productoId   = Sanitizer::int($body['producto_id'] ?? 0);
        $varianteId   = isset($body['variante_id']) ? Sanitizer::int($body['variante_id']) : null;
        $cantidad     = Sanitizer::int($body['cantidad'] ?? 1);
        $sessionToken = Sanitizer::string($body['session_token'] ?? '');

        if (!$productoId || $cantidad < 1) Response::error('producto_id y cantidad requeridos');

        // Verificar producto
        $prod = $db->prepare("SELECT precio_base, stock, nombre FROM productos WHERE id = ? AND activo = TRUE");
        $prod->execute([$productoId]);
        $producto = $prod->fetch();
        if (!$producto) Response::error('Producto no disponible', 404);

        $precioExtra = 0;
        $variante    = null;
        if ($varianteId) {
            $stk = $db->prepare("SELECT stock, precio_extra FROM variantes_producto WHERE id = ? AND producto_id = ?");
            $stk->execute([$varianteId, $productoId]);
            $variante = $stk->fetch();
            if (!$variante) Response::error('Variante no encontrada', 404);
            if ($variante['stock'] < $cantidad) Response::error('Stock insuficiente para la variante');
            $precioExtra = (float)$variante['precio_extra'];
        }

        if ($producto['stock'] < $cantidad) Response::error('Stock insuficiente. Disponible: ' . $producto['stock']);

        $precioUnitario = (float)$producto['precio_base'] + $precioExtra;

        // Obtener o crear carrito
        $carritoId = self::obtenerCarrito($db, $sessionToken);

        // Ver si ya existe el item
        $qExiste = $varianteId
            ? "SELECT id, cantidad FROM detalle_carrito WHERE carrito_id=? AND producto_id=? AND variante_id=?"
            : "SELECT id, cantidad FROM detalle_carrito WHERE carrito_id=? AND producto_id=? AND variante_id IS NULL";
        $params = $varianteId ? [$carritoId, $productoId, $varianteId] : [$carritoId, $productoId];

        $existe = $db->prepare($qExiste);
        $existe->execute($params);
        $item = $existe->fetch();

        if ($item) {
            $db->prepare("UPDATE detalle_carrito SET cantidad = cantidad + ? WHERE id = ?")
               ->execute([$cantidad, $item['id']]);
        } else {
            $db->prepare("INSERT INTO detalle_carrito (carrito_id, producto_id, variante_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)")
               ->execute([$carritoId, $productoId, $varianteId, $cantidad, $precioUnitario]);
        }

        Response::ok(['carrito_id' => $carritoId], '"' . $producto['nombre'] . '" agregado al carrito');
    }

    // GET /carrito?session_token=xxx
    public static function ver(): void {
        $db           = DB::get();
        $sessionToken = Sanitizer::string($_GET['session_token'] ?? '');

        $carrito = self::getCarritoPorToken($db, $sessionToken);
        if (!$carrito) {
            Response::ok(['items' => [], 'total' => 0, 'cantidad_items' => 0]);
            return;
        }

        $stmt = $db->prepare("SELECT dc.id, dc.cantidad, dc.precio_unitario,
                p.id as producto_id, p.nombre as producto, p.imagen_url,
                v.id as variante_id, v.valor as variante_valor, v.atributo as variante_atributo,
                (dc.cantidad * dc.precio_unitario) as subtotal
            FROM detalle_carrito dc
            JOIN productos p ON p.id = dc.producto_id
            LEFT JOIN variantes_producto v ON v.id = dc.variante_id
            WHERE dc.carrito_id = ?
            ORDER BY dc.id");
        $stmt->execute([$carrito['id']]);
        $lista = $stmt->fetchAll();

        $total = array_sum(array_column($lista, 'subtotal'));
        Response::ok([
            'carrito_id'    => $carrito['id'],
            'session_token' => $sessionToken,
            'items'         => $lista,
            'total'         => round($total, 2),
            'cantidad_items'=> count($lista),
        ]);
    }

    // DELETE /carrito/item/{id}
    public static function eliminarItem(int $itemId): void {
        DB::get()->prepare("DELETE FROM detalle_carrito WHERE id = ?")->execute([$itemId]);
        Response::ok(null, 'Item eliminado del carrito');
    }

    // POST /carrito/pedido
    public static function crearPedido(): void {
        $db   = DB::get();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $sessionToken   = Sanitizer::string($body['session_token'] ?? '');
        $metodoContacto = Sanitizer::string($body['metodo_contacto'] ?? 'whatsapp');
        $clienteId      = isset($body['cliente_id']) ? Sanitizer::int($body['cliente_id']) : null;

        $carrito = self::getCarritoPorToken($db, $sessionToken);
        if (!$carrito) Response::error('Carrito no encontrado o expirado');

        $stmt = $db->prepare("SELECT dc.id, dc.cantidad, dc.precio_unitario, p.nombre as producto,
                p.id as producto_id, dc.variante_id,
                (dc.cantidad * dc.precio_unitario) as subtotal
            FROM detalle_carrito dc
            JOIN productos p ON p.id = dc.producto_id
            WHERE dc.carrito_id = ?");
        $stmt->execute([$carrito['id']]);
        $lista = $stmt->fetchAll();

        if (!$lista) Response::error('El carrito está vacío');

        $subtotal = array_sum(array_column($lista, 'subtotal'));
        $subtotal = round($subtotal, 2);

        $db->beginTransaction();
        try {
            $ins = $db->prepare("INSERT INTO pedidos (carrito_id, cliente_id, metodo_contacto, subtotal, total, estado)
                VALUES (?,?,?,?,?,'pendiente') RETURNING id");
            $ins->execute([$carrito['id'], $clienteId, $metodoContacto, $subtotal, $subtotal]);
            $pedidoId = (int)$ins->fetchColumn();

            foreach ($lista as $item) {
                $db->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, variante_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)")
                   ->execute([$pedidoId, $item['producto_id'], $item['variante_id'], $item['cantidad'], $item['precio_unitario']]);
            }

            $db->commit();

            // Generar mensaje para WhatsApp
            $texto = "🐾 *Pedido PawSpa #$pedidoId*\n\n";
            foreach ($lista as $item) {
                $texto .= "▸ {$item['producto']} × {$item['cantidad']} = Bs {$item['subtotal']}\n";
            }
            $texto .= "\n*Total: Bs $subtotal*\n\nPor favor confirmar disponibilidad 🙏";

            $waLink = "https://wa.me/?text=" . urlencode($texto);

            AuditLog::log("Pedido #$pedidoId creado. Total: $subtotal", 'pedidos', $pedidoId);
            Response::ok([
                'pedido_id'     => $pedidoId,
                'total'         => $subtotal,
                'whatsapp_link' => $waLink,
                'items'         => count($lista),
            ], 'Pedido creado correctamente');

        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al crear pedido: ' . $e->getMessage(), 500);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Obtiene ID del carrito por token o crea uno nuevo.
     * NOTA: Usa RETURNING id para compatibilidad con PostgreSQL.
     */
    private static function obtenerCarrito(PDO $db, string $sessionToken): int {
        if ($sessionToken) {
            $stmt = $db->prepare("SELECT id FROM carritos WHERE session_token = ? AND expires_at > NOW()");
            $stmt->execute([$sessionToken]);
            $c = $stmt->fetch();
            if ($c) return (int)$c['id'];
        }

        // Crear nuevo carrito con token generado
        $token = $sessionToken ?: ('cart_' . bin2hex(random_bytes(16)));
        $ins   = $db->prepare("INSERT INTO carritos (session_token, expires_at) VALUES (?, NOW() + INTERVAL '7 days') RETURNING id");
        $ins->execute([$token]);
        return (int)$ins->fetchColumn();
    }

    private static function getCarritoPorToken(PDO $db, string $token): array|false {
        if (!$token) return false;
        $stmt = $db->prepare("SELECT * FROM carritos WHERE session_token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: false;
    }
}
