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
 *
 * Usado por api/v1/overview.php. O resultado é lido por assets/js/pages/overview.js,
 * que escolhe o layout da tela pelo campo "mode" ('single' ou 'all').
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;

final class OverviewService
{
    private MonitoringRepositoryInterface $repo;                                  // fonte dos dados (injetada pelo endpoint)

    public function __construct(MonitoringRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Ponto de entrada do service.
     *
     * @param string $companyId   empresa filtrada ou 'all' (só vale no modo consolidado)
     * @param string $reservoirId represa escolhida ou 'all'
     * @param string $period      período dos gráficos (24h, 7d, 30d, 90d, 12m)
     * @return array<string,mixed>
     */
    public function build(string $companyId, string $reservoirId, string $period): array
    {
        return $reservoirId === 'all'                                             // decide o modo da tela pela represa selecionada
            ? $this->consolidated($companyId, $period)                            // nenhuma represa: soma e médias de todas
            : $this->single($reservoirId, $period);                               // uma represa: detalhes só dela
    }

    /* ------------------------------------------------ uma represa selecionada */

    /** Dados da visão geral de uma única represa. Array vazio se ela não existir. */
    private function single(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $status = StatusRules::fromLevel((float) $r['level_pct']);               // classifica a represa pelo nível (normal/atenção/crítico)
        $levelSeries = $this->repo->series($reservoirId, 'cota', $period);       // série da cota (altura da água em metros) para o gráfico de nível
        $flowSeries  = $this->repo->series($reservoirId, 'flow', $period);       // série da vazão (m³/s) para o gráfico de vazão

        // linha tracejada de referência: cota de vertimento
        $spill = array_fill(0, count($levelSeries['labels']), (float) $r['cota_spill_m']); // o mesmo valor repetido em todos os pontos forma uma linha horizontal

        $alerts = array_slice($this->repo->alerts($reservoirId), 0, 3);          // só os 3 primeiros alertas da represa
        $reports = array_slice($this->repo->reports($reservoirId), 0, 5);        // só os 5 primeiros relatórios

        return [
            'mode' => 'single',
            'reservoir' => [                                                      // cabeçalho da represa e posição no mapa
                'id'      => $r['id'],
                'name'    => $r['name'],
                'code'    => $r['code'],
                'city'    => $r['city'],
                'basin'   => $r['basin'],                                         // bacia hidrográfica
                'lat'     => $r['lat'],
                'lng'     => $r['lng'],
                'status'  => StatusRules::describe($status),
            ],
            'kpis' => [                                                           // um item por card do topo da tela
                'level'        => ['value' => $r['level_pct'], 'unit' => '%', 'cota' => $r['cota_m'], 'status' => StatusRules::describe($status)],
                'storage'      => ['value' => $r['volume_hm3'], 'unit' => 'hm³', 'capacity' => $r['capacity_hm3'], 'occupancy' => $r['level_pct']],
                'flow'         => ['value' => $r['flow_m3s'], 'unit' => 'm³/s', 'trend' => -8, 'trend_label' => 'vs ontem'],                     // tendência -8% fixa (demonstrativa)
                'ph'           => ['value' => $r['ph'], 'unit' => '', 'status' => StatusRules::describe(StatusRules::fromPh((float) $r['ph'])), 'note' => 'Neutro'],
                'rain'         => ['value' => $r['rain_24h_mm'], 'unit' => 'mm', 'trend' => 15, 'trend_label' => 'vs ontem', 'note' => 'Na bacia'], // tendência +15% fixa (demonstrativa)
                'duration'     => ['value' => $r['duration_days'], 'unit' => 'dias', 'note' => 'Com o volume atual'],
                'operation'    => ['status' => StatusRules::describe($status), 'note' => StatusRules::levelNote($status)],                          // a frase acompanha o mesmo status do nível
            ],
            'level_chart' => [
                'labels'   => $levelSeries['labels'],
                'observed' => $levelSeries['values'],                             // linha contínua: cota medida
                'spill'    => $spill,                                             // linha tracejada: limite de vertimento
                'unit'     => 'm',
                'current'  => $r['cota_m'],
                'spill_label' => 'Cota de vertimento: ' . number_format((float) $r['cota_spill_m'], 1, ',', '.') . ' m',
            ],
            'flow_chart' => [
                'labels'   => $flowSeries['labels'],
                'current'  => $flowSeries['values'],                              // período atual
                'previous' => array_map(                                          // "período anterior" demonstrativo: 93% de cada valor atual
                    static fn (float $v): float => round($v * 0.93, 1),
                    $flowSeries['values']
                ),
                'unit' => 'm³/s',
            ],
            'alerts'  => array_map([$this, 'alertRow'], $alerts),                 // converte cada alerta para o formato da tabela
            'reports' => array_map([$this, 'reportRow'], $reports),               // idem para relatórios
        ];
    }

    /* -------------------------------------------------- todas as represas */

    /**
     * Dados consolidados de todas as represas (opcionalmente de uma empresa).
     *
     * Percorre as represas uma vez somando volumes, capacidades e vazões, e
     * acumulando nível e pH para calcular médias; em seguida soma as séries de
     * vazão dia a dia e conta os alertas ainda não resolvidos por gravidade.
     */
    private function consolidated(string $companyId, string $period): array
    {
        $list = $this->repo->reservoirs($companyId);                              // represas no escopo do filtro de empresa

        if ($list === []) {                                                       // empresa sem represas: a tela mostra o estado "sem dados"
            return ['mode' => 'all', 'empty' => true, 'kpis' => [], 'reservoirs' => []];
        }

        $count       = count($list);
        $totalVolume = 0.0;                                                       // soma do volume armazenado (hm³)
        $totalCap    = 0.0;                                                       // soma da capacidade máxima (hm³)
        $totalFlow   = 0.0;                                                       // soma das vazões (m³/s)
        $sumLevel    = 0.0;                                                       // acumulador para a média de nível
        $sumPh       = 0.0;                                                       // acumulador para a média de pH
        $byStatus    = ['normal' => 0, 'attention' => 0, 'critical' => 0];        // quantas represas em cada faixa (gráfico de rosca)
        $online      = 0;                                                         // represas com pelo menos um sensor comunicando

        foreach ($list as $r) {                                                   // uma passada por represa acumulando os totais
            $totalVolume += (float) $r['volume_hm3'];
            $totalCap    += (float) $r['capacity_hm3'];
            $totalFlow   += (float) $r['flow_m3s'];
            $sumLevel    += (float) $r['level_pct'];
            $sumPh       += (float) $r['ph'];
            $byStatus[StatusRules::fromLevel((float) $r['level_pct'])]++;         // incrementa o contador da faixa em que a represa está
            if ((int) $r['sensors_online'] > 0) {
                $online++;
            }
        }

        $avgLevel = round($sumLevel / $count, 1);                                 // média simples do nível (não ponderada pela capacidade)
        $avgPh    = round($sumPh / $count, 1);

        // vazão consolidada dos últimos 7 dias: soma diária das represas
        $flowLabels = [];
        $flowTotals = [];
        foreach ($list as $i => $r) {                                             // percorre as represas somando suas séries ponto a ponto
            $s = $this->repo->series($r['id'], 'flow', '7d');                     // sempre 7 dias, independentemente do $period recebido
            if ($i === 0) {                                                       // a primeira represa define os rótulos e o tamanho do array de totais
                $flowLabels = $s['labels'];
                $flowTotals = array_fill(0, count($s['values']), 0.0);
            }
            foreach ($s['values'] as $k => $v) {                                  // soma o valor de cada dia ao total daquele dia
                if (isset($flowTotals[$k])) {                                     // ignora pontos extras se alguma série for mais longa que a primeira
                    $flowTotals[$k] += $v;
                }
            }
        }
        $flowTotals = array_map(static fn (float $v): float => round($v, 1), $flowTotals); // arredonda cada total para 1 casa decimal

        $alerts = $this->repo->alerts('all');                                     // todos os alertas, de todas as represas
        $alertCounts = ['critical' => 0, 'attention' => 0, 'info' => 0];
        foreach ($alerts as $a) {                                                 // conta só os alertas ativos por gravidade
            if ($a['status'] === 'resolved') {                                    // alerta resolvido não entra na contagem de ativos
                continue;
            }
            if (isset($alertCounts[$a['severity']])) {                            // ignora gravidades desconhecidas
                $alertCounts[$a['severity']]++;
            }
        }
        $activeAlerts = array_sum($alertCounts);                                  // total de alertas ativos

        return [
            'mode' => 'all',
            'kpis' => [
                'reservoirs' => ['value' => $count, 'online' => $online, 'label' => 'Represas monitoradas'],
                'storage'    => ['value' => round($totalVolume), 'unit' => 'hm³', 'capacity' => round($totalCap), 'pct' => round($totalVolume / $totalCap * 100, 1)], // ocupação total = volume somado / capacidade somada
                'level'      => ['value' => $avgLevel, 'unit' => '%'],
                'flow'       => ['value' => round($totalFlow, 1), 'unit' => 'm³/s'],
                'ph'         => ['value' => $avgPh, 'unit' => '', 'status' => StatusRules::describe(StatusRules::fromPh($avgPh)), 'note' => 'Ideal'],
                'operation'  => ['normal' => $byStatus['normal'], 'attention' => $byStatus['attention'], 'critical' => $byStatus['critical']],
            ],
            'comparison' => array_map(static function (array $r): array {        // barras horizontais "comparativo entre represas"
                $st = StatusRules::fromLevel((float) $r['level_pct']);
                return [
                    'id'     => $r['id'],
                    'name'   => $r['name'],
                    'level'  => $r['level_pct'],
                    'status' => StatusRules::describe($st),                       // define a cor da barra
                ];
            }, $list),
            'flow_chart' => [
                'labels' => $flowLabels,
                'values' => $flowTotals,
                'unit'   => 'm³/s',
            ],
            'donut' => [                                                          // gráfico de rosca "situação geral"
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
            'reservoirs' => array_map(static function (array $r): array {        // linhas da tabela-resumo e marcadores do mapa
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
            'priority_alerts' => array_map(                                       // os 3 primeiros alertas não resolvidos
                [$this, 'alertRow'],
                array_slice(array_values(array_filter(                            // array_values reindexa depois do filtro, para o slice pegar os 3 primeiros
                    $alerts,
                    static fn (array $a): bool => $a['status'] !== 'resolved'
                )), 0, 3)
            ),
        ];
    }

    /* ---------------------------------------------------------- auxiliares */

    /**
     * Converte um alerta do repositório no formato de linha da tela.
     *
     * @param array<string,mixed> $a
     */
    private function alertRow(array $a): array
    {
        $r = $this->repo->reservoir($a['reservoir_id']);                         // busca o nome da represa do alerta
        $when = new \DateTimeImmutable($a['detected_at'], Clock::timezone());    // data de detecção lida no fuso de Brasília

        return [
            'id'        => $a['id'],
            'title'     => $a['title'],
            'severity'  => $a['severity'],
            'severity_label' => StatusRules::severityLabel($a['severity']),
            'reservoir' => $r['name'] ?? '—',                                     // "—" se a represa não for encontrada
            'reservoir_short' => str_replace('Represa ', '', $r['name'] ?? '—'), // "Represa Santa Clara" -> "Santa Clara" (economiza espaço na tabela)
            'at'        => Clock::dateTime($when),                                // "22/05/2024 09:15"
            'time'      => $when->format('H:i'),                                  // só o horário
        ];
    }

    /**
     * Converte um relatório do repositório no formato de linha da tela.
     *
     * @param array<string,mixed> $rep
     */
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
            'status'    => $rep['status'],                                        // chave para a cor do badge
            'status_label' => StatusRules::reportStatusLabel($rep['status']),     // texto em português do badge
        ];
    }
}
