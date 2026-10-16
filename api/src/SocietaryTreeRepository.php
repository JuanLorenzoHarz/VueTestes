<?php

declare(strict_types=1);

final class SocietaryTreeRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function getByCompany(int $companyId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, codigo_demo, razao_social, setor, cidade, uf, situacao
             FROM empresas WHERE id = :id'
        );
        $statement->execute(['id' => $companyId]);
        $company = $statement->fetch();

        if (!$company) {
            return null;
        }

        $owners = $this->db->prepare(
            'SELECT p.id, p.nome_ficticio AS nome, ps.percentual
             FROM participacoes_societarias ps
             INNER JOIN pessoas p ON p.id = ps.pessoa_id
             WHERE ps.empresa_id = :id
             ORDER BY ps.percentual DESC, p.nome_ficticio'
        );
        $owners->execute(['id' => $companyId]);
        $shareholders = $owners->fetchAll();

        $holdings = $this->db->prepare(
            'SELECT ps.pessoa_id, e.id, e.codigo_demo, e.razao_social,
                    e.setor, e.cidade, e.uf, e.situacao, ps.percentual
             FROM participacoes_societarias ps
             INNER JOIN empresas e ON e.id = ps.empresa_id
             WHERE ps.pessoa_id IN (
                 SELECT pessoa_id FROM participacoes_societarias WHERE empresa_id = :company_id
             ) AND e.id <> :other_company_id
             ORDER BY e.razao_social'
        );
        $holdings->execute(['company_id' => $companyId, 'other_company_id' => $companyId]);
        $byOwner = [];
        foreach ($holdings->fetchAll() as $holding) {
            $personId = (int) $holding['pessoa_id'];
            unset($holding['pessoa_id']);
            $byOwner[$personId][] = $holding;
        }

        foreach ($shareholders as &$shareholder) {
            $shareholder['outras_empresas'] = $byOwner[(int) $shareholder['id']] ?? [];
        }
        unset($shareholder);

        return [
            'empresa' => $company,
            'socios' => $shareholders,
            'origem' => 'Base fictícia local — participações societárias',
        ];
    }
}
