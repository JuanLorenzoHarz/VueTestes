<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/CompanyRepository.php';
require_once __DIR__ . '/../src/SocietaryTreeRepository.php';
require_once __DIR__ . '/../src/ConsultationRepository.php';
require_once __DIR__ . '/../src/BureauContract.php';
require_once __DIR__ . '/../src/BureauIntegrationService.php';
require_once __DIR__ . '/../src/ConsolidatedReportService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && $path === '/api/health') {
    respond(200, ['status' => 'ok', 'project' => 'nexus-demo']);
}

try {
    $db = Database::connect();
    $companies = new CompanyRepository($db);
    $trees = new SocietaryTreeRepository($db);
    $consultations = new ConsultationRepository($db);
    $integrations = new BureauIntegrationService($db, getenv('MOCK_BUREAU_URL') ?: 'http://mock-bureaus:8000');
    $reports = new ConsolidatedReportService($companies, $integrations);

    if ($method === 'GET' && $path === '/api/empresas') {
        $search = trim((string) ($_GET['busca'] ?? ''));
        if (strlen($search) > 100) {
            respond(422, ['erro' => 'Pesquisa limitada a 100 caracteres.']);
        }
        respond(200, ['dados' => $companies->list($search)]);
    }

    // A seleção de uma empresa dispara a verificação e a auditoria em uma única ação de domínio.
    if ($method === 'POST' && preg_match('#^/api/empresas/([1-9][0-9]*)/verificacoes$#', $path, $matches)) {
        $id = (int) $matches[1];
        $company = $companies->get($id);
        if (!$company) {
            respond(404, ['erro' => 'Empresa não encontrada.']);
        }
        $consultation = $consultations->create($id);
        $execution = $integrations->execute($company, ['bureau-a' => 'normal', 'bureau-b' => 'normal']);
        respond(201, ['dados' => [
            'consulta' => $consultation,
            'fontes_atualizadas' => count(array_filter($execution['resultados'], static fn(array $item): bool => $item['situacao'] === 'sucesso')),
            'empresa' => $companies->get($id),
        ]]);
    }

    if ($method === 'GET' && preg_match('#^/api/empresas/([1-9][0-9]*)/relatorio$#', $path, $matches)) {
        $report = $reports->generate((int) $matches[1]);
        if ($report === null) {
            respond(404, ['erro' => 'Empresa não encontrada.']);
        }
        respond(200, ['dados' => $report]);
    }

    if ($method === 'GET' && preg_match('#^/api/empresas/([1-9][0-9]*)/arvore$#', $path, $matches)) {
        $tree = $trees->getByCompany((int) $matches[1]);
        if (!$tree) {
            respond(404, ['erro' => 'Empresa não encontrada.']);
        }
        respond(200, ['dados' => $tree]);
    }

    if ($method === 'GET' && preg_match('#^/api/empresas/([1-9][0-9]*)$#', $path, $matches)) {
        $company = $companies->get((int) $matches[1]);
        if (!$company) {
            respond(404, ['erro' => 'Empresa não encontrada.']);
        }
        respond(200, ['dados' => $company]);
    }

    if (preg_match('#^/api/empresas/([1-9][0-9]*)/integracoes$#', $path, $matches)) {
        $id = (int) $matches[1];
        $company = $companies->get($id);
        if (!$company) {
            respond(404, ['erro' => 'Empresa não encontrada.']);
        }
        if ($method === 'GET') {
            respond(200, ['dados' => $integrations->list($id)]);
        }
        if ($method === 'POST') {
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body) || !isset($body['cenarios']) || !is_array($body['cenarios'])) {
                respond(422, ['erro' => 'Informe os cenários dos dois bureaus.']);
            }
            try {
                respond(201, ['dados' => $integrations->execute($company, $body['cenarios'])]);
            } catch (InvalidArgumentException $e) {
                respond(422, ['erro' => $e->getMessage()]);
            }
        }
        respond(405, ['erro' => 'Método não permitido.']);
    }

    if ($method === 'GET' && $path === '/api/consultas') {
        respond(200, ['dados' => $consultations->list()]);
    }

    if ($method === 'POST' && $path === '/api/consultas') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || !isset($body['empresa_id']) ||
            filter_var($body['empresa_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            respond(422, ['erro' => 'Informe um empresa_id válido.']);
        }
        $id = (int) $body['empresa_id'];
        if (!$companies->get($id)) {
            respond(404, ['erro' => 'Empresa não encontrada.']);
        }
        respond(201, ['dados' => $consultations->create($id)]);
    }

    respond(404, ['erro' => 'Rota não encontrada.']);
} catch (PDOException $e) {
    error_log((string) $e);
    respond(503, ['erro' => 'Banco de dados indisponível.']);
} catch (Throwable $e) {
    error_log((string) $e);
    respond(500, ['erro' => 'Erro interno.']);
}
