<?php
/**
 * Aquapulse — regras de classificação (Normal / Atenção / Crítico).
 *
 * É o ÚNICO lugar que decide a faixa de um valor. Cards, gráficos, tabelas,
 * mapa e a geração de alertas chamam classify(), então o mesmo nível nunca é
 * classificado de formas diferentes em telas diferentes.
 *
 * De onde vêm os limites:
 *   nível       da própria represa — a cota de alerta abre Atenção e a cota de
 *               vertimento abre Crítico, porque cada represa verte em um nível;
 *   pH e chuva  da tabela operational_limits (tela Configurações › Limites), iguais
 *               para todas as represas e carregados uma vez por requisição em
 *               Support\Container. Os valores abaixo são o padrão do modo
 *               demonstração, sem banco.
 *
 * Regra de leitura de um limite:
 *   - "critical" e "attention" valem a partir do seu mínimo (>=);
 *   - "normal" com mínimo e máximo define uma faixa ideal; fora dela é atenção.
 * Exemplo (Santa Clara, cotas 480 a 580 m, alerta 558 m, vertimento 565 m):
 * nível 77,9% normal, 78% atenção, 85% crítico.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

final class StatusRules
{
    /** Represa (ou grandeza) sem nenhuma leitura: não é normal, nem atenção, nem crítico. */
    public const NO_DATA = 'neutral';

    /** @var array<string,array<string,array{min:?float,max:?float}>> */
    private static array $limits = [
        'ph' => [
            'normal' => ['min' => 6.5, 'max' => 8.5],
        ],
        'precipitation' => [
            'attention' => ['min' => 20.0, 'max' => 60.0],
            'critical'  => ['min' => 60.0, 'max' => null],
        ],
    ];

    /**
     * Substitui os limites padrão pelos cadastrados no banco.
     *
     * @param array<string,array<string,array{min:?float,max:?float}>> $limits
     */
    public static function configure(array $limits): void
    {
        foreach ($limits as $metric => $faixas) {
            self::$limits[$metric] = $faixas;
        }
    }

    /** Limites iguais para todas as represas (pH e chuva). @return array<string,array<string,array{min:?float,max:?float}>> */
    public static function limits(): array
    {
        return self::$limits;
    }

    /**
     * Limites de uma grandeza em uma represa.
     *
     * @param array<string,mixed> $reservoir para o nível, precisa das quatro cotas do cadastro
     * @return array<string,array{min:?float,max:?float}>
     */
    public static function limitsFor(string $metric, array $reservoir): array
    {
        if ($metric !== 'level') {
            return self::$limits[$metric] ?? [];
        }

        $atencao = ReservoirCurve::level($reservoir, (float) $reservoir['cota_alert_m']);
        $critico = ReservoirCurve::level($reservoir, (float) $reservoir['cota_spill_m']);

        return [
            'attention' => ['min' => $atencao, 'max' => $critico],
            'critical'  => ['min' => $critico, 'max' => null],
        ];
    }

    /**
     * Classifica um valor de qualquer grandeza.
     *
     * @param array<string,mixed> $reservoir obrigatória para o nível (ver limitsFor)
     * @return string 'normal' | 'attention' | 'critical'
     */
    public static function classify(string $metric, float $value, array $reservoir = []): string
    {
        $faixas = self::limitsFor($metric, $reservoir);

        $critico = $faixas['critical']['min'] ?? null;
        if ($critico !== null && $value >= $critico) {
            return 'critical';
        }

        $atencao = $faixas['attention']['min'] ?? null;
        if ($atencao !== null && $value >= $atencao) {
            return 'attention';
        }

        $ideal = $faixas['normal'] ?? null;
        if ($ideal !== null && $ideal['min'] !== null && $ideal['max'] !== null
            && ($value < $ideal['min'] || $value > $ideal['max'])) {
            return 'attention';
        }

        return 'normal';
    }

    /** @param array<string,mixed> $reservoir represa a que o nível pertence (cotas do cadastro) */
    public static function fromLevel(?float $levelPct, array $reservoir): string
    {
        return $levelPct === null ? self::NO_DATA : self::classify('level', $levelPct, $reservoir);
    }

    public static function fromPh(?float $ph): string
    {
        return $ph === null ? self::NO_DATA : self::classify('ph', $ph);
    }

    /* ------------------------------------------------ limites em vigor */

    /** Nível (%) em que a represa entra em atenção: o da cota de alerta. */
    public static function levelAttention(array $reservoir): float
    {
        return self::limitsFor('level', $reservoir)['attention']['min'];
    }

    /** Nível (%) em que a represa entra em crítico: o da cota de vertimento. */
    public static function levelCritical(array $reservoir): float
    {
        return self::limitsFor('level', $reservoir)['critical']['min'];
    }

    public static function phMin(): float
    {
        return (float) (self::$limits['ph']['normal']['min'] ?? 6.5);
    }

    public static function phMax(): float
    {
        return (float) (self::$limits['ph']['normal']['max'] ?? 8.5);
    }

    public static function rainAttention(): float
    {
        return (float) (self::$limits['precipitation']['attention']['min'] ?? 20.0);
    }

    public static function rainCritical(): float
    {
        return (float) (self::$limits['precipitation']['critical']['min'] ?? 60.0);
    }

    /**
     * Texto do limite que um valor ultrapassou, para exibir no alerta.
     * Ex.: "78%" para um nível em atenção, "6,5 – 8,5" para pH fora da faixa.
     *
     * @param array<string,mixed> $reservoir obrigatória para o nível (ver limitsFor)
     */
    public static function thresholdText(string $metric, string $classification, array $reservoir = []): string
    {
        $faixas = self::limitsFor($metric, $reservoir);

        if (isset($faixas[$classification]['min'])) {
            return self::number((float) $faixas[$classification]['min']) . self::unit($metric);
        }
        if (isset($faixas['normal']['min'], $faixas['normal']['max'])) {
            return self::number((float) $faixas['normal']['min']) . ' – ' . self::number((float) $faixas['normal']['max']);
        }

        return '—';
    }

    /* -------------------------------------------------------- rótulos */

    /**
     * Descreve um status para a interface. O status nunca é comunicado só por
     * cor: quem consome recebe também o texto e o nome do ícone.
     *
     * @return array{key:string,label:string,icon:string}
     */
    public static function describe(string $status): array
    {
        switch ($status) {
            case 'critical':
                return ['key' => 'critical', 'label' => 'Crítico', 'icon' => 'alert-circle'];
            case 'attention':
                return ['key' => 'attention', 'label' => 'Atenção', 'icon' => 'alert-triangle'];
            case 'info':
                return ['key' => 'info', 'label' => 'Informação', 'icon' => 'info'];
            case 'offline':
                return ['key' => 'offline', 'label' => 'Sem comunicação', 'icon' => 'wifi-off'];
            case self::NO_DATA:
                return ['key' => self::NO_DATA, 'label' => 'Sem leituras', 'icon' => 'info'];
            default:
                return ['key' => 'normal', 'label' => 'Normal', 'icon' => 'check-circle'];
        }
    }

    /** Frase que acompanha o status do nível; usa os mesmos limites da classificação. */
    public static function levelNote(string $status, array $reservoir): string
    {
        switch ($status) {
            case 'critical':
                return 'Nível a partir de ' . self::pct(self::levelCritical($reservoir)) . ' (cota de vertimento); ação imediata recomendada';
            case 'attention':
                return 'Nível a partir de ' . self::pct(self::levelAttention($reservoir)) . ' (cota de alerta); acompanhamento recomendado';
            case self::NO_DATA:
                return 'Aguardando a primeira leitura';
            default:
                return 'Nível abaixo de ' . self::pct(self::levelAttention($reservoir)) . ' (cota de alerta)';
        }
    }

    /** Frase que acompanha o status do pH. */
    public static function phNote(string $status): string
    {
        if ($status === self::NO_DATA) {
            return 'Aguardando a primeira leitura';
        }
        return $status === 'normal' ? 'Dentro da faixa ideal' : 'Fora da faixa ideal';
    }

    public static function severityLabel(string $severity): string
    {
        switch ($severity) {
            case 'critical':  return 'Crítico';
            case 'attention': return 'Atenção';
            case 'info':      return 'Informação';
            default:          return 'Normal';
        }
    }

    public static function alertStatusLabel(string $status): string
    {
        switch ($status) {
            case 'new':      return 'Novo';
            case 'analysis': return 'Em análise';
            case 'resolved': return 'Resolvido';
            default:         return $status;
        }
    }


    /** 80.0 -> "80%"; 82.5 -> "82,5%" */
    public static function pct(float $value): string
    {
        return self::number($value) . '%';
    }

    /** Número no formato brasileiro, sem casas decimais desnecessárias. */
    public static function number(float $value): string
    {
        $texto = number_format($value, 2, ',', '.');
        return str_contains($texto, ',') ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }

    private static function unit(string $metric): string
    {
        switch ($metric) {
            case 'level':         return '%';
            case 'precipitation': return ' mm';
            default:              return '';
        }
    }
}
