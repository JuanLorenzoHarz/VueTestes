<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    response(405, ['erro' => 'Método não permitido.']);
    return;
}

if ($path === '/health') {
    response(200, ['status' => 'ok', 'simulado' => true]);
    return;
}

if (!preg_match('#^/v1/(bureau-a|bureau-b)/empresas/(DEMO-EMP-00[1-3])$#', (string) $path, $matches)) {
    response(404, ['erro' => 'Registro fictício não encontrado.']);
    return;
}

[, $bureau, $codigo] = $matches;
$cenario = (string) ($_GET['cenario'] ?? 'normal');
if (!in_array($cenario, ['normal', 'lento', 'falha', 'timeout'], true)) {
    response(422, ['erro' => 'Cenário desconhecido.']);
    return;
}

if ($cenario === 'lento') {
    usleep(700000);
} elseif ($cenario === 'timeout') {
    usleep(3000000);
} elseif ($cenario === 'falha') {
    response(503, ['erro' => 'Bureau de demonstração temporariamente indisponível.']);
    return;
}

$fixtures = [
    'bureau-a' => [
        'DEMO-EMP-001' => [740, 1, 0, '2026-09-21'],
        'DEMO-EMP-002' => [510, 3, 1, '2026-09-22'],
        'DEMO-EMP-003' => [865, 0, 0, '2026-09-23'],
    ],
    'bureau-b' => [
        'DEMO-EMP-001' => [698, 0, 0, '2026-09-20'],
        'DEMO-EMP-002' => [575, 2, 0, '2026-09-19'],
        'DEMO-EMP-003' => [null, 0, 0, '2026-09-24'],
    ],
];

[$score, $pendencias, $protestos, $data] = $fixtures[$bureau][$codigo];
response(200, [
    'provedor' => $bureau,
    'codigo_demo' => $codigo,
    'score' => $score,
    'pendencias' => $pendencias,
    'protestos' => $protestos,
    'atualizado_em' => $data,
    'dados_ficticios' => true,
]);
