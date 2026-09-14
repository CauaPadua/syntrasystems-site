<?php
/**
 * Aquapulse — service das oito telas de Monitoramento.
 *
 * Cada método monta o payload de uma tela detalhada. Toda análise detalhada
 * exige uma represa específica: nunca consolida métricas automaticamente.
 *
 * Depende apenas de MonitoringRepositoryInterface.
 *
 * Mapa método -> endpoint -> tela:
 *   flow()           -> api/v1/monitoring/flow.php            -> dashboard/monitoramento/vazao.php
 *   level()          -> api/v1/monitoring/level.php           -> monitoramento/nivel.php (e dashboard/niveis.php)
 *   ph()             -> api/v1/monitoring/ph.php              -> monitoramento/ph.php
 *   storage()        -> api/v1/monitoring/storage.php         -> monitoramento/volume.php
 *   precipitation()  -> api/v1/monitoring/precipitation.php   -> monitoramento/precipitacao.php
 *   operation()      -> api/v1/monitoring/operation.php       -> monitoramento/operacional.php
 *   flowComparison() -> api/v1/monitoring/flow-comparison.php -> monitoramento/comparativo.php
 * A tela 6 (duração da água) fica em DurationForecastService.
 *
 * Padrão de todos os métodos: busca a represa; se não existir devolve [] (o
 * endpoint transforma isso em "sem dados"); senão monta um array com as seções
 * que o JavaScript da tela espera (kpis, gráficos, tabelas, textos).
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;
use DateTimeImmutable;

final class MonitoringService
{
    private MonitoringRepositoryInterface $repo;                                     // fonte dos dados (mock ou banco), recebida pelo construtor

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
     *
     * Matematicamente é uma reta que passa pelos dois pontos conhecidos:
     * cota = cotaAtual + (nível - nívelAtual) × inclinação.
     */
    private function cota(array $r, float $levelPct): float
    {
        $nivelAtual  = (float) $r['level_pct'];                                      // ponto 1 da reta: nível atual (%)...
        $cotaAtual   = (float) $r['cota_m'];                                         // ...e a cota atual correspondente (m)
        $nivelCrit   = (float) StatusRules::LEVEL_CRITICAL;                          // ponto 2 da reta: 90%...
        $cotaCrit    = (float) $r['cota_critical_m'];                                // ...e a cota crítica cadastrada para a represa

        $vao = $nivelCrit - $nivelAtual;                                             // distância em % entre os dois pontos
        if (abs($vao) < 0.001) {                                                     // nível atual praticamente igual ao crítico: não dá para calcular a inclinação
            return round($cotaAtual, 1);                                             // (evita divisão por zero)
        }

        $inclinacao = ($cotaCrit - $cotaAtual) / $vao;                               // quantos metros a cota sobe por ponto percentual
        return round($cotaAtual + ($levelPct - $nivelAtual) * $inclinacao, 1);      // aplica a reta ao percentual pedido
    }
    /** Cabeçalho comum das telas detalhadas (represa + telemetria). */
    private function head(array $r): array
    {
        $sensors = $this->repo->sensors($r['id']);
        $offline = array_filter($sensors, static fn (array $s): bool => $s['status'] !== 'online'); // sensores que não estão transmitindo

        return [
            'id'        => $r['id'],
            'name'      => $r['name'],
            'code'      => $r['code'],                                               // código exibido na barra de contexto (ex.: RSC-001)
            'telemetry' => $offline === [] ? 'online' : 'partial',                   // todos online = "online"; algum fora = "partial"
            'telemetry_label' => $offline === [] ? 'Telemetria online' : 'Telemetria parcial',
        ];
    }

    /* ------------------------------------------------- 1. volume de vazão */

    /** Tela "Volume de vazão": entradas (afluência) e saídas (defluência) de água. */
    public function flow(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $in  = $this->repo->series($reservoirId, 'inflow', $period);                 // água que entra na represa, no período escolhido
        $out = $this->repo->series($reservoirId, 'outflow', $period);                // água que sai, no mesmo período

        $daily = $this->repo->series($reservoirId, 'inflow', '7d');                  // gráfico diário usa sempre os últimos 7 dias
        $dailyOut = $this->repo->series($reservoirId, 'outflow', '7d');

        $balance = round((float) $r['inflow_m3s'] - (float) $r['outflow_m3s'], 1);  // balanço = entrada - saída; positivo significa que a represa está enchendo

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'flow'    => ['value' => $r['flow_m3s'], 'unit' => 'm³/s', 'note' => 'Atualizado há 2 min'],
                'inflow'  => ['value' => $r['inflow_m3s'], 'unit' => 'm³/s', 'note' => 'Média nas últimas 24h'],
                'outflow' => ['value' => $r['outflow_m3s'], 'unit' => 'm³/s', 'note' => 'Média nas últimas 24h'],
                'balance' => ['value' => $balance, 'unit' => 'm³/s', 'note' => 'Nas últimas 24h', 'positive' => $balance >= 0], // "positive" define seta/cor verde ou vermelha no card
            ],
            'realtime' => [                                                          // gráfico principal com as duas linhas
                'labels'  => $in['labels'],
                'inflow'  => $in['values'],
                'outflow' => $out['values'],
                'unit'    => 'm³/s',
            ],
            'condition' => [                                                         // card "condição da vazão" (texto e faixa fixos, demonstrativos)
                'status'   => StatusRules::describe('normal'),
                'text'     => 'Afluência e defluência dentro da faixa operacional esperada.',
                'badge'    => 'Sistema estável',
                'range'    => 'Faixa ideal: 40,0 – 80,0 m³/s',
                'min'      => 40.0,                                                  // limites da barra visual da faixa ideal
                'max'      => 80.0,
                'value'    => $r['flow_m3s'],                                        // posição do marcador dentro da faixa
            ],
            'daily' => [
                'labels'  => $daily['labels'],
                'inflow'  => $daily['values'],
                'outflow' => $dailyOut['values'],
                'unit'    => 'm³/s',
            ],
            'sensors'  => $this->repo->sensors($reservoirId),                        // lista de sensores de vazão
            'readings' => $this->repo->readings($reservoirId, 'flow', 5),            // tabela com as 5 leituras mais recentes
        ];
    }

    /* --------------------------------------------- 2. nível do reservatório */

    /** Tela "Nível do reservatório": percentual, cota em metros e faixas de risco. */
    public function level(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'level', $period);               // histórico do nível em %
        $status = StatusRules::fromLevel((float) $r['level_pct']);                   // faixa atual da represa

        // a cota de atenção é sempre a cota do limite de atenção — derivada,
        // nunca guardada em paralelo
        $cotaAtencao = $this->cota($r, (float) StatusRules::LEVEL_ATTENTION);        // cota (m) que corresponde a 80%

        // tendência dos próximos 7 dias (projeção demonstrativa)
        $forecastLabels = [];
        $forecastValues = [];
        $cursor = Clock::now();
        $last = (float) $r['level_pct'];                                             // a projeção parte do nível atual
        for ($i = 1; $i <= 7; $i++) {                                                // um ponto por dia, de amanhã até daqui a 7 dias
            $cursor = $cursor->modify('+1 day');
            $forecastLabels[] = Clock::shortDate($cursor);
            $forecastValues[] = round($last + sin($i * 0.8) * 0.6 + $i * 0.12, 1);  // oscilação suave (seno) + leve subida de 0,12 p.p. por dia; fórmula ilustrativa
        }

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'level'     => ['value' => $r['level_pct'], 'unit' => '%', 'note' => 'Em relação à capacidade total', 'status' => StatusRules::describe($status)],
                'cota'      => ['value' => $r['cota_m'], 'unit' => 'm', 'note' => 'Cota em metros (msnm)'],                // msnm = metros sobre o nível do mar
                'variation' => ['value' => $r['cota_variation_m'], 'unit' => 'm', 'note' => 'Variação nas últimas 24h', 'positive' => (float) $r['cota_variation_m'] >= 0],
                'available' => ['value' => round(100 - (float) $r['level_pct'], 1), 'unit' => '%', 'note' => 'Em relação à capacidade total'], // espaço livre = 100% - ocupação
            ],
            'history' => [                                                           // gráfico histórico com linhas de referência
                'labels'    => $series['labels'],
                'values'    => $series['values'],
                'unit'      => '%',
                'current'   => $r['level_pct'],
                'attention' => StatusRules::LEVEL_ATTENTION,                         // linha horizontal em 80%
                'critical'  => StatusRules::LEVEL_CRITICAL,                          // linha horizontal em 90%
                'attention_label' => 'Cota de atenção ' . number_format($cotaAtencao, 1, ',', '.') . ' m',
                'critical_label'  => 'Cota crítica ' . number_format((float) $r['cota_critical_m'], 1, ',', '.') . ' m',

                // mesma série em metros, para a tela de Níveis (que trabalha
                // em cota, não em percentual)
                'cota'            => array_map(fn (float $v): float => $this->cota($r, $v), $series['values']), // converte cada ponto % em metros
                'cota_current'    => (float) $r['cota_m'],
                'cota_attention'  => $cotaAtencao,
                'cota_critical'   => (float) $r['cota_critical_m'],
                'cota_spill'      => (float) $r['cota_spill_m'],                     // cota em que a água começa a verter pelo vertedouro
                'cota_alert'      => (float) $r['cota_alert_m'],
            ],
            'capacity' => [                                                          // medidor visual de capacidade com as três faixas
                'level'  => $r['level_pct'],
                'status' => StatusRules::describe($status),
                'bands'  => [
                    ['label' => 'Acima de 90%', 'status' => 'critical',  'text' => 'Crítico',  'from' => 90, 'to' => 100],
                    ['label' => '80% – 90%',    'status' => 'attention', 'text' => 'Atenção',  'from' => 80, 'to' => 90],
                    ['label' => '0% – 80%',     'status' => 'normal',    'text' => 'Normal',   'from' => 0,  'to' => 80],
                ],
            ],
            'forecast' => [                                                          // gráfico de tendência dos próximos 7 dias
                'labels'   => $forecastLabels,
                'values'   => $forecastValues,
                'observed' => $r['level_pct'],
                'unit'     => '%',
                'cota'     => array_map(fn (float $v): float => $this->cota($r, $v), $forecastValues),
            ],
            'bands' => [                                                             // legenda explicativa das faixas
                ['status' => 'normal',    'label' => 'Normal',  'range' => '0% – 80%',      'text' => 'Nível operacional seguro.'],
                ['status' => 'attention', 'label' => 'Atenção', 'range' => '80% – 90%',     'text' => 'Atenção para possíveis cheias.'],
                ['status' => 'critical',  'label' => 'Crítico', 'range' => 'Acima de 90%',  'text' => 'Risco alto de transbordamento.'],
            ],
            // cada leitura recebe a mesma classificação usada em todo o sistema
            'readings' => array_map(
                function (array $row): array {                                       // acrescenta o status calculado a cada linha da tabela
                    $row['status'] = StatusRules::describe(StatusRules::fromLevel((float) $row['level']));
                    return $row;
                },
                $this->repo->readings($reservoirId, 'level', 5)
            ),
        ];
    }

    /* ------------------------------------------------------------- 3. pH */

    /** Tela "Monitoramento de pH": acidez/alcalinidade da água e faixa ideal. */
    public function ph(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'ph', $period);
        $daily  = $this->repo->series($reservoirId, 'ph', '7d');
        $status = StatusRules::fromPh((float) $r['ph']);                             // normal se entre 6,5 e 8,5

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'ph'        => ['value' => $r['ph'], 'note' => 'Atualizado há 2 min'],
                'min'       => ['value' => $r['ph_min'], 'note' => 'Nas últimas 24h'],
                'max'       => ['value' => $r['ph_max'], 'note' => 'Nas últimas 24h'],
                'condition' => ['status' => StatusRules::describe($status), 'label' => $status === 'normal' ? 'Ideal' : 'Atenção', 'note' => 'Dentro da faixa ideal'], // a nota é fixa, mesmo quando o status é "atenção"
            ],
            'variation' => [                                                         // gráfico com faixa ideal sombreada
                'labels' => $series['labels'],
                'values' => $series['values'],
                'ideal_min' => StatusRules::PH_MIN,
                'ideal_max' => StatusRules::PH_MAX,
            ],
            'scale' => [                                                             // régua de pH de 0 a 14
                'value'   => $r['ph'],
                'min'     => 0,
                'max'     => 14,
                'neutral' => 7,                                                      // pH 7 = neutro
                'label'   => $status === 'normal' ? 'Ideal' : 'Atenção',
                'range'   => 'Faixa ideal: 6,5 – 8,5',
            ],
            'daily' => [
                'labels' => $daily['labels'],
                'values' => $daily['values'],
                'ideal_min' => StatusRules::PH_MIN,
                'ideal_max' => StatusRules::PH_MAX,
            ],
            'points'   => $this->repo->phPoints($reservoirId),                       // pontos de coleta espalhados pela represa
            'readings' => $this->repo->readings($reservoirId, 'ph', 5),
            'note'     => 'Faixa operacional configurada: 6,5 a 8,5',
        ];
    }

    /* -------------------------------------------- 4. volume armazenado */

    /** Tela "Volume armazenado": quanto de água existe e quanto cabe. */
    public function storage(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'storage', $period);
        $available = round((float) $r['capacity_hm3'] - (float) $r['volume_hm3'], 0); // espaço livre = capacidade - volume atual

        // balanço hídrico: entradas e saídas diárias (demonstrativo)
        $balanceLabels = [];
        $inflow = [];
        $outflow = [];
        $cursor = (clone Clock::now())->modify('-29 days');                          // começa 29 dias atrás para cobrir os últimos 30 dias
        for ($i = 0; $i < 30; $i += 3) {                                             // um ponto a cada 3 dias (10 barras)
            $balanceLabels[] = Clock::shortDate($cursor);
            $inflow[]  = round(60 + sin($i * 0.7) * 45, 0);                          // entradas ilustrativas (barras para cima)
            $outflow[] = round(-(35 + cos($i * 0.5) * 25), 0);                       // saídas negativas, para as barras ficarem abaixo do eixo
            $cursor = $cursor->modify('+3 days');
        }

        $first = $series['values'][0] ?? (float) $r['volume_hm3'];                   // volume no início do período; se a série vier vazia, usa o atual
        $gain  = round((float) $r['volume_hm3'] - $first, 0);                        // ganho/perda de volume ao longo do período

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'volume'    => ['value' => $r['volume_hm3'], 'unit' => 'hm³', 'note' => 'Em relação à capacidade total'],
                'capacity'  => ['value' => $r['capacity_hm3'], 'unit' => 'hm³', 'note' => 'Capacidade máxima do reservatório'],
                'occupancy' => ['value' => $r['level_pct'], 'unit' => '%', 'note' => 'Em relação à capacidade total'],
                'available' => ['value' => $available, 'unit' => 'hm³', 'note' => 'Disponível para utilização'],
            ],
            'evolution' => [                                                         // gráfico de evolução do volume com linha de capacidade
                'labels'   => $series['labels'],
                'values'   => $series['values'],
                'capacity' => $r['capacity_hm3'],
                'unit'     => 'hm³',
                'current'  => $r['volume_hm3'],
            ],
            'occupancy' => [                                                         // gráfico de rosca ocupado x disponível
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
            'distribution' => [                                                      // como a capacidade se divide (cada item em hm³ e em %)
                ['label' => 'Volume útil', 'note' => 'Para abastecimento e usos', 'value' => $r['useful_volume_hm3'], 'pct' => round((float) $r['useful_volume_hm3'] / (float) $r['capacity_hm3'] * 100, 1), 'status' => 'normal'],
                ['label' => 'Reserva técnica', 'note' => 'Segurança operacional', 'value' => $r['technical_reserve_hm3'], 'pct' => round((float) $r['technical_reserve_hm3'] / (float) $r['capacity_hm3'] * 100, 1), 'status' => 'attention'],
                ['label' => 'Volume disponível', 'note' => 'Disponível para utilização', 'value' => $available, 'pct' => round($available / (float) $r['capacity_hm3'] * 100, 1), 'status' => 'info'],
            ],
            'history' => $this->repo->readings($reservoirId, 'storage', 5),
            'insight' => [
                'value' => $gain,
                'unit'  => 'hm³',
                'pct'   => $first > 0 ? round($gain / $first * 100, 1) : 0,                // variação percentual; protege contra divisão por zero
                'text'  => 'Diferença entre o primeiro e o último dia do período selecionado.',
            ],
        ];
    }

    /* -------------------------------------------------- 5. precipitação */

    /** Tela "Precipitação": chuva medida, acumulada e previsão. */
    public function precipitation(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $series = $this->repo->series($reservoirId, 'precipitation', $period);        // chuva de cada intervalo do período (mm)

        // acumulado progressivo
        $accum = [];
        $sum = 0.0;
        foreach ($series['values'] as $v) {                                          // percorre a série somando: cada ponto = total de chuva até aquele momento
            $sum += $v;
            $accum[] = round($sum, 1);
        }

        $rain = (float) $r['rain_24h_mm'];                                           // chuva das últimas 24 horas
        $intensity = $rain >= 20 ? 'Alta' : ($rain >= 10 ? 'Moderada' : 'Baixa');    // classificação: 20 mm ou mais = alta; 10 a 20 = moderada; abaixo de 10 = baixa
        $intensityStatus = $rain >= 20 ? 'attention' : ($rain >= 10 ? 'attention' : 'normal'); // alta e moderada resultam no mesmo status "attention"

        // previsão de 5 dias (demonstrativa)
        $forecast = [];
        $icons = ['cloud-rain', 'cloud-rain', 'cloud-sun', 'cloud-sun', 'cloud-rain'];  // ícone de cada um dos 5 dias
        $mm = [16, 20, 8, 5, 12];                                                    // milímetros previstos por dia (valores fixos)
        $cursor = Clock::now();
        $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];                   // índice 0 = domingo, igual ao format('w') do PHP
        for ($i = 0; $i < 5; $i++) {                                                 // monta os cards dos próximos 5 dias
            $cursor = $cursor->modify('+1 day');
            $forecast[] = [
                'day'   => ucfirst($dias[(int) $cursor->format('w')]),               // "Qui", "Sex"...
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
            'chart' => [                                                             // barras (diária) + linha (acumulada)
                'labels'      => $series['labels'],
                'daily'       => $series['values'],
                'accumulated' => $accum,
                'unit'        => 'mm',
                'total'       => $r['rain_7d_mm'],
            ],
            'current' => [
                'value'    => $r['rain_24h_mm'],
                'label'    => 'Chuva ' . mb_strtolower($intensity, 'UTF-8'),         // "Chuva moderada"
                'humidity' => $r['humidity_pct'],                                    // umidade relativa do ar (%)
                'last'     => $r['last_reading_time'],
                'status'   => $intensityStatus,
            ],
            'basin' => array_map(static function (array $s): array {                 // chuva por estação da bacia, classificada em baixa/média/alta
                $mm = (float) $s['rain_24h'];
                return [
                    'name'   => $s['name'],
                    'mm'     => $mm,
                    'level'  => $mm >= 20 ? 'high' : ($mm >= 10 ? 'medium' : 'low'), // mesmos limites (10 e 20 mm) da intensidade acima
                ];
            }, $this->repo->rainStations($reservoirId)),
            'forecast' => $forecast,
            'stations' => $this->repo->rainStations($reservoirId),                   // tabela completa das estações pluviométricas
            'warning'  => [                                                          // aviso de chuva intensa
                'active' => $rain >= 15,                                             // só aparece com 15 mm ou mais nas últimas 24 h
                'title'  => 'Possibilidade de chuva intensa nas próximas 48 horas',
                'text'   => 'Acompanhe as atualizações e mantenha atenção às condições climáticas.',
            ],
        ];
    }

    /* --------------------------------------------- 7. situação operacional */

    /** Tela "Situação operacional": sensores, comportas, alertas, eventos e manutenções. */
    public function operation(string $reservoirId): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $sensors = $this->repo->sensors($reservoirId);
        $offline = count(array_filter($sensors, static fn (array $s): bool => $s['status'] !== 'online')); // quantos sensores estão fora do ar
        $status  = $offline > 0 ? 'attention' : 'normal';                            // basta um sensor offline para a situação geral ficar em atenção

        $alerts = array_filter(                                                      // só alertas ainda não resolvidos
            $this->repo->alerts($reservoirId),
            static fn (array $a): bool => $a['status'] !== 'resolved'
        );

        // O rótulo do KPI só usa uma severidade quando todos os alertas ativos
        // compartilham a mesma; com severidades misturadas ele vira "ativos" e
        // a nota descreve a composição.
        $bySeverity = [];
        foreach ($alerts as $a) {                                                    // conta quantos alertas ativos há de cada gravidade
            $bySeverity[$a['severity']] = ($bySeverity[$a['severity']] ?? 0) + 1;
        }

        $alertLabel = count($bySeverity) === 1                                       // todos da mesma gravidade?
            ? mb_strtolower(StatusRules::severityLabel((string) array_key_first($bySeverity))) // sim: "2 atenção"
            : 'ativos';                                                              // não (ou nenhum): "3 ativos"

        $partes = [];
        foreach ($bySeverity as $severidade => $quantidade) {                        // monta "1 crítico", "2 atenção"...
            $partes[] = $quantidade . ' ' . mb_strtolower(StatusRules::severityLabel((string) $severidade));
        }
        $alertNote = $partes === []
            ? 'Nenhuma ocorrência em aberto.'
            : implode(' · ', $partes) . '. Requer atenção da equipe.';              // "1 crítico · 2 atenção. Requer atenção da equipe."

        $generalNote = $offline > 0
            ? 'Há sensores fora de operação. Verifique a telemetria.'
            : 'Todos os sistemas operando dentro da normalidade.';

        return [
            'reservoir' => $this->head($r),
            'kpis' => [
                'general'  => ['status' => StatusRules::describe($status), 'note' => $generalNote],
                'sensors'  => ['online' => $r['sensors_online'], 'total' => $r['sensors_total'], 'note' => round((float) $r['sensors_online'] / max(1, (int) $r['sensors_total']) * 100) . '% dos sensores em operação.'], // max(1, ...) evita divisão por zero
                'gates'    => ['online' => $r['gates_online'], 'total' => $r['gates_total'], 'note' => 'Todas as comportas operando normalmente.'], // nota fixa
                'alerts'   => ['count' => count($alerts), 'label' => $alertLabel, 'note' => $alertNote],
            ],
            'systems' => [                                                           // grade de subsistemas; só sensores e comportas vêm dos dados, o resto é fixo
                ['id' => 'sensors',  'label' => 'Sensores',  'value' => $r['sensors_online'] . '/' . $r['sensors_total'], 'status' => $offline > 0 ? 'attention' : 'normal', 'icon' => 'signal'],
                ['id' => 'gates',    'label' => 'Comportas', 'value' => $r['gates_online'] . '/' . $r['gates_total'], 'status' => 'normal', 'icon' => 'gate'],
                ['id' => 'weather',  'label' => 'Estação meteorológica', 'value' => '1/1', 'status' => 'normal', 'icon' => 'cloud-rain'],
                ['id' => 'power',    'label' => 'Energia',   'value' => '2/2', 'status' => 'normal', 'icon' => 'zap'],
                ['id' => 'comm',     'label' => 'Comunicação', 'value' => '2/2', 'status' => 'normal', 'icon' => 'radio'],
                ['id' => 'maint',    'label' => 'Manutenção', 'value' => '1 pendência', 'status' => 'attention', 'icon' => 'wrench'],
            ],
            'availability' => [                                                      // percentuais de disponibilidade
                'general' => $r['availability_pct'],
                'status'  => StatusRules::describe('normal'),
                'items'   => [
                    ['label' => 'Telemetria',  'pct' => $r['telemetry_pct'],    'note' => $r['sensors_online'] . ' de ' . $r['sensors_total'] . ' online', 'icon' => 'radio'],
                    ['label' => 'Comunicação', 'pct' => $r['communication_pct'], 'note' => '2 de 2 links ativos', 'icon' => 'signal'],
                    ['label' => 'Energia',     'pct' => $r['power_pct'],         'note' => '2 de 2 fontes ativas', 'icon' => 'zap'],
                ],
            ],
            'components' => [                                                        // tabela de componentes monitorados (lista fixa demonstrativa)
                ['name' => 'Nível do reservatório', 'status' => 'normal', 'at' => '22/05/2024 09:28'],
                ['name' => 'Vazão (afluente/defluente)', 'status' => 'normal', 'at' => '22/05/2024 09:29'],
                ['name' => 'pH da água', 'status' => 'normal', 'at' => '22/05/2024 09:27'],
                ['name' => 'Pluviômetros', 'status' => 'normal', 'at' => '22/05/2024 09:25'],
                ['name' => 'Comportas', 'status' => 'normal', 'at' => '22/05/2024 09:28'],
                ['name' => 'Energia', 'status' => 'normal', 'at' => '22/05/2024 09:29'],
            ],
            'events'       => array_map(static function (array $e): array {          // eventos operacionais formatados para a tabela
                $when = new DateTimeImmutable($e['at'], Clock::timezone());
                return [
                    'at'        => Clock::dateTime($when),
                    'component' => $e['component'],
                    'event'     => $e['event'],
                    'priority'  => $e['priority'],
                    'priority_label' => StatusRules::severityLabel($e['priority']),   // a prioridade usa os mesmos rótulos da severidade
                    'status'    => $e['status'],
                    'status_label' => StatusRules::alertStatusLabel($e['status']),
                ];
            }, $this->repo->operationEvents($reservoirId)),
            'maintenances' => array_map(static function (array $m): array {          // manutenções programadas formatadas para a tabela
                $when = new DateTimeImmutable($m['date'], Clock::timezone());
                return [
                    'date'      => $when->format('d/m/Y'),
                    'equipment' => $m['equipment'],
                    'type'      => $m['type'],
                    'priority'  => $m['priority'],
                    'priority_label' => $m['priority'] === 'attention' ? 'Atenção' : 'Baixa', // só dois níveis de prioridade para manutenção
                ];
            }, $this->repo->maintenances($reservoirId)),
        ];
    }

    /* ------------------------------------------- 8. comparativo de vazão */

    /**
     * Tela "Comparativo de vazão": período atual x período anterior.
     *
     * @param string $current  rótulo do período atual (ex.: "16 – 22 mai"); só aparece nos textos
     * @param string $previous rótulo do período de comparação; só aparece nos textos
     */
    public function flowComparison(string $reservoirId, string $current, string $previous): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $cur = $this->repo->series($reservoirId, 'flow', '7d');                      // vazão dos últimos 7 dias (sempre 7d, independente dos períodos escolhidos)
        $prevValues = array_map(                                                     // período anterior demonstrativo: atual - 6,3 com uma variação em seno
            static fn (float $v, int $i): float => round($v - 6.3 + sin($i * 1.3) * 1.4, 1),
            $cur['values'],
            array_keys($cur['values'])                                               // array_map com dois arrays passa valor e índice juntos
        );

        $avgCur  = round(array_sum($cur['values']) / max(1, count($cur['values'])), 1); // média do período atual
        $avgPrev = round(array_sum($prevValues) / max(1, count($prevValues)), 1);    // média do período anterior
        $delta   = round($avgCur - $avgPrev, 1);                                     // diferença absoluta entre as médias (m³/s)
        $pct     = $avgPrev > 0 ? round($delta / $avgPrev * 100, 1) : 0.0;           // diferença em %

        $diffs = [];                                                                 // diferença dia a dia (gráfico de barras)
        $rows  = [];                                                                 // linhas da tabela diária
        $maxDiff = 0.0;                                                              // maior diferença encontrada (em valor absoluto)
        $maxDay  = '';
        $bestDay = '';                                                               // dia de maior vazão
        $bestVal = -INF;                                                             // começa em -infinito para qualquer valor ser maior
        $worstDay = '';                                                              // dia de menor vazão
        $worstVal = INF;                                                             // começa em +infinito para qualquer valor ser menor
        $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        $cursor = (clone Clock::now())->modify('-6 days');                           // data do primeiro ponto da série de 7 dias

        foreach ($cur['values'] as $i => $v) {                                       // percorre os 7 dias comparando atual x anterior
            $p = $prevValues[$i];
            $d = round($v - $p, 1);
            $diffs[] = $d;

            if (abs($d) > abs($maxDiff)) {                                           // guarda o dia com a maior diferença, para cima ou para baixo
                $maxDiff = $d;
                $maxDay  = $cur['labels'][$i];
            }
            if ($v > $bestVal) { $bestVal = $v; $bestDay = $cur['labels'][$i] . ' (' . $dias[(int) $cursor->format('w')] . ')'; }    // novo máximo
            if ($v < $worstVal) { $worstVal = $v; $worstDay = $cur['labels'][$i] . ' (' . $dias[(int) $cursor->format('w')] . ')'; } // novo mínimo

            $rows[] = [
                'day'       => $cur['labels'][$i] . ' (' . $dias[(int) $cursor->format('w')] . ')', // "16 Mai (qui)"
                'current'   => $v,
                'previous'  => $p,
                'diff'      => $d,
                'pct'       => $p > 0 ? round($d / $p * 100, 1) : 0.0,
                'status'    => $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'),           // seta para cima, para baixo ou estável
            ];
            $cursor = $cursor->modify('+1 day');                                     // avança a data junto com o índice da série
        }

        return [
            'reservoir' => $this->head($r),
            'periods'   => ['current' => $current, 'previous' => $previous],
            'kpis' => [
                'current'  => ['value' => $avgCur, 'unit' => 'm³/s', 'note' => 'Período ' . $current],
                'previous' => ['value' => $avgPrev, 'unit' => 'm³/s', 'note' => 'Período ' . $previous],
                'variation'=> ['value' => $pct, 'unit' => '%', 'note' => ($delta >= 0 ? '+' : '') . number_format($delta, 1, ',', '.') . ' m³/s', 'positive' => $delta >= 0], // "+" explícito quando positivo
                'max_diff' => ['value' => $maxDiff, 'unit' => 'm³/s', 'note' => 'Em ' . $maxDay, 'positive' => $maxDiff >= 0],
            ],
            'chart' => [                                                             // linhas atual x anterior
                'labels'   => $cur['labels'],
                'current'  => $cur['values'],
                'previous' => $prevValues,
                'unit'     => 'm³/s',
            ],
            'diff_chart' => [                                                        // barras de diferença diária
                'labels' => $cur['labels'],
                'values' => $diffs,
                'unit'   => 'm³/s',
            ],
            'in_out' => [                                                            // entradas x saídas nos mesmos 7 dias
                'labels'  => $cur['labels'],
                'inflow'  => $this->repo->series($reservoirId, 'inflow', '7d')['values'],
                'outflow' => $this->repo->series($reservoirId, 'outflow', '7d')['values'],
                'unit'    => 'm³/s',
            ],
            'summary' => [                                                           // cards de resumo
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
