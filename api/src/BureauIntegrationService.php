<?php

declare(strict_types=1);

final class BureauIntegrationService
{
    public function __construct(private PDO $db, private string $baseUrl)
    {
    }

    public function list(int $companyId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, empresa_id, DATE_FORMAT(criada_em, "%Y-%m-%d %H:%i:%s") AS criada_em
             FROM integracao_execucoes WHERE empresa_id = :id ORDER BY id DESC LIMIT 8'
        );
        $stmt->execute(['id' => $companyId]);
        $executions = $stmt->fetchAll();
        if (!$executions) {
            return [];
        }

        $ids = array_map(static fn(array $item): int => (int) $item['id'], $executions);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT execucao_id, provedor, cenario, situacao, http_status, duracao_ms, erro,
                    score, pendencias, protestos, DATE_FORMAT(atualizado_em, '%Y-%m-%d') AS atualizado_em
             FROM integracao_resultados WHERE execucao_id IN ($placeholders) ORDER BY provedor"
        );
        $stmt->execute($ids);
        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['execucao_id']][] = $this->normalizeRow($row);
        }
        foreach ($executions as &$execution) {
            $execution['id'] = (int) $execution['id'];
            $execution['empresa_id'] = (int) $execution['empresa_id'];
            $execution['resultados'] = $grouped[$execution['id']] ?? [];
        }
        unset($execution);
        return $executions;
    }

    public function execute(array $company, array $scenarios): array
    {
        BureauContract::validateScenarios($scenarios);

        $stmt = $this->db->prepare('INSERT INTO integracao_execucoes (empresa_id) VALUES (:empresa_id)');
        $stmt->execute(['empresa_id' => $company['id']]);
        $executionId = (int) $this->db->lastInsertId();

        foreach (BureauContract::PROVIDERS as $provider => $sourceId) {
            $result = $this->consult($provider, $company['codigo_demo'], $scenarios[$provider]);
            $this->saveResult($executionId, $provider, $scenarios[$provider], $result);
            if ($result['situacao'] === 'sucesso') {
                $this->updateSnapshot((int) $company['id'], $sourceId, $result);
            }
        }

        return $this->list((int) $company['id'])[0];
    }

    private function consult(string $provider, string $code, string $scenario): array
    {
        $url = rtrim($this->baseUrl, '/') . '/v1/' . $provider . '/empresas/'
            . rawurlencode($code) . '?cenario=' . rawurlencode($scenario);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => 1600,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $start = microtime(true);
        $raw = curl_exec($curl);
        $duration = (int) round((microtime(true) - $start) * 1000);
        $codeError = curl_errno($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $base = [
            'situacao' => 'sucesso',
            'http_status' => $httpStatus ?: null,
            'duracao_ms' => $duration,
            'erro' => null,
            'score' => null,
            'pendencias' => null,
            'protestos' => null,
            'atualizado_em' => null,
        ];

        if ($codeError === CURLE_OPERATION_TIMEDOUT) {
            return array_replace($base, ['situacao' => 'timeout', 'erro' => 'Tempo limite de 1,6 s excedido.']);
        }
        if ($raw === false || $codeError !== 0) {
            return array_replace($base, ['situacao' => 'indisponivel', 'erro' => 'Não foi possível conectar ao provedor.']);
        }
        if ($httpStatus !== 200) {
            return array_replace($base, ['situacao' => 'erro_http', 'erro' => 'O provedor retornou HTTP ' . $httpStatus . '.']);
        }

        $data = json_decode($raw, true);
        if (!BureauContract::validateResponse($data, $provider, $code)) {
            return array_replace($base, ['situacao' => 'resposta_invalida', 'erro' => 'Resposta fora do contrato esperado.']);
        }

        return array_replace($base, [
            'score' => $data['score'],
            'pendencias' => $data['pendencias'],
            'protestos' => $data['protestos'],
            'atualizado_em' => $data['atualizado_em'],
        ]);
    }

    private function saveResult(int $executionId, string $provider, string $scenario, array $result): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO integracao_resultados
             (execucao_id, provedor, cenario, situacao, http_status, duracao_ms, erro, score, pendencias, protestos, atualizado_em)
             VALUES (:execucao_id, :provedor, :cenario, :situacao, :http_status, :duracao_ms, :erro,
                     :score, :pendencias, :protestos, :atualizado_em)'
        );
        $stmt->execute(array_merge($result, ['execucao_id' => $executionId, 'provedor' => $provider, 'cenario' => $scenario]));
    }

    private function updateSnapshot(int $companyId, int $sourceId, array $result): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO indicadores_financeiros (empresa_id, fonte_id, score, pendencias, protestos, atualizado_em)
             VALUES (:empresa_id, :fonte_id, :score, :pendencias, :protestos, :atualizado_em)
             ON DUPLICATE KEY UPDATE score = VALUES(score), pendencias = VALUES(pendencias),
               protestos = VALUES(protestos), atualizado_em = VALUES(atualizado_em)'
        );
        $stmt->execute([
            'empresa_id' => $companyId,
            'fonte_id' => $sourceId,
            'score' => $result['score'],
            'pendencias' => $result['pendencias'],
            'protestos' => $result['protestos'],
            'atualizado_em' => $result['atualizado_em'],
        ]);
    }

    private function normalizeRow(array $row): array
    {
        foreach (['execucao_id', 'duracao_ms', 'score', 'pendencias', 'protestos', 'http_status'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        }
        return $row;
    }
}
