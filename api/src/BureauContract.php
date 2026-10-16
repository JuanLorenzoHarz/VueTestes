<?php

declare(strict_types=1);

final class BureauContract
{
    public const PROVIDERS = ['bureau-a' => 1, 'bureau-b' => 2];
    public const SCENARIOS = ['normal', 'lento', 'falha', 'timeout'];

    public static function validateScenarios(array $scenarios): void
    {
        if (count($scenarios) !== count(self::PROVIDERS) ||
            array_diff(array_keys($scenarios), array_keys(self::PROVIDERS)) !== [] ||
            array_diff(array_keys(self::PROVIDERS), array_keys($scenarios)) !== []) {
            throw new InvalidArgumentException('Informe um cenário para cada bureau.');
        }
        foreach ($scenarios as $scenario) {
            if (!is_string($scenario) || !in_array($scenario, self::SCENARIOS, true)) {
                throw new InvalidArgumentException('Informe apenas cenários válidos para os dois bureaus.');
            }
        }
    }

    public static function validateResponse(mixed $data, string $provider, string $demoCode): bool
    {
        if (!is_array($data) || ($data['provedor'] ?? null) !== $provider ||
            ($data['codigo_demo'] ?? null) !== $demoCode || ($data['dados_ficticios'] ?? false) !== true ||
            !array_key_exists('score', $data) || !self::validScore($data['score']) ||
            !self::validCount($data['pendencias'] ?? null) ||
            !self::validCount($data['protestos'] ?? null)) {
            return false;
        }
        $date = $data['atualizado_em'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year);
    }

    private static function validScore(mixed $score): bool
    {
        return $score === null || (is_int($score) && $score >= 0 && $score <= 1000);
    }

    private static function validCount(mixed $count): bool
    {
        return is_int($count) && $count >= 0 && $count <= 100000;
    }
}
