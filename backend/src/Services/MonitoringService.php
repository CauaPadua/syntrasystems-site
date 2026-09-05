<?php
/**
 * Aquapulse — service das oito telas de Monitoramento.
 *
 * Cada método monta o payload de uma tela detalhada. Toda análise detalhada
 * exige uma represa específica: nunca consolida métricas automaticamente.
 *
 * Depende apenas de MonitoringRepositoryInterface.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;
use DateTimeImmutable;

final class MonitoringService
{
    private MonitoringRepositoryInterface $repo;

    public function __construct(MonitoringRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }


    /**
     * Converte um percentual de capacidade em cota (metros).
     *
     * A relação é derivada de dois pares conhecidos da própria represa —
     * (nível atual, cota atual) e (limite crítico, cota crítica) — para que
     * cota e percentual nunca se contradigam. Assim a "cota de atenção"
     * exibida é sempre a cota correspondente a StatusRules::LEVEL_ATTENTION,
     * e não um número guardado à parte que poderia divergir.
     */
    private function cota(array $r, float $levelPct): float
    {
        $nivelAtual  = (float) $r['level_pct'];
        $cotaAtual   = (float) $r['cota_m'];
        $nivelCrit   = (float) StatusRules::LEVEL_CRITICAL;
        $cotaCrit    = (float) $r['cota_critical_m'];

        $vao = $nivelCrit - $nivelAtual;
        if (abs($vao) < 0.001) {
            return round($cotaAtual, 1);
        }

        $inclinacao = ($cotaCrit - $cotaAtual) / $vao;
        return round($cotaAtual + ($levelPct - $nivelAtual) * $inclinacao, 1);
    }
    /** Cabeçalho comum das telas detalhadas (represa + telemetria). */
    private function head(array $r): array
    {
        $sensors = $this->repo->sensors($r['id']);
        $offline = array_filter($sensors, static fn (array $s): bool => $s['status'] !== 'online');

        return [
            'id'        => $r['id'],
            'name'      => $r['name'],
            'code'      => $r['code'],
            'telemetry' => $offline === [] ? 'online' : 'partial',
            'telemetry_label' => $offline === [] ? 'Telemetria online' : 'Telemetria parcial',
        ];
    }

    /* ------------------------------------------------- 1. volume de vazão */

    public function flow(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $in  = $this->repo->series($reservoirId, 'inflow', $period);
        $out = $this->repo->series($reservoirId, 'outflow', $period);

        $daily = $this->repo->series($reservoirId, 'inflow', '7d');
        $dailyOut = $this->repo->series($reservoirId, 'outflow', '7d');

        $balance = round((float) $r['inflow_m3s'] - (float) $r['outflow_m3s'], 1);

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'flow'    => ['value' => $r['flow_m3s'], 'unit' => 'm³/s', 'note' => 'Atualizado há 2 min'],
                'inflow'  => ['value' => $r['inflow_m3s'], 'unit' => 'm³/s', 'note' => 'Média nas últimas 24h'],
                'outflow' => ['value' => $r['outflow_m3s'], 'unit' => 'm³/s', 'note' => 'Média nas últimas 24h'],
                'balance' => ['value' => $balance, 'unit' => 'm³/s', 'note' => 'Nas últimas 24h', 'positive' => $balance >= 0],
            ],
            'realtime' => [
                'labels'  => $in['labels'],
                'inflow'  => $in['values'],
                'outflow' => $out['values'],
                'unit'    => 'm³/s',
            ],
            'condition' => [
                'status'   => StatusRules::describe('normal'),
                'text'     => 'Afluência e defluência dentro da faixa operacional esperada.',
                'badge'    => 'Sistema estável',
                'range'    => 'Faixa ideal: 40,0 – 80,0 m³/s',
                'min'      => 40.0,
                'max'      => 80.0,
                'value'    => $r['flow_m3s'],
            ],
            'daily' => [
                'labels'  => $daily['labels'],
                'inflow'  => $daily['values'],
                'outflow' => $dailyOut['values'],
                'unit'    => 'm³/s',
            ],
            'sensors'  => $this->repo->sensors($reservoirId),
            'readings' => $this->repo->readings($reservoirId, 'flow', 5),
        ];
    }

    /* --------------------------------------------- 2. nível do reservatório */

    public function level(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'level', $period);
        $status = StatusRules::fromLevel((float) $r['level_pct']);

        // a cota de atenção é sempre a cota do limite de atenção — derivada,
        // nunca guardada em paralelo
        $cotaAtencao = $this->cota($r, (float) StatusRules::LEVEL_ATTENTION);

        // tendência dos próximos 7 dias (projeção demonstrativa)
        $forecastLabels = [];
        $forecastValues = [];
        $cursor = Clock::now();
        $last = (float) $r['level_pct'];
        for ($i = 1; $i <= 7; $i++) {
            $cursor = $cursor->modify('+1 day');
            $forecastLabels[] = Clock::shortDate($cursor);
            $forecastValues[] = round($last + sin($i * 0.8) * 0.6 + $i * 0.12, 1);
        }

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'level'     => ['value' => $r['level_pct'], 'unit' => '%', 'note' => 'Em relação à capacidade total', 'status' => StatusRules::describe($status)],
                'cota'      => ['value' => $r['cota_m'], 'unit' => 'm', 'note' => 'Cota em metros (msnm)'],
                'variation' => ['value' => $r['cota_variation_m'], 'unit' => 'm', 'note' => 'Variação nas últimas 24h', 'positive' => (float) $r['cota_variation_m'] >= 0],
                'available' => ['value' => round(100 - (float) $r['level_pct'], 1), 'unit' => '%', 'note' => 'Em relação à capacidade total'],
            ],
            'history' => [
                'labels'    => $series['labels'],
                'values'    => $series['values'],
                'unit'      => '%',
                'current'   => $r['level_pct'],
                'attention' => StatusRules::LEVEL_ATTENTION,
                'critical'  => StatusRules::LEVEL_CRITICAL,
                'attention_label' => 'Cota de atenção ' . number_format($cotaAtencao, 1, ',', '.') . ' m',
                'critical_label'  => 'Cota crítica ' . number_format((float) $r['cota_critical_m'], 1, ',', '.') . ' m',

                // mesma série em metros, para a tela de Níveis (que trabalha
                // em cota, não em percentual)
                'cota'            => array_map(fn (float $v): float => $this->cota($r, $v), $series['values']),
                'cota_current'    => (float) $r['cota_m'],
                'cota_attention'  => $cotaAtencao,
                'cota_critical'   => (float) $r['cota_critical_m'],
                'cota_spill'      => (float) $r['cota_spill_m'],
                'cota_alert'      => (float) $r['cota_alert_m'],
            ],
            'capacity' => [
                'level'  => $r['level_pct'],
                'status' => StatusRules::describe($status),
                'bands'  => [
                    ['label' => 'Acima de 90%', 'status' => 'critical',  'text' => 'Crítico',  'from' => 90, 'to' => 100],
                    ['label' => '80% – 90%',    'status' => 'attention', 'text' => 'Atenção',  'from' => 80, 'to' => 90],
                    ['label' => '0% – 80%',     'status' => 'normal',    'text' => 'Normal',   'from' => 0,  'to' => 80],
                ],
            ],
            'forecast' => [
                'labels'   => $forecastLabels,
                'values'   => $forecastValues,
                'observed' => $r['level_pct'],
                'unit'     => '%',
                'cota'     => array_map(fn (float $v): float => $this->cota($r, $v), $forecastValues),
            ],
            'bands' => [
                ['status' => 'normal',    'label' => 'Normal',  'range' => '0% – 80%',      'text' => 'Nível operacional seguro.'],
                ['status' => 'attention', 'label' => 'Atenção', 'range' => '80% – 90%',     'text' => 'Atenção para possíveis cheias.'],
                ['status' => 'critical',  'label' => 'Crítico', 'range' => 'Acima de 90%',  'text' => 'Risco alto de transbordamento.'],
            ],
            // cada leitura recebe a mesma classificação usada em todo o sistema
            'readings' => array_map(
                function (array $row): array {
                    $row['status'] = StatusRules::describe(StatusRules::fromLevel((float) $row['level']));
                    return $row;
                },
                $this->repo->readings($reservoirId, 'level', 5)
            ),
        ];
    }

    /* ------------------------------------------------------------- 3. pH */

    public function ph(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'ph', $period);
        $daily  = $this->repo->series($reservoirId, 'ph', '7d');
        $status = StatusRules::fromPh((float) $r['ph']);

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'ph'        => ['value' => $r['ph'], 'note' => 'Atualizado há 2 min'],
                'min'       => ['value' => $r['ph_min'], 'note' => 'Nas últimas 24h'],
                'max'       => ['value' => $r['ph_max'], 'note' => 'Nas últimas 24h'],
                'condition' => ['status' => StatusRules::describe($status), 'label' => $status === 'normal' ? 'Ideal' : 'Atenção', 'note' => 'Dentro da faixa ideal'],
            ],
            'variation' => [
                'labels' => $series['labels'],
                'values' => $series['values'],
                'ideal_min' => StatusRules::PH_MIN,
                'ideal_max' => StatusRules::PH_MAX,
            ],
            'scale' => [
                'value'   => $r['ph'],
                'min'     => 0,
                'max'     => 14,
                'neutral' => 7,
                'label'   => $status === 'normal' ? 'Ideal' : 'Atenção',
                'range'   => 'Faixa ideal: 6,5 – 8,5',
            ],
            'daily' => [
                'labels' => $daily['labels'],
                'values' => $daily['values'],
                'ideal_min' => StatusRules::PH_MIN,
                'ideal_max' => StatusRules::PH_MAX,
            ],
            'points'   => $this->repo->phPoints($reservoirId),
            'readings' => $this->repo->readings($reservoirId, 'ph', 5),
            'note'     => 'Faixa operacional configurada: 6,5 a 8,5',
        ];
    }

    /* -------------------------------------------- 4. volume armazenado */

    public function storage(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'storage', $period);
        $available = round((float) $r['capacity_hm3'] - (float) $r['volume_hm3'], 0);

        // balanço hídrico: entradas e saídas diárias (demonstrativo)
        $balanceLabels = [];
        $inflow = [];
        $outflow = [];
        $cursor = (clone Clock::now())->modify('-29 days');
        for ($i = 0; $i < 30; $i += 3) {
            $balanceLabels[] = Clock::shortDate($cursor);
            $inflow[]  = round(60 + sin($i * 0.7) * 45, 0);
            $outflow[] = round(-(35 + cos($i * 0.5) * 25), 0);
            $cursor = $cursor->modify('+3 days');
        }

        $first = $series['values'][0] ?? (float) $r['volume_hm3'];
        $gain  = round((float) $r['volume_hm3'] - $first, 0);

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'volume'    => ['value' => $r['volume_hm3'], 'unit' => 'hm³', 'note' => 'Em relação à capacidade total'],
                'capacity'  => ['value' => $r['capacity_hm3'], 'unit' => 'hm³', 'note' => 'Capacidade máxima do reservatório'],
                'occupancy' => ['value' => $r['level_pct'], 'unit' => '%', 'note' => 'Em relação à capacidade total'],
                'available' => ['value' => $available, 'unit' => 'hm³', 'note' => 'Disponível para utilização'],
            ],
            'evolution' => [
                'labels'   => $series['labels'],
                'values'   => $series['values'],
                'capacity' => $r['capacity_hm3'],
                'unit'     => 'hm³',
                'current'  => $r['volume_hm3'],
            ],
            'occupancy' => [
                'pct'       => $r['level_pct'],
                'stored'    => $r['volume_hm3'],
                'available' => $available,
                'available_pct' => round(100 - (float) $r['level_pct'], 1),
                'capacity'  => $r['capacity_hm3'],
            ],
            'balance' => [
                'labels'  => $balanceLabels,
                'inflow'  => $inflow,
                'outflow' => $outflow,
                'unit'    => 'hm³',
            ],
            'distribution' => [
                ['label' => 'Volume útil', 'note' => 'Para abastecimento e usos', 'value' => $r['useful_volume_hm3'], 'pct' => round((float) $r['useful_volume_hm3'] / (float) $r['capacity_hm3'] * 100, 1), 'status' => 'normal'],
                ['label' => 'Reserva técnica', 'note' => 'Segurança operacional', 'value' => $r['technical_reserve_hm3'], 'pct' => round((float) $r['technical_reserve_hm3'] / (float) $r['capacity_hm3'] * 100, 1), 'status' => 'attention'],
                ['label' => 'Volume disponível', 'note' => 'Disponível para utilização', 'value' => $available, 'pct' => round($available / (float) $r['capacity_hm3'] * 100, 1), 'status' => 'info'],
            ],
            'history' => $this->repo->readings($reservoirId, 'storage', 5),
            'insight' => [
                'value' => $gain,
                'unit'  => 'hm³',
                'pct'   => $first > 0 ? round($gain / $first * 100, 1) : 0,
                'text'  => 'Diferença entre o primeiro e o último dia do período selecionado.',
            ],
        ];
    }

    /* -------------------------------------------------- 5. precipitação */

    public function precipitation(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'precipitation', $period);

        // acumulado progressivo
        $accum = [];
        $sum = 0.0;
        foreach ($series['values'] as $v) {
            $sum += $v;
            $accum[] = round($sum, 1);
        }

        $rain = (float) $r['rain_24h_mm'];
        $intensity = $rain >= 20 ? 'Alta' : ($rain >= 10 ? 'Moderada' : 'Baixa');
        $intensityStatus = $rain >= 20 ? 'attention' : ($rain >= 10 ? 'attention' : 'normal');

        // previsão de 5 dias (demonstrativa)
        $forecast = [];
        $icons = ['cloud-rain', 'cloud-rain', 'cloud-sun', 'cloud-sun', 'cloud-rain'];
        $mm = [16, 20, 8, 5, 12];
        $cursor = Clock::now();
        $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        for ($i = 0; $i < 5; $i++) {
            $cursor = $cursor->modify('+1 day');
            $forecast[] = [
                'day'   => ucfirst($dias[(int) $cursor->format('w')]),
                'date'  => $cursor->format('d/m'),
                'icon'  => $icons[$i],
                'mm'    => $mm[$i],
            ];
        }

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'rain_24h'  => ['value' => $r['rain_24h_mm'], 'unit' => 'mm', 'note' => 'Atualizado há 2 min'],
                'rain_7d'   => ['value' => $r['rain_7d_mm'], 'unit' => 'mm', 'note' => 'Total nos últimos 7 dias'],
                'rain_month'=> ['value' => $r['rain_month_mm'], 'unit' => 'mm', 'note' => 'Total até o momento'],
                'intensity' => ['label' => $intensity, 'note' => 'Intensidade de chuva', 'status' => StatusRules::describe($intensityStatus)],
            ],
            'chart' => [
                'labels'      => $series['labels'],
                'daily'       => $series['values'],
                'accumulated' => $accum,
                'unit'        => 'mm',
                'total'       => $r['rain_7d_mm'],
            ],
            'current' => [
                'value'    => $r['rain_24h_mm'],
                'label'    => 'Chuva ' . mb_strtolower($intensity, 'UTF-8'),
                'humidity' => $r['humidity_pct'],
                'last'     => $r['last_reading_time'],
                'status'   => $intensityStatus,
            ],
            'basin' => array_map(static function (array $s): array {
                $mm = (float) $s['rain_24h'];
                return [
                    'name'   => $s['name'],
                    'mm'     => $mm,
                    'level'  => $mm >= 20 ? 'high' : ($mm >= 10 ? 'medium' : 'low'),
                ];
            }, $this->repo->rainStations($reservoirId)),
            'forecast' => $forecast,
            'stations' => $this->repo->rainStations($reservoirId),
            'warning'  => [
                'active' => $rain >= 15,
                'title'  => 'Possibilidade de chuva intensa nas próximas 48 horas',
                'text'   => 'Acompanhe as atualizações e mantenha atenção às condições climáticas.',
            ],
        ];
    }

    /* --------------------------------------------- 7. situação operacional */

    public function operation(string $reservoirId): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $sensors = $this->repo->sensors($reservoirId);
        $offline = count(array_filter($sensors, static fn (array $s): bool => $s['status'] !== 'online'));
        $status  = $offline > 0 ? 'attention' : 'normal';

        $alerts = array_filter(
            $this->repo->alerts($reservoirId),
            static fn (array $a): bool => $a['status'] !== 'resolved'
        );

        // O rótulo do KPI só usa uma severidade quando todos os alertas ativos
        // compartilham a mesma; com severidades misturadas ele vira "ativos" e
        // a nota descreve a composição.
        $bySeverity = [];
        foreach ($alerts as $a) {
            $bySeverity[$a['severity']] = ($bySeverity[$a['severity']] ?? 0) + 1;
        }

        $alertLabel = count($bySeverity) === 1
            ? mb_strtolower(StatusRules::severityLabel((string) array_key_first($bySeverity)))
            : 'ativos';

        $partes = [];
        foreach ($bySeverity as $severidade => $quantidade) {
            $partes[] = $quantidade . ' ' . mb_strtolower(StatusRules::severityLabel((string) $severidade));
        }
        $alertNote = $partes === []
            ? 'Nenhuma ocorrência em aberto.'
            : implode(' · ', $partes) . '. Requer atenção da equipe.';

        $generalNote = $offline > 0
            ? 'Há sensores fora de operação. Verifique a telemetria.'
            : 'Todos os sistemas operando dentro da normalidade.';

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'general'  => ['status' => StatusRules::describe($status), 'note' => $generalNote],
                'sensors'  => ['online' => $r['sensors_online'], 'total' => $r['sensors_total'], 'note' => round((float) $r['sensors_online'] / max(1, (int) $r['sensors_total']) * 100) . '% dos sensores em operação.'],
                'gates'    => ['online' => $r['gates_online'], 'total' => $r['gates_total'], 'note' => 'Todas as comportas operando normalmente.'],
                'alerts'   => ['count' => count($alerts), 'label' => $alertLabel, 'note' => $alertNote],
            ],
            'systems' => [
                ['id' => 'sensors',  'label' => 'Sensores',  'value' => $r['sensors_online'] . '/' . $r['sensors_total'], 'status' => $offline > 0 ? 'attention' : 'normal', 'icon' => 'signal'],
                ['id' => 'gates',    'label' => 'Comportas', 'value' => $r['gates_online'] . '/' . $r['gates_total'], 'status' => 'normal', 'icon' => 'gate'],
                ['id' => 'weather',  'label' => 'Estação meteorológica', 'value' => '1/1', 'status' => 'normal', 'icon' => 'cloud-rain'],
                ['id' => 'power',    'label' => 'Energia',   'value' => '2/2', 'status' => 'normal', 'icon' => 'zap'],
                ['id' => 'comm',     'label' => 'Comunicação', 'value' => '2/2', 'status' => 'normal', 'icon' => 'radio'],
                ['id' => 'maint',    'label' => 'Manutenção', 'value' => '1 pendência', 'status' => 'attention', 'icon' => 'wrench'],
            ],
            'availability' => [
                'general' => $r['availability_pct'],
                'status'  => StatusRules::describe('normal'),
                'items'   => [
                    ['label' => 'Telemetria',  'pct' => $r['telemetry_pct'],    'note' => $r['sensors_online'] . ' de ' . $r['sensors_total'] . ' online', 'icon' => 'radio'],
                    ['label' => 'Comunicação', 'pct' => $r['communication_pct'], 'note' => '2 de 2 links ativos', 'icon' => 'signal'],
                    ['label' => 'Energia',     'pct' => $r['power_pct'],         'note' => '2 de 2 fontes ativas', 'icon' => 'zap'],
                ],
            ],
            'components' => [
                ['name' => 'Nível do reservatório', 'status' => 'normal', 'at' => '22/05/2024 09:28'],
                ['name' => 'Vazão (afluente/defluente)', 'status' => 'normal', 'at' => '22/05/2024 09:29'],
                ['name' => 'pH da água', 'status' => 'normal', 'at' => '22/05/2024 09:27'],
                ['name' => 'Pluviômetros', 'status' => 'normal', 'at' => '22/05/2024 09:25'],
                ['name' => 'Comportas', 'status' => 'normal', 'at' => '22/05/2024 09:28'],
                ['name' => 'Energia', 'status' => 'normal', 'at' => '22/05/2024 09:29'],
            ],
            'events'       => array_map(static function (array $e): array {
                $when = new DateTimeImmutable($e['at'], Clock::timezone());
                return [
                    'at'        => Clock::dateTime($when),
                    'component' => $e['component'],
                    'event'     => $e['event'],
                    'priority'  => $e['priority'],
                    'priority_label' => StatusRules::severityLabel($e['priority']),
                    'status'    => $e['status'],
                    'status_label' => StatusRules::alertStatusLabel($e['status']),
                ];
            }, $this->repo->operationEvents($reservoirId)),
            'maintenances' => array_map(static function (array $m): array {
                $when = new DateTimeImmutable($m['date'], Clock::timezone());
                return [
                    'date'      => $when->format('d/m/Y'),
                    'equipment' => $m['equipment'],
                    'type'      => $m['type'],
                    'priority'  => $m['priority'],
                    'priority_label' => $m['priority'] === 'attention' ? 'Atenção' : 'Baixa',
                ];
            }, $this->repo->maintenances($reservoirId)),
        ];
    }

    /* ------------------------------------------- 8. comparativo de vazão */

    public function flowComparison(string $reservoirId, string $current, string $previous): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $cur = $this->repo->series($reservoirId, 'flow', '7d');
        $prevValues = array_map(
            static fn (float $v, int $i): float => round($v - 6.3 + sin($i * 1.3) * 1.4, 1),
            $cur['values'],
            array_keys($cur['values'])
        );

        $avgCur  = round(array_sum($cur['values']) / max(1, count($cur['values'])), 1);
        $avgPrev = round(array_sum($prevValues) / max(1, count($prevValues)), 1);
        $delta   = round($avgCur - $avgPrev, 1);
        $pct     = $avgPrev > 0 ? round($delta / $avgPrev * 100, 1) : 0.0;

        $diffs = [];
        $rows  = [];
        $maxDiff = 0.0;
        $maxDay  = '';
        $bestDay = '';
        $bestVal = -INF;
        $worstDay = '';
        $worstVal = INF;
        $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        $cursor = (clone Clock::now())->modify('-6 days');

        foreach ($cur['values'] as $i => $v) {
            $p = $prevValues[$i];
            $d = round($v - $p, 1);
            $diffs[] = $d;

            if (abs($d) > abs($maxDiff)) {
                $maxDiff = $d;
                $maxDay  = $cur['labels'][$i];
            }
            if ($v > $bestVal) { $bestVal = $v; $bestDay = $cur['labels'][$i] . ' (' . $dias[(int) $cursor->format('w')] . ')'; }
            if ($v < $worstVal) { $worstVal = $v; $worstDay = $cur['labels'][$i] . ' (' . $dias[(int) $cursor->format('w')] . ')'; }

            $rows[] = [
                'day'       => $cur['labels'][$i] . ' (' . $dias[(int) $cursor->format('w')] . ')',
                'current'   => $v,
                'previous'  => $p,
                'diff'      => $d,
                'pct'       => $p > 0 ? round($d / $p * 100, 1) : 0.0,
                'status'    => $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'reservoir' => $this->head($r),
            'periods'   => ['current' => $current, 'previous' => $previous],
            'kpis' => [
                'current'  => ['value' => $avgCur, 'unit' => 'm³/s', 'note' => 'Período ' . $current],
                'previous' => ['value' => $avgPrev, 'unit' => 'm³/s', 'note' => 'Período ' . $previous],
                'variation'=> ['value' => $pct, 'unit' => '%', 'note' => ($delta >= 0 ? '+' : '') . number_format($delta, 1, ',', '.') . ' m³/s', 'positive' => $delta >= 0],
                'max_diff' => ['value' => $maxDiff, 'unit' => 'm³/s', 'note' => 'Em ' . $maxDay, 'positive' => $maxDiff >= 0],
            ],
            'chart' => [
                'labels'   => $cur['labels'],
                'current'  => $cur['values'],
                'previous' => $prevValues,
                'unit'     => 'm³/s',
            ],
            'diff_chart' => [
                'labels' => $cur['labels'],
                'values' => $diffs,
                'unit'   => 'm³/s',
            ],
            'in_out' => [
                'labels'  => $cur['labels'],
                'inflow'  => $this->repo->series($reservoirId, 'inflow', '7d')['values'],
                'outflow' => $this->repo->series($reservoirId, 'outflow', '7d')['values'],
                'unit'    => 'm³/s',
            ],
            'summary' => [
                'best'     => ['label' => 'Melhor dia', 'day' => $bestDay, 'value' => $bestVal, 'unit' => 'm³/s'],
                'worst'    => ['label' => 'Menor vazão', 'day' => $worstDay, 'value' => $worstVal, 'unit' => 'm³/s'],
                'average'  => ['label' => 'Média semanal', 'current' => $avgCur, 'previous' => $avgPrev, 'unit' => 'm³/s'],
                'trend'    => ['label' => 'Tendência', 'value' => $delta >= 0 ? 'Alta' : 'Baixa', 'note' => 'Vazão ' . ($delta >= 0 ? 'acima' : 'abaixo') . ' do período anterior'],
            ],
            'rows' => $rows,
            'insight' => [
                'delta' => $delta,
                'text'  => 'A vazão média ' . ($delta >= 0 ? 'aumentou' : 'diminuiu') . ' ' . number_format(abs($delta), 1, ',', '.') . ' m³/s em relação ao período anterior.',
            ],
        ];
    }
}
