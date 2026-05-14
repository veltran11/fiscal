<?php

namespace App\Models;

class Cliente extends BaseModel
{
    protected string $table      = 'clientes';
    protected string $primaryKey = 'id';

    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM clientes WHERE usuario_id = ? ORDER BY nombre ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM clientes WHERE id = ? AND usuario_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
