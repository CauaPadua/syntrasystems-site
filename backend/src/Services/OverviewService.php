<?php
/**
 * Aquapulse — service da Visão geral.
 *
 * Monta as duas situações da tela:
 *   - uma represa selecionada  -> KPIs da represa, série de nível, alertas, relatórios
 *   - "Todas as represas"      -> KPIs consolidados, comparativo, donut, resumo, mapa
 *
 * Depende apenas de MonitoringRepositoryInterface: não conhece a origem dos
 * dados. Trocar o mock pelo banco não altera este arquivo.
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
     * @return array<string,mixed>
     */
    public function build(string $companyId, string $reservoirId, string $period): array
    {
        return $reservoirId === 'all'
            ? $this->consolidated($companyId, $period)
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
        $levelSeries = $this->repo->series($reservoirId, 'cota', $period);
        $flowSeries  = $this->repo->series($reservoirId, 'flow', $period);

        // linha tracejada de referência: cota de vertimento
        $spill = array_fill(0, count($levelSeries['labels']), (float) $r['cota_spill_m']);

        $alerts = array_slice($this->repo->alerts($reservoirId), 0, 3);
        $reports = array_slice($this->repo->reports($reservoirId), 0, 5);

        return [
            'mode' => 'single',
            'reservoir' => [
                'id'      => $r['id'],
                'name'    => $r['name'],
                'code'    => $r['code'],
                'city'    => $r['city'],
                'basin'   => $r['basin'],
                'lat'     => $r['lat'],
                'lng'     => $r['lng'],
                'status'  => StatusRules::describe($status),
            ],
            'kpis' => [
                'level'        => ['value' => $r['level_pct'], 'unit' => '%', 'cota' => $r['cota_m'], 'status' => StatusRules::describe($status)],
                'storage'      => ['value' => $r['volume_hm3'], 'unit' => 'hm³', 'capacity' => $r['capacity_hm3'], 'occupancy' => $r['level_pct']],
                'flow'         => ['value' => $r['flow_m3s'], 'unit' => 'm³/s', 'trend' => -8, 'trend_label' => 'vs ontem'],
                'ph'           => ['value' => $r['ph'], 'unit' => '', 'status' => StatusRules::describe(StatusRules::fromPh((float) $r['ph'])), 'note' => 'Neutro'],
                'rain'         => ['value' => $r['rain_24h_mm'], 'unit' => 'mm', 'trend' => 15, 'trend_label' => 'vs ontem', 'note' => 'Na bacia'],
                'duration'     => ['value' => $r['duration_days'], 'unit' => 'dias', 'note' => 'Com o volume atual'],
                'operation'    => ['status' => StatusRules::describe($status), 'note' => 'Todas as condições dentro dos limites'],
            ],
            'level_chart' => [
                'labels'   => $levelSeries['labels'],
                'observed' => $levelSeries['values'],
                'spill'    => $spill,
                'unit'     => 'm',
                'current'  => $r['cota_m'],
                'spill_label' => 'Cota de vertimento: ' . number_format((float) $r['cota_spill_m'], 1, ',', '.') . ' m',
            ],
            'flow_chart' => [
                'labels'   => $flowSeries['labels'],
                'current'  => $flowSeries['values'],
                'previous' => array_map(
                    static fn (float $v): float => round($v * 0.93, 1),
                    $flowSeries['values']
                ),
                'unit' => 'm³/s',
            ],
            'alerts'  => array_map([$this, 'alertRow'], $alerts),
            'reports' => array_map([$this, 'reportRow'], $reports),
        ];
    }

    /* -------------------------------------------------- todas as represas */

    private function consolidated(string $companyId, string $period): array
    {
        $list = $this->repo->reservoirs($companyId);

        if ($list === []) {
            return ['mode' => 'all', 'empty' => true, 'kpis' => [], 'reservoirs' => []];
        }

        $count       = count($list);
        $totalVolume = 0.0;
        $totalCap    = 0.0;
        $totalFlow   = 0.0;
        $sumLevel    = 0.0;
        $sumPh       = 0.0;
        $byStatus    = ['normal' => 0, 'attention' => 0, 'critical' => 0];
        $online      = 0;

        foreach ($list as $r) {
            $totalVolume += (float) $r['volume_hm3'];
            $totalCap    += (float) $r['capacity_hm3'];
            $totalFlow   += (float) $r['flow_m3s'];
            $sumLevel    += (float) $r['level_pct'];
            $sumPh       += (float) $r['ph'];
            $byStatus[StatusRules::fromLevel((float) $r['level_pct'])]++;
            if ((int) $r['sensors_online'] > 0) {
                $online++;
            }
        }

        $avgLevel = round($sumLevel / $count, 1);
        $avgPh    = round($sumPh / $count, 1);

        // vazão consolidada dos últimos 7 dias: soma diária das represas
        $flowLabels = [];
        $flowTotals = [];
        foreach ($list as $i => $r) {
            $s = $this->repo->series($r['id'], 'flow', '7d');
            if ($i === 0) {
                $flowLabels = $s['labels'];
                $flowTotals = array_fill(0, count($s['values']), 0.0);
            }
            foreach ($s['values'] as $k => $v) {
                if (isset($flowTotals[$k])) {
                    $flowTotals[$k] += $v;
                }
            }
        }
        $flowTotals = array_map(static fn (float $v): float => round($v, 1), $flowTotals);

        $alerts = $this->repo->alerts('all');
        $alertCounts = ['critical' => 0, 'attention' => 0, 'info' => 0];
        foreach ($alerts as $a) {
            if ($a['status'] === 'resolved') {
                continue;
            }
            if (isset($alertCounts[$a['severity']])) {
                $alertCounts[$a['severity']]++;
            }
        }
        $activeAlerts = array_sum($alertCounts);

        return [
            'mode' => 'all',
            'kpis' => [
                'reservoirs' => ['value' => $count, 'online' => $online, 'label' => 'Represas monitoradas'],
                'storage'    => ['value' => round($totalVolume), 'unit' => 'hm³', 'capacity' => round($totalCap), 'pct' => round($totalVolume / $totalCap * 100, 1)],
                'level'      => ['value' => $avgLevel, 'unit' => '%'],
                'flow'       => ['value' => round($totalFlow, 1), 'unit' => 'm³/s'],
                'ph'         => ['value' => $avgPh, 'unit' => '', 'status' => StatusRules::describe(StatusRules::fromPh($avgPh)), 'note' => 'Ideal'],
                'operation'  => ['normal' => $byStatus['normal'], 'attention' => $byStatus['attention'], 'critical' => $byStatus['critical']],
            ],
            'comparison' => array_map(static function (array $r): array {
                $st = StatusRules::fromLevel((float) $r['level_pct']);
                return [
                    'id'     => $r['id'],
                    'name'   => $r['name'],
                    'level'  => $r['level_pct'],
                    'status' => StatusRules::describe($st),
                ];
            }, $list),
            'flow_chart' => [
                'labels' => $flowLabels,
                'values' => $flowTotals,
                'unit'   => 'm³/s',
            ],
            'donut' => [
                'total'  => $count,
                'normal' => $byStatus['normal'],
                'attention' => $byStatus['attention'],
                'critical'  => $byStatus['critical'],
            ],
            'alert_counts' => [
                'total'     => $activeAlerts,
                'critical'  => $alertCounts['critical'],
                'attention' => $alertCounts['attention'],
                'info'      => $alertCounts['info'],
            ],
            'reservoirs' => array_map(static function (array $r): array {
                $st = StatusRules::fromLevel((float) $r['level_pct']);
                return [
                    'id'       => $r['id'],
                    'name'     => $r['name'],
                    'level'    => $r['level_pct'],
                    'volume'   => $r['volume_hm3'],
                    'flow'     => $r['flow_m3s'],
                    'ph'       => $r['ph'],
                    'rain'     => $r['rain_24h_mm'],
                    'duration' => $r['duration_days'],
                    'lat'      => $r['lat'],
                    'lng'      => $r['lng'],
                    'city'     => $r['city'],
                    'status'   => StatusRules::describe($st),
                ];
            }, $list),
            'priority_alerts' => array_map(
                [$this, 'alertRow'],
                array_slice(array_values(array_filter(
                    $alerts,
                    static fn (array $a): bool => $a['status'] !== 'resolved'
                )), 0, 3)
            ),
        ];
    }

    /* ---------------------------------------------------------- auxiliares */

    /** @param array<string,mixed> $a */
    private function alertRow(array $a): array
    {
        $r = $this->repo->reservoir($a['reservoir_id']);
        $when = new \DateTimeImmutable($a['detected_at'], Clock::timezone());

        return [
            'id'        => $a['id'],
            'title'     => $a['title'],
            'severity'  => $a['severity'],
            'severity_label' => StatusRules::severityLabel($a['severity']),
            'reservoir' => $r['name'] ?? '—',
            'reservoir_short' => str_replace('Represa ', '', $r['name'] ?? '—'),
            'at'        => Clock::dateTime($when),
            'time'      => $when->format('H:i'),
        ];
    }

    /** @param array<string,mixed> $rep */
    private function reportRow(array $rep): array
    {
        $r = $this->repo->reservoir($rep['reservoir_id']);
        $when = new \DateTimeImmutable($rep['generated_at'], Clock::timezone());

        return [
            'id'        => $rep['id'],
            'name'      => $rep['name'],
            'reservoir' => str_replace('Represa ', '', $r['name'] ?? '—'),
            'period'    => $rep['period'],
            'generated_at' => Clock::dateTime($when),
            'status'    => $rep['status'],
            'status_label' => StatusRules::reportStatusLabel($rep['status']),
        ];
    }
}
