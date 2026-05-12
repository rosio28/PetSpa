<?php
// src/Controllers/ClientesController.php

class ClientesController {

    // GET /clientes
    public static function listar(): void {
        Auth::require(['admin','recepcion']);
        $db = DB::get();
        $where = []; $params = [];
        if (!empty($_GET['buscar'])) {
            $where[] = "(c.nombre ILIKE ? OR c.telefono LIKE ? OR c.ci LIKE ? OR u.email ILIKE ?)";
            $q = '%' . Sanitizer::string($_GET['buscar']) . '%';
            $params = array_merge($params, [$q, $q, $q, $q]);
        }
        $sql = "SELECT c.*, u.email, u.estado as cuenta_activa FROM clientes c JOIN usuarios u ON u.id=c.usuario_id"
             . ($where ? " WHERE " . implode(' AND ', $where) : "")
             . " ORDER BY c.nombre LIMIT 100";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::ok($stmt->fetchAll());
    }

    // GET /clientes/{id}
    public static function ver(int $id): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $db   = DB::get();

        // Cliente solo puede ver su propio perfil
        if ($user['rol'] === 'cliente') {
            $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id=?");
            $c->execute([$user['user_id']]);
            $miId = (int)$c->fetchColumn();
            if ($miId !== $id) Response::error('Sin acceso', 403);
        }

        $stmt = $db->prepare("SELECT c.*, u.email, u.estado as cuenta_activa, u.ultimo_acceso
            FROM clientes c JOIN usuarios u ON u.id=c.usuario_id WHERE c.id=?");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();
        if (!$cliente) Response::error('Cliente no encontrado', 404);

        // Sus mascotas
        $m = $db->prepare("SELECT m.*, md.es_principal FROM mascotas m JOIN mascota_dueno md ON md.mascota_id=m.id WHERE md.cliente_id=?");
        $m->execute([$id]);
        $cliente['mascotas'] = $m->fetchAll();

        Response::ok($cliente);
    }

    // PUT /clientes/{id}  — cliente edita su perfil o admin edita cualquiera
    public static function actualizar(int $id): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $db   = DB::get();

        if ($user['rol'] === 'cliente') {
            $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id=?");
            $c->execute([$user['user_id']]);
            if ((int)$c->fetchColumn() !== $id) Response::error('Sin acceso', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];

        foreach (['nombre','telefono','ci','direccion','canal_notif','horario_pref'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = Sanitizer::string($body[$f]); }
        }

        if ($fields) {
            $params[] = $id;
            $db->prepare("UPDATE clientes SET " . implode(', ', $fields) . " WHERE id=?")->execute($params);
        }
        AuditLog::log("Cliente #$id actualizado", 'clientes', $id);
        Response::ok(null, 'Perfil actualizado');
    }

    // GET /clientes/{id}/historial  — historial de citas
    public static function historial(int $id): void {
        $user = Auth::require(['admin','recepcion','cliente']);
        $db   = DB::get();

        if ($user['rol'] === 'cliente') {
            $c = $db->prepare("SELECT id FROM clientes WHERE usuario_id=?");
            $c->execute([$user['user_id']]);
            if ((int)$c->fetchColumn() !== $id) Response::error('Sin acceso', 403);
        }

        $stmt = $db->prepare("SELECT ci.id, ci.fecha_hora_inicio, ci.estado,
                m.nombre as mascota, s.nombre as servicio, g.nombre as groomer,
                s.precio_base
            FROM citas ci
            JOIN mascotas m ON m.id=ci.mascota_id
            JOIN servicios s ON s.id=ci.servicio_id
            JOIN groomers g ON g.id=ci.groomer_id
            WHERE ci.cliente_id=? ORDER BY ci.fecha_hora_inicio DESC");
        $stmt->execute([$id]);
        Response::ok($stmt->fetchAll());
    }
}
