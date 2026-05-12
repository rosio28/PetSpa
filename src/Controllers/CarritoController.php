<?php
// src/Controllers/CarritoController.php

class CarritoController {

    // POST /carrito/agregar
    public static function agregar(): void {
        $db = DB::get();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $productoId  = Sanitizer::int($body['producto_id'] ?? 0);
        $varianteId  = isset($body['variante_id']) ? Sanitizer::int($body['variante_id']) : null;
        $cantidad    = Sanitizer::int($body['cantidad'] ?? 1);
        $sessionToken = Sanitizer::string($body['session_token'] ?? '');

        if (!$productoId || $cantidad < 1) Response::error('producto_id y cantidad requeridos');

        // Verificar stock
        if ($varianteId) {
            $stk = $db->prepare("SELECT stock, precio_extra FROM variantes_producto WHERE id = ?");
            $stk->execute([$varianteId]);
            $variante = $stk->fetch();
            if (!$variante || $variante['stock'] < $cantidad) Response::error('Stock insuficiente para la variante');
        }
        $prod = $db->prepare("SELECT precio_base, stock FROM productos WHERE id = ? AND activo = TRUE");
        $prod->execute([$productoId]);
        $producto = $prod->fetch();
        if (!$producto) Response::error('Producto no disponible', 404);

        // Precio congelado
        $precioUnitario = $producto['precio_base'] + (float)($variante['precio_extra'] ?? 0);

        // Obtener o crear carrito
        $carritoId = self::obtenerCarrito($db, $sessionToken);

        // Ver si ya existe el item
        $existe = $db->prepare("SELECT id, cantidad FROM detalle_carrito WHERE carrito_id=? AND producto_id=? AND (variante_id=? OR (variante_id IS NULL AND ? IS NULL))");
        $existe->execute([$carritoId, $productoId, $varianteId, $varianteId]);
        $item = $existe->fetch();

        if ($item) {
            $db->prepare("UPDATE detalle_carrito SET cantidad = cantidad + ? WHERE id = ?")->execute([$cantidad, $item['id']]);
        } else {
            $db->prepare("INSERT INTO detalle_carrito (carrito_id, producto_id, variante_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)")
               ->execute([$carritoId, $productoId, $varianteId, $cantidad, $precioUnitario]);
        }

        Response::ok(['carrito_id' => $carritoId], 'Producto agregado al carrito');
    }

    // GET /carrito?session_token=xxx
    public static function ver(): void {
        $db = DB::get();
        $sessionToken = Sanitizer::string($_GET['session_token'] ?? '');

        $carrito = self::getCarritoPorToken($db, $sessionToken);
        if (!$carrito) { Response::ok(['items' => [], 'total' => 0]); return; }

        $items = $db->prepare("SELECT dc.*, p.nombre as producto, p.imagen_url,
                v.valor as variante_valor, v.atributo as variante_atributo,
                (dc.cantidad * dc.precio_unitario) as subtotal
            FROM detalle_carrito dc
            JOIN productos p ON p.id = dc.producto_id
            LEFT JOIN variantes_producto v ON v.id = dc.variante_id
            WHERE dc.carrito_id = ?");
        $items->execute([$carrito['id']]);
        $lista = $items->fetchAll();

        $total = array_sum(array_column($lista, 'subtotal'));
        Response::ok(['carrito_id' => $carrito['id'], 'items' => $lista, 'total' => $total]);
    }

    // DELETE /carrito/item/{id}
    public static function eliminarItem(int $itemId): void {
        DB::get()->prepare("DELETE FROM detalle_carrito WHERE id = ?")->execute([$itemId]);
        Response::ok(null, 'Item eliminado');
    }

    // POST /carrito/pedido  — convertir carrito en pedido WhatsApp/Telegram
    public static function crearPedido(): void {
        $db   = DB::get();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $sessionToken  = Sanitizer::string($body['session_token'] ?? '');
        $metodoContacto = Sanitizer::string($body['metodo_contacto'] ?? 'whatsapp');
        $clienteId      = isset($body['cliente_id']) ? Sanitizer::int($body['cliente_id']) : null;

        $carrito = self::getCarritoPorToken($db, $sessionToken);
        if (!$carrito) Response::error('Carrito no encontrado o expirado');

        $items = $db->prepare("SELECT dc.*, p.nombre as producto, (dc.cantidad * dc.precio_unitario) as subtotal
            FROM detalle_carrito dc JOIN productos p ON p.id = dc.producto_id WHERE dc.carrito_id = ?");
        $items->execute([$carrito['id']]);
        $lista = $items->fetchAll();
        if (!$lista) Response::error('El carrito está vacío');

        $subtotal = array_sum(array_column($lista, 'subtotal'));

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pedidos (carrito_id, cliente_id, metodo_contacto, subtotal, total, estado)
                VALUES (?,?,?,?,?,'pendiente') RETURNING id");
            $stmt->execute([$carrito['id'], $clienteId, $metodoContacto, $subtotal, $subtotal]);
            $pedidoId = $stmt->fetchColumn();

            foreach ($lista as $item) {
                $db->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, variante_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)")
                   ->execute([$pedidoId, $item['producto_id'], $item['variante_id'], $item['cantidad'], $item['precio_unitario']]);
            }

            $db->commit();

            // Generar link de WhatsApp
            $texto = "🐾 *Pedido Pet Spa #$pedidoId*\n";
            foreach ($lista as $item) $texto .= "- {$item['producto']} x{$item['cantidad']} = Bs {$item['subtotal']}\n";
            $texto .= "\n*Total: Bs $subtotal*\nPor favor confirmar.";

            $waLink = "https://wa.me/?text=" . urlencode($texto);

            AuditLog::log("Pedido #$pedidoId creado", 'pedidos', $pedidoId);
            Response::ok(['pedido_id' => $pedidoId, 'whatsapp_link' => $waLink, 'total' => $subtotal], 'Pedido creado');
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al crear pedido: ' . $e->getMessage(), 500);
        }
    }

    private static function obtenerCarrito(PDO $db, string $sessionToken): int {
        if ($sessionToken) {
            $stmt = $db->prepare("SELECT id FROM carritos WHERE session_token = ? AND expires_at > NOW()");
            $stmt->execute([$sessionToken]);
            $c = $stmt->fetch();
            if ($c) return $c['id'];
        }
        $token = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO carritos (session_token) VALUES (?) RETURNING id")->execute([$token]);
        return (int)$db->lastInsertId();
    }

    private static function getCarritoPorToken(PDO $db, string $token): array|false {
        if (!$token) return false;
        $stmt = $db->prepare("SELECT * FROM carritos WHERE session_token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: false;
    }
}
