<?php

declare(strict_types=1);

final class CompanyRepository
{
    public function __construct(private ?array $company) {}
    public function get(int $id): ?array { return $id === 1 ? $this->company : null; }
}

final class BureauIntegrationService
{
    public function __construct(private array $runs) {}
    public function list(int $id): array { return $id === 1 ? $this->runs : []; }
}

require_once __DIR__ . '/../api/src/ConsolidatedReportService.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FALHA: ' . $message);
    }
}

$company = [
    'id' => 1,
    'codigo_demo' => 'DEMO-EMP-001',
    'razao_social' => 'Aurora Tecnologia Ltda.',
    'socios' => [['nome' => 'A'], ['nome' => 'B']],
    'vinculos' => [['id' => 1]],
    'indicadores' => [
        ['fonte' => 'A', 'score' => 750, 'pendencias' => 2, 'protestos' => 0],
        ['fonte' => 'B', 'score' => 640, 'pendencias' => 0, 'protestos' => 1],
    ],
    'registros_juridicos' => [
        ['situacao' => 'Em andamento'], ['situacao' => 'Encerrado'],
    ],
];
$service = new ConsolidatedReportService(new CompanyRepository($company), new BureauIntegrationService([]));
$report = $service->generate(1);
check($report !== null, 'deve retornar relatório existente');
check($report['dados_ficticios'] === true, 'marca de simulação');
check($report['referencia'] === 'NEXUS-0001', 'referência determinística');
check($report['sumario']['socios'] === 2, 'contagem de sócios');
check($report['sumario']['fontes_com_pendencias'] === 1, 'não soma pendências entre fontes');
check($report['sumario']['fontes_com_protestos'] === 1, 'não soma protestos entre fontes');
check($report['sumario']['registros_em_andamento'] === 1, 'contagem de processos em andamento');
check($report['integracao_recente'] === null, 'sem execução anterior');
check(count($report['avisos']) >= 5, 'avisa sobre falta de consulta REST');
check($service->generate(999) === null, 'empresa inexistente devolve null');

$run = [[
    'id' => 9, 'resultados' => [
        ['provedor' => 'bureau-a', 'situacao' => 'erro_http'],
        ['provedor' => 'bureau-b', 'situacao' => 'sucesso'],
    ],
]];
$service = new ConsolidatedReportService(new CompanyRepository($company), new BureauIntegrationService($run));
$report = $service->generate(1);
check($report['integracao_recente']['id'] === 9, 'utiliza última execução');
check(str_contains(implode(' ', $report['avisos']), 'bureau-a'), 'alerta sobre provedor com falha');
check(!array_key_exists('score_consolidado', $report), 'não inventa score único');

echo "OK: relatório sem integrações, totais por fonte, último erro e empresa inexistente\n";
