<?php
/**
 * Aquapulse — dados da Visão geral.
 *
 * A tela tem duas situações, escolhidas pelo campo "mode" da resposta:
 *   single  uma represa selecionada: indicadores, gráficos, alertas e relatórios dela;
 *   all     "todas as represas": totais, médias, comparativo e resumo por represa.
 *
 * Usado por api/v1/overview.php e lido por assets/js/pages/overview.js.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;

final class OverviewService
{
    private MonitoringRepositoryInterface $repo;

    public function __construct(MonitoringRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @param string $companyId   empresa filtrada ou 'all' (só vale no modo consolidado)
     * @param string $reservoirId represa escolhida ou 'all'
     * @param string $period      período dos gráficos (24h, 7d, 30d, 90d, 12m)
     * @return array<string,mixed>
     */
    public function build(string $companyId, string $reservoirId, string $period): array
    {
        return $reservoirId === 'all'
            ? $this->consolidated($companyId)
            : $this->single($reservoirId, $period);
    }

    /* ------------------------------------------------ uma represa selecionada */

    private function single(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $status = StatusRules::fromLevel((float) $r['level_pct']);
        $phStatus = StatusRules::fromPh((float) $r['ph']);

        $cota = $this->repo->series($reservoirId, 'cota', $period);
        $entrada = $this->repo->series($reservoirId, 'inflow', $period);
        $saida = $this->repo->series($reservoirId, 'outflow', $period);
        $saidaPorRotulo = array_combine($saida['labels'], $saida['values']) ?: [];

        return [
            'mode' => 'single',
            'reservoir' => [
                'id'           => $r['id'],
                'name'         => $r['name'],
                'code'         => $r['code'],
                'city'         => $r['city'],
                'basin'        => $r['basin'],
                'lat'          => $r['lat'],
                'lng'          => $r['lng'],
                'status'       => StatusRules::describe($status),
                'last_reading' => $this->lastReading($r),
            ],
            'kpis' => [
                'level'   => ['value' => $r['level_pct'], 'unit' => '%', 'cota' => $r['cota_m'], 'status' => StatusRules::describe($status)],
                'storage' => ['value' => $r['volume_hm3'], 'unit' => 'hm³', 'capacity' => $r['capacity_hm3'], 'occupancy' => $r['level_pct']],
                'flow'    => ['value' => $r['inflow_m3s'], 'unit' => 'm³/s',
                              'trend' => $this->trend((float) $r['inflow_m3s'], (float) $r['inflow_24h_ago_m3s']), 'trend_label' => 'vs 24 h atrás'],
                'ph'      => ['value' => $r['ph'], 'unit' => '', 'status' => StatusRules::describe($phStatus),
                              'note' => $phStatus === 'normal' ? 'Dentro da faixa ideal' : 'Fora da faixa ideal'],
                'rain'    => ['value' => $r['rain_24h_mm'], 'unit' => 'mm',
                              'trend' => $this->trend((float) $r['rain_24h_mm'], (float) $r['rain_prev_24h_mm']), 'trend_label' => 'vs 24 h anteriores', 'note' => 'Acumulado de 24 h'],
                'duration' => ['value' => ReservoirCurve::durationDays($r), 'unit' => 'dias', 'note' => 'Volume acima da reserva ÷ consumo médio'],
                'operation' => ['status' => StatusRules::describe($status), 'note' => StatusRules::levelNote($status)],
            ],
            'level_chart' => [
                'labels'      => $cota['labels'],
                'observed'    => $cota['values'],
                'spill'       => array_fill(0, count($cota['labels']), (float) $r['cota_spill_m']),   // linha horizontal de referência
                'unit'        => 'm',
                'current'     => $r['cota_m'],
                'spill_label' => 'Cota de vertimento: ' . number_format((float) $r['cota_spill_m'], 1, ',', '.') . ' m',
            ],
            'flow_chart' => [
                'labels'  => $entrada['labels'],
                'inflow'  => $entrada['values'],
                'outflow' => array_map(static fn (string $rotulo) => $saidaPorRotulo[$rotulo] ?? null, $entrada['labels']),
                'unit'    => 'm³/s',
            ],
            'alerts'  => array_map([$this, 'alertRow'], array_slice($this->repo->alerts($reservoirId), 0, 3)),
            'reports' => array_map([$this, 'reportRow'], array_slice($this->repo->reports($reservoirId), 0, 5)),
        ];
    }

    /* -------------------------------------------------- todas as represas */

    /** Totais e médias das represas que o usuário pode ver (opcionalmente de uma empresa). */
    private function consolidated(string $companyId): array
    {
        $list = $this->repo->reservoirs($companyId);

        if ($list === []) {
            return ['mode' => 'all', 'empty' => true, 'kpis' => [], 'reservoirs' => []];
        }

        $count = count($list);
        $totalVolume = 0.0;
        $totalCap = 0.0;
        $totalFlow = 0.0;
        $sumLevel = 0.0;
        $sumPh = 0.0;
        $byStatus = ['normal' => 0, 'attention' => 0, 'critical' => 0];
        $online = 0;

        foreach ($list as $r) {
            $totalVolume += (float) $r['volume_hm3'];
            $totalCap += (float) $r['capacity_hm3'];
            $totalFlow += (float) $r['inflow_m3s'];
            $sumLevel += (float) $r['level_pct'];
            $sumPh += (float) $r['ph'];
            $byStatus[StatusRules::fromLevel((float) $r['level_pct'])]++;
            if ((int) $r['sensors_online'] > 0) {
                $online++;
            }
        }

        $avgLevel = round($sumLevel / $count, 1);
        $avgPh = round($sumPh / $count, 1);
        $avgPhStatus = StatusRules::fromPh($avgPh);

        // Afluência somada das represas, dia a dia, nos últimos 7 dias.
        $flowTotals = [];
        foreach ($list as $r) {
            $s = $this->repo->series($r['id'], 'inflow', '7d');
            foreach ($s['labels'] as $i => $rotulo) {
                $flowTotals[$rotulo] = ($flowTotals[$rotulo] ?? 0.0) + $s['values'][$i];
            }
        }

        $alerts = $this->repo->alerts('all');
        $open = array_values(array_filter($alerts, static fn (array $a): bool => $a['status'] !== 'resolved'));
        $alertCounts = ['critical' => 0, 'attention' => 0];
        foreach ($open as $a) {
            if (isset($alertCounts[$a['severity']])) {
                $alertCounts[$a['severity']]++;
            }
        }

        return [
            'mode' => 'all',
            'kpis' => [
                'reservoirs' => ['value' => $count, 'online' => $online, 'label' => 'Represas monitoradas'],
                'storage'    => ['value' => round($totalVolume), 'unit' => 'hm³', 'capacity' => round($totalCap), 'pct' => round($totalVolume / $totalCap * 100, 1)],
                'level'      => ['value' => $avgLevel, 'unit' => '%'],
                'flow'       => ['value' => round($totalFlow, 1), 'unit' => 'm³/s'],
                'ph'         => ['value' => $avgPh, 'unit' => '', 'status' => StatusRules::describe($avgPhStatus),
                                 'note' => $avgPhStatus === 'normal' ? 'Dentro da faixa ideal' : 'Fora da faixa ideal'],
                'operation'  => $byStatus,
            ],
            'comparison' => array_map(static function (array $r): array {
                return [
                    'id'     => $r['id'],
                    'name'   => $r['name'],
                    'level'  => $r['level_pct'],
                    'status' => StatusRules::describe(StatusRules::fromLevel((float) $r['level_pct'])),
                ];
            }, $list),
            'flow_chart' => [
                'labels' => array_keys($flowTotals),
                'values' => array_map(static fn (float $v): float => round($v, 1), array_values($flowTotals)),
                'unit'   => 'm³/s',
            ],
            'donut' => ['total' => $count] + $byStatus,
            'alert_counts' => ['total' => count($open)] + $alertCounts,
            'reservoirs' => array_map(static function (array $r): array {
                return [
                    'id'       => $r['id'],
                    'name'     => $r['name'],
                    'level'    => $r['level_pct'],
                    'volume'   => $r['volume_hm3'],
                    'flow'     => $r['inflow_m3s'],
                    'ph'       => $r['ph'],
                    'rain'     => $r['rain_24h_mm'],
                    'duration' => ReservoirCurve::durationDays($r),
                    'lat'      => $r['lat'],
                    'lng'      => $r['lng'],
                    'city'     => $r['city'],
                    'status'   => StatusRules::describe(StatusRules::fromLevel((float) $r['level_pct'])),
                ];
            }, $list),
            'priority_alerts' => array_map([$this, 'alertRow'], array_slice($open, 0, 3)),
        ];
    }

    /* ---------------------------------------------------------- auxiliares */

    /** Variação percentual entre dois valores; null quando não há base de comparação. */
    private function trend(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    private function lastReading(array $r): string
    {
        return $r['last_reading_at'] === null
            ? 'Nenhuma leitura registrada'
            : 'Última leitura ' . Clock::relative(Clock::parse((string) $r['last_reading_at']));
    }

    /** Nome curto da represa de um registro ("Represa Santa Clara" -> "Santa Clara"). */
    private function shortName(string $reservoirId): string
    {
        $r = $this->repo->reservoir($reservoirId);
        return $r === null ? '—' : str_replace('Represa ', '', $r['name']);
    }

    /** @param array<string,mixed> $a */
    private function alertRow(array $a): array
    {
        $when = Clock::parse($a['detected_at']);
        $nome = $this->shortName($a['reservoir_id']);

        return [
            'id'              => $a['id'],
            'title'           => $a['title'],
            'severity'        => $a['severity'],
            'severity_label'  => StatusRules::severityLabel($a['severity']),
            'reservoir'       => 'Represa ' . $nome,
            'reservoir_short' => $nome,
            'at'              => Clock::dateTime($when),
            'time'            => $when->format('H:i'),
        ];
    }

    /** @param array<string,mixed> $rep */
    private function reportRow(array $rep): array
    {
        return [
            'id'           => $rep['id'],
            'name'         => $rep['name'],
            'reservoir'    => $this->shortName($rep['reservoir_id']),
            'period'       => $rep['period'],
            'generated_at' => Clock::dateTime(Clock::parse($rep['generated_at'])),
        ];
    }
}
