<?php

declare(strict_types=1);

final class ConsolidatedReportService
{
    public function __construct(
        private CompanyRepository $companies,
        private BureauIntegrationService $integrations
    ) {
    }

    public function generate(int $companyId): ?array
    {
        $company = $this->companies->get($companyId);
        if ($company === null) {
            return null;
        }

        $executions = $this->integrations->list($companyId);
        $lastExecution = $executions[0] ?? null;
        $lastResults = $lastExecution['resultados'] ?? [];
        $failedProviders = array_values(array_map(
            static fn(array $item): string => $item['provedor'],
            array_filter($lastResults, static fn(array $item): bool => $item['situacao'] !== 'sucesso')
        ));
        $activeLegal = array_values(array_filter(
            $company['registros_juridicos'],
            static fn(array $item): bool => strcasecmp(trim($item['situacao']), 'Em andamento') === 0
        ));

        $notices = [
            'Scores de fontes distintas não devem ser somados, promediados ou tratados como equivalentes.',
            'Pendências e protestos são contagens informadas por fonte; podem se referir aos mesmos eventos.',
            'Vínculos societários e registros jurídicos não implicam irregularidade, responsabilidade solidária ou inadimplência.',
            'Este relatório é informativo e não representa uma recomendação ou decisão de concessão de crédito.',
        ];
        if ($lastExecution === null) {
            $notices[] = 'Não houve uma verificação recente das fontes até a emissão deste relatório; confira as datas de referência.';
        } elseif ($failedProviders !== []) {
            $notices[] = 'Não foi possível atualizar as seguintes fontes: ' . implode(', ', $failedProviders)
                . '. Indicadores anteriores podem ter sido preservados; confira a data de referência de cada fonte.';
        }

        return [
            'referencia' => sprintf('NEXUS-%04d', $companyId),
            'gerado_em' => gmdate('Y-m-d\TH:i:s\Z'),
            'dados_ficticios' => true,
            'empresa' => $company,
            'sumario' => [
                'fontes_financeiras' => count($company['indicadores']),
                'socios' => count($company['socios']),
                'vinculos' => count($company['vinculos']),
                'registros_juridicos' => count($company['registros_juridicos']),
                'registros_em_andamento' => count($activeLegal),
                'fontes_com_pendencias' => count(array_filter($company['indicadores'], static fn(array $i): bool => (int) $i['pendencias'] > 0)),
                'fontes_com_protestos' => count(array_filter($company['indicadores'], static fn(array $i): bool => (int) $i['protestos'] > 0)),
            ],
            'integracao_recente' => $lastExecution,
            'avisos' => $notices,
        ];
    }
}
