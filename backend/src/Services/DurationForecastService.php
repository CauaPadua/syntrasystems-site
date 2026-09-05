<?php
/**
 * Aquapulse — service de previsão de duração da água.
 *
 * ALGORITMO DEMONSTRATIVO E ISOLADO DE PROPÓSITO.
 *
 * A projeção atual é um balanço linear simples: volume útil menos consumo
 * diário, com um ganho fixo por precipitação prevista. Serve para exercitar a
 * tela com números coerentes — NÃO é um modelo hidrológico.
 *
 * SUBSTITUIÇÃO FUTURA: a equipe de back-end/banco deve trocar apenas o corpo
 * de `project()` e `estimateDays()` por um modelo real (série histórica,
 * evaporação, sazonalidade, outorgas). A estrutura devolvida e o contrato da
 * API permanecem os mesmos, então a tela não precisa mudar.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;

final class DurationForecastService
{
    /** Cenários demonstrativos aplicados sobre o consumo médio. */
    private const SCENARIOS = [
        'saving'  => ['factor' => 0.90, 'label' => 'Cenário econômico',    'note' => 'Com economia de 10%',    'badge' => 'Recomendado'],
        'current' => ['factor' => 1.00, 'label' => 'Cenário atual',        'note' => 'Manutenção do consumo',  'badge' => 'Cenário base'],
        'high'    => ['factor' => 1.20, 'label' => 'Cenário de alta demanda', 'note' => 'Aumento de consumo de 20%', 'badge' => 'Atenção'],
    ];

    private MonitoringRepositoryInterface $repo;

    public function __construct(MonitoringRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Estimativa de duração em dias.
     *
     * Fórmula demonstrativa: (volume útil - reserva técnica) / consumo diário.
     * TROCAR POR MODELO REAL quando o banco existir.
     */
    private function estimateDays(float $usefulVolume, float $technicalReserve, float $dailyConsumption): int
    {
        if ($dailyConsumption <= 0) {
            return 0;
        }
        return (int) floor(($usefulVolume - $technicalReserve) / $dailyConsumption);
    }

    /**
     * Projeção do volume ao longo do horizonte.
     *
     * @return array<int,float>
     */
    private function project(float $startVolume, float $dailyConsumption, int $days, int $step): array
    {
        $values = [];
        for ($d = 0; $d <= $days; $d += $step) {
            // ganho demonstrativo por precipitação prevista
            $rainGain = $d * 0.35;
            $v = $startVolume - ($dailyConsumption * $d) + $rainGain;
            $values[] = round(max(0, $v), 0);
        }
        return $values;
    }

    /**
     * @return array<string,mixed>
     */
    public function build(string $reservoirId, string $horizon): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $useful    = (float) $r['useful_volume_hm3'];
        $reserve   = (float) $r['technical_reserve_hm3'];
        $consumption = (float) $r['daily_consumption_hm3'];
        $days      = (int) filter_var($horizon, FILTER_SANITIZE_NUMBER_INT);
        $days      = $days > 0 ? $days : 90;
        $step      = max(1, (int) round($days / 30));

        // rótulos do eixo
        $labels = [];
        $cursor = Clock::now();
        for ($d = 0; $d <= $days; $d += $step) {
            $labels[] = Clock::shortDate($cursor->modify('+' . $d . ' days'));
        }

        $scenarios = [];
        foreach (self::SCENARIOS as $key => $cfg) {
            $c = $consumption * $cfg['factor'];
            $scenarios[$key] = [
                'key'    => $key,
                'label'  => $cfg['label'],
                'note'   => $cfg['note'],
                'badge'  => $cfg['badge'],
                'days'   => $this->estimateDays($useful, $reserve, $c),
                'values' => $this->project((float) $r['volume_hm3'], $c, $days, $step),
            ];
        }

        // a estimativa canônica da represa é o valor de referência da tela
        $canonical = (int) $r['duration_days'];
        $scenarios['current']['days'] = $canonical;
        $scenarios['saving']['days']  = $canonical + 12;
        $scenarios['high']['days']    = $canonical - 17;

        $endDate = Clock::now()->modify('+' . $canonical . ' days');

        // histórico das estimativas (demonstrativo)
        $history = [];
        $base = $canonical;
        $offsets = [0, -3, -5, -8, -10];
        $dates   = ['22/05/2024', '20/05/2024', '18/05/2024', '15/05/2024', '12/05/2024'];
        $conf    = [92, 91, 90, 89, 89];
        foreach ($dates as $i => $d) {
            $value = $base + $offsets[$i];
            $history[] = [
                'date'       => $d,
                'estimate'   => $value,
                'variation'  => $i === 0 ? '—' : '+' . ($offsets[$i - 1] - $offsets[$i]),
                'scenario'   => 'Atual',
                'confidence' => $conf[$i],
            ];
        }

        return [
            'reservoir' => [
                'id'   => $r['id'],
                'name' => $r['name'],
                'code' => $r['code'],
                'telemetry_label' => 'Dados atualizados',
            ],
            'kpis' => [
                'duration'    => ['value' => $canonical, 'unit' => 'dias', 'note' => 'Com base no cenário atual'],
                'useful'      => ['value' => $useful, 'unit' => 'hm³', 'note' => round($useful / (float) $r['capacity_hm3'] * 100, 1) . '% da capacidade total'],
                'consumption' => ['value' => $consumption, 'unit' => 'hm³/dia', 'note' => 'Média dos últimos 7 dias'],
                'reliability' => ['value' => $r['forecast_reliability_pct'], 'unit' => '%', 'note' => 'Alta confiabilidade'],
            ],
            'projection' => [
                'labels'   => $labels,
                'current'  => $scenarios['current']['values'],
                'high'     => $scenarios['high']['values'],
                'saving'   => $scenarios['saving']['values'],
                'capacity' => $r['capacity_hm3'],
                'reserve'  => $reserve,
                'unit'     => 'hm³',
            ],
            'estimate' => [
                'days'      => $canonical,
                'date'      => Clock::longDate($endDate),
                'badge'     => 'Cenário estável',
                'note'      => 'Tendência de manutenção da duração',
                'max_days'  => 120,
            ],
            'scenarios' => [
                ['key' => 'saving',  'label' => $scenarios['saving']['label'],  'days' => $scenarios['saving']['days'],  'note' => $scenarios['saving']['note'],  'badge' => $scenarios['saving']['badge'],  'status' => 'normal',    'icon' => 'leaf'],
                ['key' => 'current', 'label' => $scenarios['current']['label'], 'days' => $scenarios['current']['days'], 'note' => $scenarios['current']['note'], 'badge' => $scenarios['current']['badge'], 'status' => 'info',      'icon' => 'waves'],
                ['key' => 'high',    'label' => $scenarios['high']['label'],    'days' => $scenarios['high']['days'],    'note' => $scenarios['high']['note'],    'badge' => $scenarios['high']['badge'],    'status' => 'attention', 'icon' => 'chart-up'],
            ],
            'factors' => [
                ['label' => 'Volume útil atual',        'value' => number_format($useful, 0, ',', '.') . ' hm³', 'icon' => 'waves'],
                ['label' => 'Vazão de entrada (média)', 'value' => '18,2 hm³/dia', 'icon' => 'chart-up'],
                ['label' => 'Consumo médio',            'value' => number_format($consumption, 1, ',', '.') . ' hm³/dia', 'icon' => 'clock'],
                ['label' => 'Precipitação prevista (' . $days . 'd)', 'value' => '210 mm', 'icon' => 'cloud-rain'],
            ],
            'history' => $history,
            'insight' => [
                'gain' => $scenarios['saving']['days'] - $canonical,
                'text' => 'A economia de 10% pode ampliar a duração da água em até '
                          . ($scenarios['saving']['days'] - $canonical) . ' dias no horizonte selecionado.',
            ],
        ];
    }
}
