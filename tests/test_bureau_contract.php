<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/src/BureauContract.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invalid(array $input): void
{
    try {
        BureauContract::validateScenarios($input);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Cenário inválido não foi recusado: ' . json_encode($input));
}

BureauContract::validateScenarios(['bureau-a' => 'normal', 'bureau-b' => 'timeout']);
BureauContract::validateScenarios(['bureau-b' => 'falha', 'bureau-a' => 'lento']);
invalid([]);
invalid(['bureau-a' => 'normal']);
invalid(['bureau-a' => 'normal', 'bureau-b' => 'lento', 'extra' => 'normal']);
invalid(['bureau-a' => 'normal', 'bureau-b' => 'invalido']);
invalid(['bureau-a' => 'normal', 'bureau-b' => 42]);

$valid = [
    'provedor' => 'bureau-a', 'codigo_demo' => 'DEMO-EMP-001',
    'score' => 740, 'pendencias' => 1, 'protestos' => 0,
    'atualizado_em' => '2026-09-21', 'dados_ficticios' => true,
];
verify(BureauContract::validateResponse($valid, 'bureau-a', 'DEMO-EMP-001'), 'Resposta válida recusada');
verify(BureauContract::validateResponse(array_replace($valid, ['score' => null]), 'bureau-a', 'DEMO-EMP-001'), 'Score ausente deveria ser permitido');
foreach ([
    ['provedor' => 'bureau-b'], ['codigo_demo' => 'DEMO-EMP-002'],
    ['dados_ficticios' => false], ['score' => -1], ['score' => 1001],
    ['score' => '740'], ['pendencias' => -1], ['protestos' => '0'],
    ['atualizado_em' => '2026-02-30'], ['atualizado_em' => 'invalid'],
] as $invalid) {
    verify(!BureauContract::validateResponse(array_replace($valid, $invalid), 'bureau-a', 'DEMO-EMP-001'), 'Resposta inválida aceita: ' . json_encode($invalid));
}
$missing = $valid;
unset($missing['score']);
verify(!BureauContract::validateResponse($missing, 'bureau-a', 'DEMO-EMP-001'), 'Score não fornecido deve falhar');
verify(!BureauContract::validateResponse('texto', 'bureau-a', 'DEMO-EMP-001'), 'Payload não-JSON deve falhar');

echo "OK: cenários, fornecedores, CPF/CNPJ demo, valores e datas do contrato de bureaus\n";
