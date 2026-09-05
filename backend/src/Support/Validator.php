<?php
/**
 * Aquapulse — validação de parâmetros da API por allowlist.
 *
 * Regra: nada vindo do navegador é confiável. Todo parâmetro é comparado com
 * uma lista fechada de valores aceitos; qualquer coisa fora dela vira erro
 * explícito, nunca uma consulta com valor arbitrário.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

final class Validator
{
    public const PERIODS   = ['24h', '7d', '30d', '90d', '12m'];
    public const SEVERITY  = ['all', 'critical', 'attention', 'info'];
    public const ALERT_STATUS  = ['all', 'new', 'analysis', 'resolved'];
    public const REPORT_STATUS = ['all', 'done', 'processing', 'scheduled'];
    public const REPORT_TYPES  = ['all', 'operational', 'hydrological', 'quality', 'planning'];

    /** Lê um parâmetro de query como string simples. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Valida contra uma allowlist. Devolve o valor ou encerra com 400.
     *
     * @param array<int,string> $allowed
     */
    public static function inList(string $value, array $allowed, string $code, string $message): string
    {
        if (!in_array($value, $allowed, true)) {
            ApiResponse::error($code, $message, 400);
        }
        return $value;
    }

    /** Período válido, com padrão. */
    public static function period(string $default = '7d'): string
    {
        $value = self::query('period', $default);
        if ($value === '') {
            $value = $default;
        }
        return self::inList(
            $value,
            self::PERIODS,
            'INVALID_PERIOD',
            'O período informado não é válido. Use: ' . implode(', ', self::PERIODS) . '.'
        );
    }

    /**
     * ID de empresa existente (ou 'all'). Encerra com 404 se não existir.
     *
     * @param array<int,array<string,mixed>> $companies
     */
    public static function companyId(array $companies, string $default = 'all'): string
    {
        $value = self::query('company_id', $default);
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        foreach ($companies as $c) {
            if ($c['id'] === $value) {
                return $value;
            }
        }

        ApiResponse::error('INVALID_COMPANY', 'A empresa informada não existe ou não está disponível.', 404);
        return 'all'; // inalcançável
    }

    /**
     * ID de represa existente. `$allowAll` controla se 'all' é aceito.
     *
     * @param array<int,array<string,mixed>> $reservoirs
     */
    public static function reservoirId(array $reservoirs, bool $allowAll = true, string $default = 'all'): string
    {
        $value = self::query('reservoir_id', $default);

        if ($value === '' || $value === 'all') {
            if ($allowAll) {
                return 'all';
            }
            ApiResponse::error(
                'RESERVOIR_REQUIRED',
                'Selecione uma represa específica para esta análise detalhada.',
                400
            );
        }

        foreach ($reservoirs as $r) {
            if ($r['id'] === $value) {
                return $value;
            }
        }

        ApiResponse::error('INVALID_RESERVOIR', 'A represa informada não existe ou não está disponível.', 404);
        return 'all'; // inalcançável
    }

    /**
     * Parâmetro simples validado por allowlist, com padrão.
     *
     * @param array<int,string> $allowed
     */
    public static function option(string $key, array $allowed, string $default, string $code, string $message): string
    {
        $value = self::query($key, $default);
        if ($value === '') {
            $value = $default;
        }
        return self::inList($value, $allowed, $code, $message);
    }
}
