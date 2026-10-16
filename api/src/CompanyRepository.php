<?php

declare(strict_types=1);

final class CompanyRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function list(string $search = ''): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, codigo_demo, razao_social, setor, cidade, uf, situacao
             FROM empresas
             WHERE razao_social LIKE :name OR codigo_demo LIKE :code
             ORDER BY razao_social LIMIT 30'
        );
        $pattern = '%' . $search . '%';
        $stmt->execute(['name' => $pattern, 'code' => $pattern]);
        return $stmt->fetchAll();
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, codigo_demo, razao_social, setor, cidade, uf, situacao
             FROM empresas WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $company = $stmt->fetch();
        if (!$company) {
            return null;
        }

        $shareholders = $this->db->prepare(
            'SELECT p.id, p.nome_ficticio AS nome, s.percentual
             FROM participacoes_societarias s
             INNER JOIN pessoas p ON p.id = s.pessoa_id
             WHERE s.empresa_id = :id ORDER BY s.percentual DESC'
        );
        $shareholders->execute(['id' => $id]);

        $relationships = $this->db->prepare(
            'SELECT v.id, v.tipo, v.descricao, e.id AS empresa_relacionada_id,
                    e.razao_social AS empresa_relacionada
             FROM vinculos_empresariais v
             INNER JOIN empresas e ON e.id = v.empresa_destino_id
             WHERE v.empresa_origem_id = :origin
             UNION ALL
             SELECT v.id, v.tipo, v.descricao, e.id AS empresa_relacionada_id,
                    e.razao_social AS empresa_relacionada
             FROM vinculos_empresariais v
             INNER JOIN empresas e ON e.id = v.empresa_origem_id
             WHERE v.empresa_destino_id = :destination'
        );
        $relationships->execute(['origin' => $id, 'destination' => $id]);

        $signals = $this->db->prepare(
            'SELECT i.id, f.nome AS fonte, i.score, i.pendencias,
                    i.protestos, DATE_FORMAT(i.atualizado_em, "%Y-%m-%d") AS atualizado_em
             FROM indicadores_financeiros i
             INNER JOIN fontes f ON f.id = i.fonte_id
             WHERE i.empresa_id = :id ORDER BY f.nome'
        );
        $signals->execute(['id' => $id]);

        $legal = $this->db->prepare(
            'SELECT r.id, r.tipo, r.resumo, r.situacao, f.nome AS fonte,
                    DATE_FORMAT(r.data_registro, "%Y-%m-%d") AS data_registro
             FROM registros_juridicos r
             INNER JOIN fontes f ON f.id = r.fonte_id
             WHERE r.empresa_id = :id ORDER BY r.data_registro DESC'
        );
        $legal->execute(['id' => $id]);

        $company['socios'] = $shareholders->fetchAll();
        $company['vinculos'] = $relationships->fetchAll();
        $company['indicadores'] = $signals->fetchAll();
        $company['registros_juridicos'] = $legal->fetchAll();
        return $company;
    }
}
