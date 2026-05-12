<?php
// src/Controllers/ServiciosController.php

class ServiciosController {

    public static function listar(): void {
        Auth::require(['admin','recepcion','groomer','cliente']);
        $stmt = DB::get()->query("SELECT * FROM servicios WHERE activo = TRUE ORDER BY nombre");
        Response::ok($stmt->fetchAll());
    }

    public static function ver(int $id): void {
        Auth::require(['admin','recepcion','groomer','cliente']);
        $stmt = DB::get()->prepare("SELECT * FROM servicios WHERE id = ?");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if (!$s) Response::error('Servicio no encontrado', 404);
        Response::ok($s);
    }

    public static function crear(): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $nombre    = Sanitizer::string($body['nombre'] ?? '');
        $duracion  = Sanitizer::int($body['duracion_base_minutos'] ?? 0);
        $precio    = (float)($body['precio_base'] ?? 0);

        if (!$nombre || !$duracion || $precio < 0) Response::error('nombre, duracion_base_minutos y precio_base son requeridos');
        if ($duracion % 15 !== 0) Response::error('La duración debe ser múltiplo de 15 minutos');

        $db   = DB::get();
        $stmt = $db->prepare("INSERT INTO servicios 
            (nombre, descripcion, duracion_base_minutos, precio_base, permite_doble_booking, requiere_bloqueo_consecutivo, factor_tamano_raza, consumo_insumos)
            VALUES (?,?,?,?,?,?,?,?) RETURNING id");
        $stmt->execute([
            $nombre,
            Sanitizer::string($body['descripcion'] ?? ''),
            $duracion, $precio,
            (bool)($body['permite_doble_booking'] ?? false),
            (bool)($body['requiere_bloqueo_consecutivo'] ?? false),
            isset($body['factor_tamano_raza']) ? json_encode($body['factor_tamano_raza']) : null,
            isset($body['consumo_insumos'])    ? json_encode($body['consumo_insumos'])    : null,
        ]);
        $id = $stmt->fetchColumn();
        AuditLog::log("Servicio creado: $nombre", 'servicios', $id);
        Response::ok(['id' => $id], 'Servicio creado');
    }

    public static function actualizar(int $id): void {
        Auth::require(['admin']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db   = DB::get();

        $fields = []; $params = [];
        $strMap = ['nombre','descripcion'];
        foreach ($strMap as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }
        if (isset($body['duracion_base_minutos'])) {
            $d = Sanitizer::int($body['duracion_base_minutos']);
            if ($d % 15 !== 0) Response::error('Duración debe ser múltiplo de 15');
            $fields[] = "duracion_base_minutos = ?"; $params[] = $d;
        }
        if (isset($body['precio_base']))              { $fields[] = "precio_base = ?";              $params[] = (float)$body['precio_base']; }
        if (isset($body['permite_doble_booking']))     { $fields[] = "permite_doble_booking = ?";     $params[] = (bool)$body['permite_doble_booking']; }
        if (isset($body['factor_tamano_raza']))        { $fields[] = "factor_tamano_raza = ?";        $params[] = json_encode($body['factor_tamano_raza']); }
        if (isset($body['consumo_insumos']))           { $fields[] = "consumo_insumos = ?";           $params[] = json_encode($body['consumo_insumos']); }
        if (isset($body['activo']))                    { $fields[] = "activo = ?";                    $params[] = (bool)$body['activo']; }

        if (!$fields) Response::error('Nada que actualizar');
        $params[] = $id;
        $db->prepare("UPDATE servicios SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        AuditLog::log("Servicio #$id actualizado", 'servicios', $id);
        Response::ok(null, 'Servicio actualizado');
    }

    public static function eliminar(int $id): void {
        Auth::require(['admin']);
        DB::get()->prepare("UPDATE servicios SET activo = FALSE WHERE id = ?")->execute([$id]);
        AuditLog::log("Servicio #$id desactivado", 'servicios', $id);
        Response::ok(null, 'Servicio desactivado');
    }
}
