<?php

namespace App\Models;

class Factura extends BaseModel
{
    protected string $table      = 'facturas';
    protected string $primaryKey = 'id';

    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*, c.nombre AS cliente_nombre, c.cuit AS cliente_cuit
             FROM facturas f
             LEFT JOIN clientes c ON f.cliente_id = c.id
             WHERE f.usuario_id = ?
             ORDER BY f.fecha DESC, f.numero DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*, c.nombre AS cliente_nombre, c.cuit AS cliente_cuit
             FROM facturas f
             LEFT JOIN clientes c ON f.cliente_id = c.id
             WHERE f.id = ? AND f.usuario_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
