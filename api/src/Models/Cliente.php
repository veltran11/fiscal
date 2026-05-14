<?php

namespace App\Models;

class Cliente extends BaseModel
{
    protected string $table      = 'clientes';
    protected string $primaryKey = 'id';

    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, b.nombre cond_iva
            FROM clientes a 
            left join cond_iva b on a.cond_iva_id = b.id
            WHERE a.usuario_id = ? ORDER BY a.nombre ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, b.nombre cond_iva
             FROM clientes a
             LEFT JOIN cond_iva b ON a.cond_iva_id = b.id
             WHERE a.id = ? AND a.usuario_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
