<?php
// src/Controllers/ProductosController.php

class ProductosController {

    public static function listar(): void {
        Auth::require(['admin','recepcion','groomer','cliente']);
        $db = DB::get();
        $where = []; $params = [];

        if (!empty($_GET['categoria_id'])) { $where[] = "p.categoria_id = ?"; $params[] = Sanitizer::int($_GET['categoria_id']); }
        if (!empty($_GET['buscar']))       { $where[] = "p.nombre ILIKE ?";  $params[] = '%' . Sanitizer::string($_GET['buscar']) . '%'; }

        $sql = "SELECT p.*, c.nombre as categoria, 
                    (SELECT json_agg(v) FROM variantes_producto v WHERE v.producto_id = p.id) as variantes
                FROM productos p
                LEFT JOIN categorias_producto c ON c.id = p.categoria_id
                WHERE p.activo = TRUE"
              . ($where ? " AND " . implode(' AND ', $where) : '')
              . " ORDER BY p.nombre";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::ok($stmt->fetchAll());
    }

    public static function ver(int $id): void {
        Auth::require(['admin','recepcion','groomer','cliente']);
        $stmt = DB::get()->prepare("SELECT p.*, 
            (SELECT json_agg(v) FROM variantes_producto v WHERE v.producto_id = p.id) as variantes
            FROM productos p WHERE p.id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) Response::error('Producto no encontrado', 404);
        Response::ok($p);
    }

    public static function crear(): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $nombre  = Sanitizer::string($body['nombre'] ?? '');
        $sku     = Sanitizer::string($body['sku'] ?? '');
        $precio  = (float)($body['precio_base'] ?? -1);

        if (!$nombre || !$sku || $precio < 0) Response::error('nombre, sku y precio_base son requeridos');

        $db = DB::get();
        // SKU único
        $chk = $db->prepare("SELECT id FROM productos WHERE sku = ?");
        $chk->execute([$sku]);
        if ($chk->fetch()) Response::error('SKU ya existe', 409);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO productos 
                (nombre, descripcion, precio_base, stock, stock_minimo, sku, imagen_url, categoria_id)
                VALUES (?,?,?,?,?,?,?,?) RETURNING id");
            $stmt->execute([
                $nombre,
                Sanitizer::string($body['descripcion'] ?? ''),
                $precio,
                Sanitizer::int($body['stock'] ?? 0),
                Sanitizer::int($body['stock_minimo'] ?? 5),
                $sku,
                Sanitizer::string($body['imagen_url'] ?? ''),
                isset($body['categoria_id']) ? Sanitizer::int($body['categoria_id']) : null,
            ]);
            $prodId = $stmt->fetchColumn();

            // Variantes
            if (!empty($body['variantes']) && is_array($body['variantes'])) {
                foreach ($body['variantes'] as $v) {
                    $skuV = Sanitizer::string($v['sku_variante'] ?? '');
                    if (!$skuV) continue;
                    $db->prepare("INSERT INTO variantes_producto (producto_id, atributo, valor, precio_extra, stock, sku_variante) VALUES (?,?,?,?,?,?)")
                       ->execute([
                           $prodId,
                           Sanitizer::string($v['atributo'] ?? ''),
                           Sanitizer::string($v['valor'] ?? ''),
                           (float)($v['precio_extra'] ?? 0),
                           Sanitizer::int($v['stock'] ?? 0),
                           $skuV
                       ]);
                }
            }

            $db->commit();
            AuditLog::log("Producto creado: $nombre (SKU $sku)", 'productos', $prodId);
            Response::ok(['id' => $prodId], 'Producto creado');
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Error al crear producto: ' . $e->getMessage(), 500);
        }
    }

    public static function actualizar(int $id): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db   = DB::get();

        $fields = []; $params = [];
        foreach (['nombre','descripcion','imagen_url'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }
        if (isset($body['precio_base']))  { $fields[] = "precio_base = ?";  $params[] = (float)$body['precio_base']; }
        if (isset($body['stock']))        { $fields[] = "stock = ?";        $params[] = Sanitizer::int($body['stock']); }
        if (isset($body['stock_minimo'])) { $fields[] = "stock_minimo = ?"; $params[] = Sanitizer::int($body['stock_minimo']); }
        if (isset($body['categoria_id'])) { $fields[] = "categoria_id = ?"; $params[] = Sanitizer::int($body['categoria_id']); }
        if (isset($body['activo']))       { $fields[] = "activo = ?";       $params[] = (bool)$body['activo']; }

        if ($fields) {
            $params[] = $id;
            $db->prepare("UPDATE productos SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        AuditLog::log("Producto #$id actualizado", 'productos', $id);
        Response::ok(null, 'Producto actualizado');
    }

    // GET /productos/bajo-stock  — inventario crítico
    public static function bajoStock(): void {
        Auth::require(['admin','recepcion']);
        $stmt = DB::get()->query("SELECT id, nombre, sku, stock, stock_minimo FROM productos WHERE activo=TRUE AND stock <= stock_minimo ORDER BY stock ASC");
        Response::ok($stmt->fetchAll());
    }
}
