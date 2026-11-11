<?php
/**
 * Aquapulse — dados das telas de Monitoramento.
 *
 * Cada método monta o conteúdo de uma tela a partir do repositório. Toda
 * análise detalhada é de UMA represa: nada aqui consolida represas.
 *
 *   flow()           api/v1/monitoring/flow.php            monitoramento/vazao.php
 *   level()          api/v1/monitoring/level.php           monitoramento/nivel.php e niveis.php
 *   ph()             api/v1/monitoring/ph.php              monitoramento/ph.php
 *   storage()        api/v1/monitoring/storage.php         monitoramento/volume.php
 *   precipitation()  api/v1/monitoring/precipitation.php   monitoramento/precipitacao.php
 *   operation()      api/v1/monitoring/operation.php       monitoramento/operacional.php
 *   flowComparison() api/v1/monitoring/flow-comparison.php monitoramento/comparativo.php
 *
 * Todos devolvem [] quando a represa não existe (o endpoint responde "sem dados").
 * Os números vêm das leituras gravadas; os limites e as faixas vêm de StatusRules.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;
use DateTimeImmutable;

final class MonitoringService
{
    /** Semanas oferecidas no comparativo de vazão (0 = os últimos 7 dias). */
    public const COMPARISON_WEEKS = 4;

    private MonitoringRepositoryInterface $repo;

    public function __construct(MonitoringRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /* ------------------------------------------------------------ apoio */

    /**
     * Cabeçalho comum das telas: represa, situação da telemetria e última leitura.
     *
     * @param array<int,array<string,mixed>> $sensors equipamentos da represa
     */
    private function head(array $r, array $sensors): array
    {
        // Só se espera transmissão de quem está ativo no cadastro.
        $esperados = count(array_filter($sensors, static fn (array $s): bool => in_array($s['status'], ['online', 'offline'], true)));
        $online = count(array_filter($sensors, static fn (array $s): bool => $s['status'] === 'online'));

        if ($esperados > 0 && $online === $esperados) {
            $telemetria = 'online';
            $rotulo = 'Equipamentos online';
        } elseif ($online === 0) {
            $telemetria = 'offline';
            $rotulo = 'Equipamentos sem comunicação';
        } else {
            $telemetria = 'partial';
            $rotulo = $online . ' de ' . $esperados . ' equipamentos online';
        }

        return [
            'id'              => $r['id'],
            'name'            => $r['name'],
            'code'            => $r['code'],
            'telemetry'       => $telemetria,
            'telemetry_label' => $rotulo,
            'last_reading'    => $this->ultimaLeitura($r),
        ];
    }

    /** "Última leitura há 12 s" — ou o aviso de que ainda não há leituras. */
    private function ultimaLeitura(array $r): string
    {
        return $r['last_reading_at'] === null
            ? 'Nenhuma leitura registrada'
            : 'Última leitura ' . Clock::relative(Clock::parse((string) $r['last_reading_at']));
    }

    /**
     * Reordena os valores de uma série para os rótulos de outra. Quando um
     * equipamento ficou sem transmitir, a posição correspondente vira null e o
     * gráfico mostra a falha em vez de deslocar os pontos.
     *
     * @param array{labels:array<int,string>,values:array<int,float>} $serie
     * @param array<int,string> $labels
     * @return array<int,?float>
     */
    private function alinhar(array $serie, array $labels): array
    {
        $porRotulo = array_combine($serie['labels'], $serie['values']) ?: [];

        return array_map(static fn (string $rotulo) => $porRotulo[$rotulo] ?? null, $labels);
    }

    /**
     * Equipamentos da represa que medem as grandezas informadas — lista exibida
     * nas telas de vazão, pH e precipitação.
     *
     * @param array<int,array<string,mixed>> $sensors
     * @param array<int,string>              $tipos
     */
    private function equipamentosDe(array $sensors, array $tipos): array
    {
        return array_values(array_filter($sensors, static fn (array $s): bool => in_array($s['type'], $tipos, true)));
    }

    /** Média dos valores não nulos; 0 quando não há nenhum. */
    private function media(array $valores): float
    {
        $validos = array_filter($valores, static fn ($v): bool => $v !== null);
        return $validos === [] ? 0.0 : array_sum($validos) / count($validos);
    }

    private function numero(float $valor, int $casas = 1): string
    {
        return number_format($valor, $casas, ',', '.');
    }

    /* ------------------------------------------------- 1. volume de vazão */

    /** Entradas (afluência) e saídas (defluência) de água. */
    public function flow(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $sensors = $this->repo->sensors($reservoirId);

        $entrada = $this->repo->series($reservoirId, 'inflow', $period);
        $saida = $this->alinhar($this->repo->series($reservoirId, 'outflow', $period), $entrada['labels']);

        $entradaDiaria = $this->repo->series($reservoirId, 'inflow', '7d');
        $saidaDiaria = $this->alinhar($this->repo->series($reservoirId, 'outflow', '7d'), $entradaDiaria['labels']);

        // Médias das últimas 24 h, calculadas sobre a série horária.
        $entrada24 = $this->repo->series($reservoirId, 'inflow', '24h');
        $saida24 = $this->repo->series($reservoirId, 'outflow', '24h');
        $mediaEntrada = round($this->media($entrada24['values']), 1);
        $mediaSaida = round($this->media($saida24['values']), 1);
        $balanco = round($mediaEntrada - $mediaSaida, 1);

        if ($balanco > 0.05) {
            $texto = 'Entra mais água do que sai: o reservatório tende a subir.';
            $selo = 'Acumulando';
        } elseif ($balanco < -0.05) {
            $texto = 'Sai mais água do que entra: o reservatório tende a baixar.';
            $selo = 'Liberando';
        } else {
            $texto = 'Entrada e saída equilibradas.';
            $selo = 'Estável';
        }

        // O medidor posiciona a vazão atual entre a menor e a maior do período.
        $minimo = $entrada['values'] === [] ? 0.0 : min($entrada['values']);
        $maximo = $entrada['values'] === [] ? 0.0 : max($entrada['values']);

        return [
            'reservoir' => $this->head($r, $sensors),
            'kpis' => [
                'flow'    => ['value' => $r['inflow_m3s'], 'unit' => 'm³/s', 'note' => $this->ultimaLeitura($r)],
                'inflow'  => ['value' => $mediaEntrada, 'unit' => 'm³/s', 'note' => 'Média das últimas 24 h'],
                'outflow' => ['value' => $mediaSaida, 'unit' => 'm³/s', 'note' => 'Média das últimas 24 h'],
                'balance' => ['value' => $balanco, 'unit' => 'm³/s', 'note' => 'Afluência menos defluência (24 h)', 'positive' => $balanco >= 0],
            ],
            'realtime' => [
                'labels'  => $entrada['labels'],
                'inflow'  => $entrada['values'],
                'outflow' => $saida,
                'unit'    => 'm³/s',
            ],
            'condition' => [
                'label' => $selo,
                'text'  => $texto,
                'range' => 'No período: ' . $this->numero($minimo) . ' a ' . $this->numero($maximo) . ' m³/s',
                'min'   => $minimo,
                'max'   => $maximo,
                'value' => $r['inflow_m3s'],
            ],
            'daily' => [
                'labels'  => $entradaDiaria['labels'],
                'inflow'  => $entradaDiaria['values'],
                'outflow' => $saidaDiaria,
                'unit'    => 'm³/s',
            ],
            'sensors'  => $this->equipamentosDe($sensors, ['inflow', 'outflow']),
            'readings' => $this->repo->readings($reservoirId, 'inflow', 5),
        ];
    }

    /* --------------------------------------------- 2. nível do reservatório */

    /** Percentual de ocupação, cota em metros e faixas de risco. */
    public function level(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $serie = $this->repo->series($reservoirId, 'level', $period);
        $status = StatusRules::fromLevel((float) $r['level_pct']);

        $atencao = StatusRules::levelAttention();
        $critico = StatusRules::levelCritical();

        // As cotas das linhas de limite saem da mesma conta que converte o nível
        // medido em cota, então linha e leitura nunca se contradizem.
        $cotaAtencao = ReservoirCurve::cota($r, $atencao);
        $cotaCritica = ReservoirCurve::cota($r, $critico);

        $faixas = [
            ['status' => 'critical',  'label' => 'Crítico', 'range' => 'A partir de ' . StatusRules::pct($critico),
             'text' => 'Risco alto de transbordamento.', 'from' => $critico, 'to' => 100],
            ['status' => 'attention', 'label' => 'Atenção', 'range' => StatusRules::pct($atencao) . ' a ' . StatusRules::pct($critico),
             'text' => 'Acompanhar a evolução do nível.', 'from' => $atencao, 'to' => $critico],
            ['status' => 'normal',    'label' => 'Normal',  'range' => 'Abaixo de ' . StatusRules::pct($atencao),
             'text' => 'Nível operacional seguro.', 'from' => 0, 'to' => $atencao],
        ];

        return [
            'reservoir' => $this->head($r, $this->repo->sensors($reservoirId)),
            'kpis' => [
                'level'     => ['value' => $r['level_pct'], 'unit' => '%', 'note' => $this->ultimaLeitura($r), 'status' => StatusRules::describe($status)],
                'cota'      => ['value' => $r['cota_m'], 'unit' => 'm', 'note' => 'Metros acima do nível do mar'],
                'variation' => ['value' => $r['cota_variation_m'], 'unit' => 'm', 'note' => 'Variação nas últimas 24 h', 'positive' => (float) $r['cota_variation_m'] >= 0],
                'available' => ['value' => round(100 - (float) $r['level_pct'], 1), 'unit' => '%', 'note' => 'Espaço livre no reservatório'],
            ],
            'history' => [
                'labels'          => $serie['labels'],
                'values'          => $serie['values'],
                'unit'            => '%',
                'current'         => $r['level_pct'],
                'attention'       => $atencao,
                'critical'        => $critico,
                'attention_label' => 'Atenção: ' . StatusRules::pct($atencao) . ' (cota ' . $this->numero($cotaAtencao) . ' m)',
                'critical_label'  => 'Crítico: ' . StatusRules::pct($critico) . ' (cota ' . $this->numero($cotaCritica) . ' m)',

                // a mesma série em metros, para a tela Níveis
                'cota'           => array_map(static fn (float $v): float => ReservoirCurve::cota($r, $v), $serie['values']),
                'cota_current'   => (float) $r['cota_m'],
                'cota_attention' => $cotaAtencao,
                'cota_critical'  => $cotaCritica,
                'cota_spill'     => (float) $r['cota_spill_m'],
                'cota_alert'     => (float) $r['cota_alert_m'],
            ],
            'capacity' => [
                'level'  => $r['level_pct'],
                'status' => StatusRules::describe($status),
                'bands'  => $faixas,
            ],
            'bands' => array_reverse($faixas),
            'monthly' => $this->cotaMensal($r),
            'readings' => array_map(
                static function (array $linha): array {
                    $linha['status'] = StatusRules::describe(StatusRules::fromLevel((float) $linha['level']));
                    return $linha;
                },
                $this->repo->readings($reservoirId, 'level', 5)
            ),
        ];
    }

    /**
     * Cota máxima, média e mínima de cada um dos últimos seis meses, calculadas
     * sobre as médias diárias do nível (tela Níveis).
     *
     * @return array{labels:array<int,string>,max:array<int,float>,avg:array<int,float>,min:array<int,float>}
     */
    private function cotaMensal(array $r): array
    {
        $hoje = Clock::now();
        $serie = $this->repo->dailySeries($r['id'], 'level', $hoje->modify('first day of -5 months'), $hoje);

        $porMes = [];
        foreach ($serie['labels'] as $i => $rotulo) {
            $porMes[substr($rotulo, 3)][] = ReservoirCurve::cota($r, $serie['values'][$i]);   // "10 Nov" -> "Nov"
        }

        return [
            'labels' => array_keys($porMes),
            'max'    => array_values(array_map(static fn (array $v): float => max($v), $porMes)),
            'avg'    => array_values(array_map(static fn (array $v): float => round(array_sum($v) / count($v), 1), $porMes)),
            'min'    => array_values(array_map(static fn (array $v): float => min($v), $porMes)),
        ];
    }

    /* ------------------------------------------------------------- 3. pH */

    /** Acidez/alcalinidade da água e faixa ideal. */
    public function ph(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $sensors = $this->repo->sensors($reservoirId);
        $serie = $this->repo->series($reservoirId, 'ph', $period);
        $diaria = $this->repo->series($reservoirId, 'ph', '7d');

        $status = StatusRules::fromPh((float) $r['ph']);
        $minimo = StatusRules::phMin();
        $maximo = StatusRules::phMax();
        $faixa = $this->numero($minimo) . ' – ' . $this->numero($maximo);
        $rotulo = $status === 'normal' ? 'Ideal' : 'Atenção';

        return [
            'reservoir' => $this->head($r, $sensors),
            'kpis' => [
                'ph'        => ['value' => $r['ph'], 'note' => $this->ultimaLeitura($r)],
                'min'       => ['value' => $r['ph_min'], 'note' => 'Nas últimas 24 h'],
                'max'       => ['value' => $r['ph_max'], 'note' => 'Nas últimas 24 h'],
                'condition' => [
                    'status' => StatusRules::describe($status),
                    'label'  => $rotulo,
                    'note'   => $status === 'normal' ? 'Dentro da faixa ideal' : 'Fora da faixa ideal (' . $faixa . ')',
                ],
            ],
            'variation' => [
                'labels'    => $serie['labels'],
                'values'    => $serie['values'],
                'ideal_min' => $minimo,
                'ideal_max' => $maximo,
                'ideal_label' => "Faixa ideal\n" . $faixa,
            ],
            'scale' => [
                'value'  => $r['ph'],
                'min'    => 0,
                'max'    => 14,
                'label'  => $rotulo,
                'status' => $status,
                'range'  => 'Faixa ideal: ' . $faixa,
            ],
            'daily' => [
                'labels'    => $diaria['labels'],
                'values'    => $diaria['values'],
                'ideal_min' => $minimo,
                'ideal_max' => $maximo,
            ],
            'sensors'  => $this->equipamentosDe($sensors, ['ph', 'water_temp']),
            'readings' => array_map(static function (array $linha): array {
                $linha['status'] = StatusRules::describe($linha['status']);
                return $linha;
            }, $this->repo->readings($reservoirId, 'ph', 5)),
        ];
    }

    /* -------------------------------------------- 4. volume armazenado */

    /** Quanto de água existe e quanto cabe. */
    public function storage(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $serie = $this->repo->series($reservoirId, 'storage', $period);
        $capacidade = (float) $r['capacity_hm3'];
        $volume = (float) $r['volume_hm3'];
        $livre = round($capacidade - $volume, 0);

        // Balanço hídrico: a vazão média do dia (m³/s) vira volume diário (hm³).
        // 1 m³/s durante um dia = 86.400 m³ = 0,0864 hm³.
        $entrada = $this->repo->series($reservoirId, 'inflow', '30d');
        $saida = $this->alinhar($this->repo->series($reservoirId, 'outflow', '30d'), $entrada['labels']);

        $inicio = $serie['values'][0] ?? $volume;             // volume no começo do período
        $ganho = round($volume - $inicio, 0);

        return [
            'reservoir' => $this->head($r, $this->repo->sensors($reservoirId)),
            'kpis' => [
                'volume'    => ['value' => $r['volume_hm3'], 'unit' => 'hm³', 'note' => $this->ultimaLeitura($r)],
                'capacity'  => ['value' => $r['capacity_hm3'], 'unit' => 'hm³', 'note' => 'Capacidade máxima do reservatório'],
                'occupancy' => ['value' => $r['level_pct'], 'unit' => '%', 'note' => 'Em relação à capacidade total'],
                'available' => ['value' => $livre, 'unit' => 'hm³', 'note' => 'Espaço livre no reservatório'],
            ],
            'evolution' => [
                'labels'   => $serie['labels'],
                'values'   => $serie['values'],
                'capacity' => $r['capacity_hm3'],
                'unit'     => 'hm³',
                'current'  => $r['volume_hm3'],
            ],
            'occupancy' => [
                'pct'           => $r['level_pct'],
                'stored'        => $r['volume_hm3'],
                'available'     => $livre,
                'available_pct' => round(100 - (float) $r['level_pct'], 1),
                'capacity'      => $r['capacity_hm3'],
            ],
            'balance' => [
                'labels'  => $entrada['labels'],
                'inflow'  => array_map(static fn (float $v): float => round($v * 0.0864, 2), $entrada['values']),
                'outflow' => array_map(static fn ($v) => $v === null ? null : round(-$v * 0.0864, 2), $saida),   // negativo: barra abaixo do eixo
                'unit'    => 'hm³/dia',
            ],
            'distribution' => [
                ['label' => 'Volume útil', 'note' => 'Para abastecimento e usos', 'value' => $r['useful_volume_hm3'],
                 'pct' => round((float) $r['useful_volume_hm3'] / $capacidade * 100, 1), 'status' => 'normal'],
                ['label' => 'Reserva técnica', 'note' => 'Segurança operacional', 'value' => $r['technical_reserve_hm3'],
                 'pct' => round((float) $r['technical_reserve_hm3'] / $capacidade * 100, 1), 'status' => 'attention'],
                ['label' => 'Espaço livre', 'note' => 'Capacidade ainda não ocupada', 'value' => $livre,
                 'pct' => round($livre / $capacidade * 100, 1), 'status' => 'info'],
            ],
            'history' => array_map(static function (array $linha): array {
                $linha['status'] = StatusRules::describe($linha['status']);
                return $linha;
            }, $this->repo->readings($reservoirId, 'storage', 5)),
            'insight' => [
                'value' => $ganho,
                'unit'  => 'hm³',
                'pct'   => $inicio > 0 ? round($ganho / $inicio * 100, 1) : 0,
                'text'  => 'Diferença entre o primeiro dia do período selecionado e o volume atual.',
            ],
        ];
    }

    /* -------------------------------------------------- 5. precipitação */

    /** Chuva medida e acumulada. */
    public function precipitation(string $reservoirId, string $period): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $serie = $this->repo->series($reservoirId, 'precipitation', $period);

        $acumulado = [];
        $soma = 0.0;
        foreach ($serie['values'] as $v) {
            $soma += $v;
            $acumulado[] = round($soma, 1);
        }

        $chuva = (float) $r['rain_24h_mm'];
        $atencao = StatusRules::rainAttention();
        $status = StatusRules::classify('precipitation', $chuva);

        // A intensidade é descrita pelos mesmos limites que abrem os alertas.
        if ($status === 'critical') {
            $intensidade = 'Crítica';
        } elseif ($status === 'attention') {
            $intensidade = 'Alta';
        } else {
            $intensidade = $chuva >= $atencao / 2 ? 'Moderada' : 'Baixa';
        }

        $sensors = $this->repo->sensors($reservoirId);

        return [
            'reservoir' => $this->head($r, $sensors),
            'kpis' => [
                'rain_24h'   => ['value' => $r['rain_24h_mm'], 'unit' => 'mm', 'note' => $this->ultimaLeitura($r)],
                'rain_7d'    => ['value' => $r['rain_7d_mm'], 'unit' => 'mm', 'note' => 'Total nos últimos 7 dias'],
                'rain_month' => ['value' => $r['rain_month_mm'], 'unit' => 'mm', 'note' => 'Total desde o dia 1º'],
                'intensity'  => ['label' => $intensidade, 'note' => 'Pelo acumulado de 24 h', 'status' => StatusRules::describe($status)],
            ],
            'chart' => [
                'labels'      => $serie['labels'],
                'daily'       => $serie['values'],
                'accumulated' => $acumulado,
                'unit'        => 'mm',
            ],
            'current' => [
                'value'  => $r['rain_24h_mm'],
                'label'  => 'Chuva ' . mb_strtolower($intensidade, 'UTF-8'),
                'last'   => $this->ultimaLeitura($r),
                'status' => $status,
            ],
            'sensors'  => $this->equipamentosDe($sensors, ['precipitation']),
            'warning'  => [
                'active' => $status !== 'normal',
                'title'  => $status === 'critical' ? 'Chuva acumulada em nível crítico' : 'Chuva acumulada em atenção',
                'text'   => 'Foram registrados ' . $this->numero($chuva) . ' mm nas últimas 24 h. Limite de atenção: '
                          . StatusRules::number($atencao) . ' mm; crítico: ' . StatusRules::number(StatusRules::rainCritical()) . ' mm.',
            ],
        ];
    }

    /* --------------------------------------------- 6. situação operacional */

    /** Equipamentos, alertas em aberto e manutenções. */
    public function operation(string $reservoirId): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $sensors = $this->repo->sensors($reservoirId);
        $esperados = array_filter($sensors, static fn (array $s): bool => in_array($s['status'], ['online', 'offline'], true));
        $online = count(array_filter($sensors, static fn (array $s): bool => $s['status'] === 'online'));
        $semComunicacao = count($esperados) - $online;

        $alertas = array_filter(
            $this->repo->alerts($reservoirId),
            static fn (array $a): bool => $a['status'] !== 'resolved'
        );

        $porGravidade = [];
        foreach ($alertas as $a) {
            $porGravidade[$a['severity']] = ($porGravidade[$a['severity']] ?? 0) + 1;
        }

        $partes = [];
        foreach ($porGravidade as $gravidade => $quantidade) {
            $partes[] = $quantidade . ' ' . mb_strtolower(StatusRules::severityLabel((string) $gravidade), 'UTF-8');
        }

        // Situação geral: alerta crítico pesa mais que equipamento fora do ar.
        if (isset($porGravidade['critical'])) {
            $geral = 'critical';
            $notaGeral = 'Há alerta crítico em aberto.';
        } elseif ($semComunicacao > 0 || $alertas !== []) {
            $geral = 'attention';
            $notaGeral = $semComunicacao > 0
                ? $semComunicacao . ' equipamento(s) sem comunicação.'
                : 'Há alerta em aberto.';
        } else {
            $geral = 'normal';
            $notaGeral = 'Equipamentos transmitindo e nenhum alerta em aberto.';
        }

        // Equipamentos agrupados pela grandeza que medem.
        $grupos = [];
        foreach ($sensors as $s) {
            $g = &$grupos[$s['type']];
            $g = $g ?? ['label' => $s['type_label'], 'total' => 0, 'online' => 0];
            if (in_array($s['status'], ['online', 'offline'], true)) {
                $g['total']++;
                $g['online'] += $s['status'] === 'online' ? 1 : 0;
            }
            unset($g);
        }

        $percentual = count($esperados) > 0 ? round($online / count($esperados) * 100, 1) : 0.0;

        return [
            'reservoir' => $this->head($r, $sensors),
            'kpis' => [
                'general' => ['status' => StatusRules::describe($geral), 'note' => $notaGeral],
                'sensors' => [
                    'online' => $online,
                    'total'  => count($esperados),
                    'note'   => count($sensors) - count($esperados) > 0
                        ? (count($sensors) - count($esperados)) . ' fora de operação (manutenção ou desativado).'
                        : 'Todos os equipamentos cadastrados estão em operação.',
                ],
                'last'   => ['value' => $r['last_reading_at'] === null ? '—' : Clock::relative(Clock::parse((string) $r['last_reading_at'])),
                             'note' => $r['last_reading_at'] === null ? 'Nenhuma leitura registrada' : Clock::parse((string) $r['last_reading_at'])->format('d/m/Y H:i:s')],
                'alerts' => [
                    'count' => count($alertas),
                    'label' => count($alertas) === 1 ? 'em aberto' : 'em aberto',
                    'note'  => $partes === [] ? 'Nenhuma ocorrência em aberto.' : implode(' · ', $partes) . '.',
                    'status' => isset($porGravidade['critical']) ? 'critical' : ($alertas === [] ? 'normal' : 'attention'),
                ],
            ],
            'systems' => array_values(array_map(static function (array $g): array {
                return [
                    'label'  => $g['label'],
                    'value'  => $g['total'] === 0 ? 'Fora de operação' : $g['online'] . ' de ' . $g['total'] . ' online',
                    'status' => $g['total'] > 0 && $g['online'] === $g['total'] ? 'normal' : 'attention',
                ];
            }, $grupos)),
            'availability' => [
                'general' => $percentual,
                'items'   => array_values(array_map(static function (array $g): array {
                    return [
                        'label' => $g['label'],
                        'pct'   => $g['total'] > 0 ? round($g['online'] / $g['total'] * 100) : 0,
                        'note'  => $g['online'] . ' de ' . $g['total'] . ' online',
                    ];
                }, array_filter($grupos, static fn (array $g): bool => $g['total'] > 0))),
            ],
            'components' => array_map(static function (array $s): array {
                return [
                    'id'           => $s['id'],
                    'name'         => $s['id'] . ' — ' . $s['name'],
                    'type_label'   => $s['type_label'],
                    'status'       => $s['status'],
                    'status_label' => $s['status_label'],
                    'at'           => $s['last_reading_at'] === null ? '—' : Clock::parse($s['last_reading_at'])->format('d/m/Y H:i:s'),
                    'ago'          => $s['last_reading_label'],
                ];
            }, $sensors),
            'maintenances' => array_map(static function (array $m): array {
                $rotulos = ['critical' => 'Crítica', 'attention' => 'Atenção', 'info' => 'Informação', 'low' => 'Baixa'];
                return [
                    'date'           => Clock::parse($m['date'])->format('d/m/Y'),
                    'equipment'      => $m['equipment'],
                    'type'           => $m['type'],
                    'priority'       => $m['priority'],
                    'priority_label' => $rotulos[$m['priority']] ?? $m['priority'],
                    'description'    => $m['description'],
                ];
            }, $this->repo->maintenances($reservoirId)),
        ];
    }

    /* ------------------------------------------- 7. comparativo de vazão */

    /**
     * Semanas disponíveis para comparação, da mais recente para a mais antiga.
     * A semana 0 são os últimos 7 dias; a semana 1, os 7 dias anteriores; etc.
     *
     * @return array<int,array{value:int,label:string,from:DateTimeImmutable,to:DateTimeImmutable}>
     */
    public static function weeks(): array
    {
        $hoje = Clock::now()->setTime(0, 0);
        $semanas = [];

        for ($i = 0; $i < self::COMPARISON_WEEKS; $i++) {
            $fim = $hoje->modify('-' . ($i * 7) . ' days');
            $inicio = $fim->modify('-6 days');
            $semanas[] = [
                'value' => $i,
                'label' => $inicio->format('d/m') . ' – ' . $fim->format('d/m'),
                'from'  => $inicio,
                'to'    => $fim,
            ];
        }
        return $semanas;
    }

    /**
     * Afluência média diária de duas semanas, lado a lado.
     *
     * @param int $current  índice da semana analisada (ver weeks())
     * @param int $previous índice da semana de comparação
     */
    public function flowComparison(string $reservoirId, int $current, int $previous): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $semanas = self::weeks();
        $atual = $semanas[$current];
        $anterior = $semanas[$previous];

        $valoresAtuais = $this->semana($reservoirId, 'inflow', $atual['from']);
        $valoresAnteriores = $this->semana($reservoirId, 'inflow', $anterior['from']);
        $saidas = $this->semana($reservoirId, 'outflow', $atual['from']);

        $mediaAtual = round($this->media($valoresAtuais), 1);
        $mediaAnterior = round($this->media($valoresAnteriores), 1);
        $delta = round($mediaAtual - $mediaAnterior, 1);
        $pct = $mediaAnterior > 0 ? round($delta / $mediaAnterior * 100, 1) : 0.0;

        $diasDaSemana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        $labels = [];
        $diferencas = [];
        $linhas = [];
        $maiorDiferenca = null;
        $melhor = null;
        $pior = null;

        for ($i = 0; $i < 7; $i++) {
            $dia = $atual['from']->modify("+{$i} days");
            $rotulo = Clock::shortDate($dia);
            $rotuloCompleto = $rotulo . ' (' . $diasDaSemana[(int) $dia->format('w')] . ')';
            $labels[] = $rotulo;

            $v = $valoresAtuais[$i];
            $p = $valoresAnteriores[$i];
            $d = ($v === null || $p === null) ? null : round($v - $p, 1);
            $diferencas[] = $d;

            if ($d !== null && ($maiorDiferenca === null || abs($d) > abs($maiorDiferenca['value']))) {
                $maiorDiferenca = ['value' => $d, 'day' => $rotulo];
            }
            if ($v !== null && ($melhor === null || $v > $melhor['value'])) {
                $melhor = ['value' => $v, 'day' => $rotuloCompleto];
            }
            if ($v !== null && ($pior === null || $v < $pior['value'])) {
                $pior = ['value' => $v, 'day' => $rotuloCompleto];
            }

            $linhas[] = [
                'day'      => $rotuloCompleto,
                'current'  => $v,
                'previous' => $p,
                'diff'     => $d,
                'pct'      => ($d === null || $p <= 0) ? null : round($d / $p * 100, 1),
                'status'   => $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat')),
            ];
        }

        return [
            'reservoir' => $this->head($r, $this->repo->sensors($reservoirId)),
            'periods'   => [
                'current'  => $atual['label'],
                'previous' => $anterior['label'],
                'options'  => array_map(static fn (array $s): array => ['value' => $s['value'], 'label' => $s['label']], $semanas),
            ],
            'kpis' => [
                'current'   => ['value' => $mediaAtual, 'unit' => 'm³/s', 'note' => 'Semana de ' . $atual['label']],
                'previous'  => ['value' => $mediaAnterior, 'unit' => 'm³/s', 'note' => 'Semana de ' . $anterior['label']],
                'variation' => ['value' => $pct, 'unit' => '%', 'note' => ($delta >= 0 ? '+' : '') . $this->numero($delta) . ' m³/s', 'positive' => $delta >= 0],
                'max_diff'  => ['value' => $maiorDiferenca['value'] ?? 0, 'unit' => 'm³/s',
                                'note' => $maiorDiferenca === null ? 'Sem dados para comparar' : 'Em ' . $maiorDiferenca['day'],
                                'positive' => ($maiorDiferenca['value'] ?? 0) >= 0],
            ],
            'chart' => [
                'labels'   => $labels,
                'current'  => $valoresAtuais,
                'previous' => $valoresAnteriores,
                'unit'     => 'm³/s',
            ],
            'diff_chart' => ['labels' => $labels, 'values' => $diferencas, 'unit' => 'm³/s'],
            'in_out' => [
                'labels'  => $labels,
                'inflow'  => $valoresAtuais,
                'outflow' => $saidas,
                'unit'    => 'm³/s',
            ],
            'summary' => [
                'best'    => ['label' => 'Maior vazão', 'day' => $melhor['day'] ?? '—', 'value' => $melhor['value'] ?? null, 'unit' => 'm³/s'],
                'worst'   => ['label' => 'Menor vazão', 'day' => $pior['day'] ?? '—', 'value' => $pior['value'] ?? null, 'unit' => 'm³/s'],
                'average' => ['label' => 'Média semanal', 'current' => $mediaAtual, 'previous' => $mediaAnterior, 'unit' => 'm³/s'],
                'trend'   => ['label' => 'Tendência', 'value' => $delta >= 0 ? 'Alta' : 'Baixa',
                              'note' => 'Vazão ' . ($delta >= 0 ? 'acima' : 'abaixo') . ' da semana de comparação'],
            ],
            'rows' => $linhas,
            'insight' => [
                'delta' => $delta,
                'text'  => 'A afluência média ' . ($delta >= 0 ? 'aumentou' : 'diminuiu') . ' ' . $this->numero(abs($delta))
                         . ' m³/s em relação à semana de ' . $anterior['label'] . '.',
            ],
        ];
    }

    /**
     * Média diária de uma grandeza nos 7 dias a partir de $from. Dia sem
     * leitura vira null, mantendo cada valor na posição do seu dia.
     *
     * @return array<int,?float>
     */
    private function semana(string $reservoirId, string $metric, DateTimeImmutable $from): array
    {
        $serie = $this->repo->dailySeries($reservoirId, $metric, $from, $from->modify('+6 days'));

        $rotulos = [];
        for ($i = 0; $i < 7; $i++) {
            $rotulos[] = Clock::shortDate($from->modify("+{$i} days"));
        }

        return $this->alinhar($serie, $rotulos);
    }
}
