<?php

declare(strict_types=1);

final class ConsultationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function list(): array
    {
        $stmt = $this->db->query(
            'SELECT c.id, c.empresa_id, e.razao_social AS empresa,
                    c.finalidade, DATE_FORMAT(c.criada_em, "%Y-%m-%d %H:%i:%s") AS criada_em
             FROM consultas c JOIN empresas e ON e.id = c.empresa_id
             ORDER BY c.criada_em DESC, c.id DESC LIMIT 25'
        );
        return $stmt->fetchAll();
    }

    public function create(int $companyId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO consultas (empresa_id, finalidade)
             VALUES (:id, :purpose)'
        );
        $stmt->execute([
            'id' => $companyId,
            'purpose' => 'Verificação de perfil empresarial',
        ]);
        return [
            'id' => (int) $this->db->lastInsertId(),
            'empresa_id' => $companyId,
            'mensagem' => 'Verificação registrada no histórico.',
        ];
    }
}
