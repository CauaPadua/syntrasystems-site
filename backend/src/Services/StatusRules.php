<?php
/**
 * Aquapulse — regras de classificação (Normal / Atenção / Crítico).
 *
 * É o ÚNICO lugar que decide a faixa de um valor. Cards, gráficos, tabelas,
 * mapa e a geração de alertas chamam classify(), então o mesmo nível nunca é
 * classificado de formas diferentes em telas diferentes.
 *
 * Os limites vêm da tabela operational_limits (carregados uma vez por
 * requisição em Support\Container). Os valores abaixo são o padrão usado no
 * modo demonstração, sem banco.
 *
 * Regra de leitura de um limite:
 *   - "critical" e "attention" valem a partir do seu mínimo (>=);
 *   - "normal" com mínimo e máximo define uma faixa ideal; fora dela é atenção.
 * Exemplo com os valores padrão: nível 79,9% normal, 80% atenção, 90% crítico.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

final class StatusRules
{
    /** @var array<string,array<string,array{min:?float,max:?float}>> */
    private static array $limits = [
        'level' => [
            'attention' => ['min' => 80.0, 'max' => 90.0],
            'critical'  => ['min' => 90.0, 'max' => null],
        ],
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

    /** @return array<string,array<string,array{min:?float,max:?float}>> */
    public static function limits(): array
    {
        return self::$limits;
    }

    /**
     * Classifica um valor de qualquer grandeza.
     *
     * @return string 'normal' | 'attention' | 'critical'
     */
    public static function classify(string $metric, float $value): string
    {
        $faixas = self::$limits[$metric] ?? [];

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

    public static function fromLevel(float $levelPct): string
    {
        return self::classify('level', $levelPct);
    }

    public static function fromPh(float $ph): string
    {
        return self::classify('ph', $ph);
    }

    /* ------------------------------------------------ limites em vigor */

    public static function levelAttention(): float
    {
        return (float) (self::$limits['level']['attention']['min'] ?? 80.0);
    }

    public static function levelCritical(): float
    {
        return (float) (self::$limits['level']['critical']['min'] ?? 90.0);
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
     * Ex.: "80%" para um nível em atenção, "6,5 – 8,5" para pH fora da faixa.
     */
    public static function thresholdText(string $metric, string $classification): string
    {
        $faixas = self::$limits[$metric] ?? [];

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
            default:
                return ['key' => 'normal', 'label' => 'Normal', 'icon' => 'check-circle'];
        }
    }

    /** Frase que acompanha o status do nível; usa os mesmos limites da classificação. */
    public static function levelNote(string $status): string
    {
        switch ($status) {
            case 'critical':
                return 'Nível a partir de ' . self::pct(self::levelCritical()) . '; ação imediata recomendada';
            case 'attention':
                return 'Nível a partir de ' . self::pct(self::levelAttention()) . '; acompanhamento recomendado';
            default:
                return 'Nível abaixo de ' . self::pct(self::levelAttention());
        }
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
