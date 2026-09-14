<?php
/**
 * Aquapulse — validação de parâmetros da API por allowlist.
 *
 * Regra: nada vindo do navegador é confiável. Todo parâmetro é comparado com
 * uma lista fechada de valores aceitos; qualquer coisa fora dela vira erro
 * explícito, nunca uma consulta com valor arbitrário.
 *
 * Os métodos que encontram um valor inválido chamam ApiResponse::error(), que
 * responde e ENCERRA a requisição. Por isso, quando um método daqui retorna,
 * o valor devolvido já é garantidamente válido.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

final class Validator
{
    public const PERIODS   = ['24h', '7d', '30d', '90d', '12m'];                          // janelas de tempo aceitas nos gráficos (24 horas, 7/30/90 dias, 12 meses)
    public const SEVERITY  = ['all', 'critical', 'attention', 'info'];                    // gravidade dos alertas; "all" = sem filtro
    public const ALERT_STATUS  = ['all', 'new', 'analysis', 'resolved'];                  // andamento do alerta: novo, em análise, resolvido
    public const REPORT_STATUS = ['all', 'done', 'processing', 'scheduled'];              // situação do relatório: concluído, processando, agendado
    public const REPORT_TYPES  = ['all', 'operational', 'hydrological', 'quality', 'planning']; // categorias de relatório

    /** Lê um parâmetro de query como string simples. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;                                                  // valor da URL (?chave=valor) ou o padrão quando ausente
        return is_string($value) ? trim($value) : $default;                                // "?x[]=1" chega como array: é descartado em favor do padrão
    }

    /**
     * Valida contra uma allowlist. Devolve o valor ou encerra com 400.
     *
     * @param array<int,string> $allowed
     * @param string $code    código de erro devolvido ao front-end (ex.: INVALID_PERIOD)
     * @param string $message mensagem legível exibida ao usuário
     */
    public static function inList(string $value, array $allowed, string $code, string $message): string
    {
        if (!in_array($value, $allowed, true)) {                                           // comparação estrita (true): "7d" precisa ser exatamente "7d"
            ApiResponse::error($code, $message, 400);                                      // 400 Bad Request: o erro está no pedido do cliente
        }
        return $value;
    }

    /** Período válido, com padrão. */
    public static function period(string $default = '7d'): string
    {
        $value = self::query('period', $default);                                          // lê ?period=
        if ($value === '') {                                                               // "?period=" vazio é tratado como "não informado"
            $value = $default;
        }
        return self::inList(
            $value,
            self::PERIODS,
            'INVALID_PERIOD',
            'O período informado não é válido. Use: ' . implode(', ', self::PERIODS) . '.' // a mensagem já lista as opções aceitas
        );
    }

    /**
     * ID de empresa existente (ou 'all'). Encerra com 404 se não existir.
     *
     * Diferente dos períodos, a lista válida não é fixa no código: vem do
     * repositório (empresas cadastradas), recebida em $companies.
     *
     * @param array<int,array<string,mixed>> $companies
     */
    public static function companyId(array $companies, string $default = 'all'): string
    {
        $value = self::query('company_id', $default);
        if ($value === '' || $value === 'all') {                                           // sem filtro de empresa: consolida todas
            return 'all';
        }

        foreach ($companies as $c) {                                                       // procura o ID recebido entre as empresas existentes
            if ($c['id'] === $value) {
                return $value;
            }
        }

        ApiResponse::error('INVALID_COMPANY', 'A empresa informada não existe ou não está disponível.', 404); // 404: o recurso pedido não existe
        return 'all'; // inalcançável
    }

    /**
     * ID de represa existente. `$allowAll` controla se 'all' é aceito.
     *
     * Telas consolidadas (visão geral, alertas) aceitam "todas as represas";
     * as telas detalhadas de monitoramento exigem uma represa específica
     * e chamam com $allowAll = false.
     *
     * @param array<int,array<string,mixed>> $reservoirs
     */
    public static function reservoirId(array $reservoirs, bool $allowAll = true, string $default = 'all'): string
    {
        $value = self::query('reservoir_id', $default);

        if ($value === '' || $value === 'all') {                                           // o cliente não escolheu uma represa
            if ($allowAll) {                                                               // tela consolidada: tudo bem
                return 'all';
            }
            ApiResponse::error(                                                            // tela detalhada: é obrigatório escolher uma
                'RESERVOIR_REQUIRED',
                'Selecione uma represa específica para esta análise detalhada.',
                400
            );
        }

        foreach ($reservoirs as $r) {                                                      // confere se a represa existe na lista recebida
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
     * Versão genérica de period(): usada para severity, status, type, horizon etc.
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
