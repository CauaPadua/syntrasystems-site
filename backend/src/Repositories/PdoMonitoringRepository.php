<?php
/**
 * Aquapulse — leitura dos dados de monitoramento no MySQL (PDO).
 *
 * COMO O ESTADO ATUAL É MONTADO
 * A tabela reservoirs guarda só o cadastro. Os valores de agora (nível, vazão,
 * pH...) são a última leitura de cada grandeza em readings, entregues pela view
 * vw_resumo_represas. Acumulados e comparações (chuva de 24 h, mínimo e máximo
 * de pH, variação da cota) são calculados em SQL sobre o histórico.
 *
 * COMO OS GRÁFICOS SÃO MONTADOS
 * readings guarda leituras individuais, com o instante em que foram feitas. Os
 * gráficos agrupam essas leituras com GROUP BY: por hora (24 h), por dia
 * (7, 30 e 90 dias) ou por mês (12 meses). A chuva é somada; as demais
 * grandezas são médias.
 *
 * PERMISSÃO
 * O repositório recebe as empresas que o usuário pode consultar e aplica esse
 * filtro em todas as consultas — nenhuma tela precisa lembrar de filtrar.
 *
 * SEGURANÇA
 * Todo valor vindo da requisição entra por parâmetro em consulta preparada,
 * nunca concatenado no SQL. Os trechos montados como texto só acrescentam
 * nomes de coluna e expressões fixas escritas nesta classe.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Services\EquipmentRules;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\Clock;
use DateTimeImmutable;
use PDO;

final class PdoMonitoringRepository implements MonitoringRepositoryInterface
{
    private PDO $pdo;

    /** @var array<int,string>|null empresas permitidas; null = todas */
    private ?array $empresas;

    /** @var array<string,array<string,array{min:?float,max:?float}>>|null */
    private ?array $limites = null;

    /**
     * @param array<int,string>|null $empresas IDs das empresas que o usuário pode ver; null = todas
     */
    public function __construct(PDO $pdo, ?array $empresas = null)
    {
        $this->pdo = $pdo;
        $this->empresas = $empresas;
    }

    /* ====================================================== apoio interno */

    /**
     * Converte número vindo do banco (que chega como texto) para int ou float.
     *
     * @param mixed $valor
     * @return int|float
     */
    private function numero($valor)
    {
        $f = (float) $valor;
        return floor($f) === $f ? (int) $f : round($f, 3);
    }

    /**
     * Marcadores nomeados para um IN (...): o PDO não aceita um array em um
     * único parâmetro, então cada item ganha o seu (:r0, :r1, ...).
     *
     * @param array<int,string> $itens
     * @return array{sql:string,valores:array<string,string>}
     */
    private function marcadores(array $itens, string $prefixo): array
    {
        $nomes = [];
        $valores = [];

        foreach (array_values($itens) as $i => $item) {
            $nomes[] = ':' . $prefixo . $i;
            $valores[$prefixo . $i] = $item;
        }

        return ['sql' => implode(', ', $nomes), 'valores' => $valores];
    }

    /**
     * Condição que limita uma coluna de empresa às empresas do usuário.
     *
     * @return array{sql:string,valores:array<string,string>}
     */
    private function escopo(string $coluna): array
    {
        if ($this->empresas === null) {
            return ['sql' => '', 'valores' => []];            // administrador: sem restrição
        }
        if ($this->empresas === []) {
            return ['sql' => ' AND 1 = 0', 'valores' => []];  // usuário sem nenhum vínculo: não vê nada
        }

        $m = $this->marcadores($this->empresas, 'emp');
        return ['sql' => " AND {$coluna} IN ({$m['sql']})", 'valores' => $m['valores']];
    }

    /* ========================================================== empresas */

    public function companies(): array
    {
        $e = $this->escopo('id');

        $stmt = $this->pdo->prepare(
            "SELECT id, code, name, manager, status, status_label
               FROM companies
              WHERE 1 = 1{$e['sql']}
              ORDER BY sort_order, name"
        );
        $stmt->execute($e['valores']);

        return $stmt->fetchAll();
    }

    /* ========================================================== represas */

    public function reservoirs(string $companyId = 'all'): array
    {
        return $this->montarRepresas($companyId === 'all' ? null : $companyId);
    }

    public function reservoir(string $reservoirId): ?array
    {
        return $this->montarRepresas(null, $reservoirId)[0] ?? null;
    }

    /**
     * Represas com cadastro, valores atuais e valores calculados.
     *
     * @return array<int,array<string,mixed>>
     */
    private function montarRepresas(?string $companyId = null, ?string $reservoirId = null): array
    {
        /* --------------- 1. cadastro + valores atuais (view vw_resumo_represas) */
        $e = $this->escopo('r.company_id');
        $sql = "SELECT r.id, r.code, r.name, r.company_id, r.city, r.basin, r.lat, r.lng, r.coordinates_label,
                       r.capacity_hm3, r.useful_volume_hm3, r.technical_reserve_hm3, r.daily_consumption_hm3,
                       r.cota_spill_m, r.cota_alert_m, r.cota_min_m, r.cota_max_m,
                       v.level_pct, v.cota_m, v.volume_hm3, v.inflow_m3s, v.outflow_m3s, v.ph, v.water_temp_c,
                       v.last_reading_at, v.open_alerts
                  FROM reservoirs r
                  JOIN vw_resumo_represas v ON v.reservoir_id = r.id
                 WHERE 1 = 1{$e['sql']}";
        $params = $e['valores'];

        if ($companyId !== null) {
            $sql .= ' AND r.company_id = :cid';
            $params['cid'] = $companyId;
        }
        if ($reservoirId !== null) {
            $sql .= ' AND r.id = :rid';
            $params['rid'] = $reservoirId;
        }

        // A primeira represa da lista é a que o painel abre por padrão.
        $sql .= ' ORDER BY r.sort_order, r.code';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();

        if ($linhas === []) {
            return [];
        }

        $ids = array_column($linhas, 'id');
        $agora = Clock::now();
        $ontem = $agora->modify('-24 hours');

        /* -------------------------------- 2. contas sobre o histórico */
        $chuva = $this->chuvaAcumulada($ids, $agora);
        $faixaPh = $this->faixaDePh($ids, $agora);
        $antes = $this->valoresEm($ids, ['cota', 'inflow'], $ontem);
        $equipamentos = $this->contagemDeEquipamentos($ids, $agora);

        /* ------------------------------------------------ 3. montagem */
        $represas = [];
        foreach ($linhas as $r) {
            $id = (string) $r['id'];
            $cota = (float) $r['cota_m'];

            $represas[] = [
                'id'                => $id,
                'code'              => (string) $r['code'],
                'name'              => (string) $r['name'],
                'company_id'        => (string) $r['company_id'],
                'city'              => (string) $r['city'],
                'basin'             => (string) $r['basin'],
                'lat'               => (float) $r['lat'],
                'lng'               => (float) $r['lng'],
                'coordinates_label' => (string) $r['coordinates_label'],

                'capacity_hm3'          => $this->numero($r['capacity_hm3']),
                'useful_volume_hm3'     => $this->numero($r['useful_volume_hm3']),
                'technical_reserve_hm3' => $this->numero($r['technical_reserve_hm3']),
                'daily_consumption_hm3' => $this->numero($r['daily_consumption_hm3']),
                'cota_spill_m'          => $this->numero($r['cota_spill_m']),
                'cota_alert_m'          => $this->numero($r['cota_alert_m']),
                'cota_min_m'            => $this->numero($r['cota_min_m']),
                'cota_max_m'            => $this->numero($r['cota_max_m']),

                'level_pct'    => $this->numero($r['level_pct']),
                'cota_m'       => $this->numero($r['cota_m']),
                'volume_hm3'   => $this->numero($r['volume_hm3']),
                'inflow_m3s'   => $this->numero($r['inflow_m3s']),
                'outflow_m3s'  => $this->numero($r['outflow_m3s']),
                'ph'           => $this->numero($r['ph']),
                'water_temp_c' => $this->numero($r['water_temp_c']),

                'ph_min'             => $this->numero($faixaPh[$id]['min'] ?? $r['ph']),
                'ph_max'             => $this->numero($faixaPh[$id]['max'] ?? $r['ph']),
                'rain_24h_mm'        => $this->numero($chuva[$id]['h24'] ?? 0),
                'rain_prev_24h_mm'   => $this->numero($chuva[$id]['h24_anterior'] ?? 0),
                'rain_7d_mm'         => $this->numero($chuva[$id]['d7'] ?? 0),
                'rain_month_mm'      => $this->numero($chuva[$id]['mes'] ?? 0),
                'cota_variation_m'   => round($cota - ($antes[$id]['cota'] ?? $cota), 1),
                'inflow_24h_ago_m3s' => $this->numero($antes[$id]['inflow'] ?? $r['inflow_m3s']),

                'sensors_online'  => (int) ($equipamentos[$id]['online'] ?? 0),
                'sensors_total'   => (int) ($equipamentos[$id]['total'] ?? 0),
                'open_alerts'     => (int) $r['open_alerts'],
                'last_reading_at' => $r['last_reading_at'] === null ? null : Clock::iso(Clock::parse((string) $r['last_reading_at'])),
            ];
        }

        return $represas;
    }

    /**
     * Chuva acumulada por represa: últimas 24 h, as 24 h anteriores, 7 dias e mês.
     * Cada leitura de chuva é o quanto choveu desde a leitura anterior, então o
     * acumulado é a soma (SUM) das leituras do intervalo.
     *
     * @param array<int,string> $ids
     * @return array<string,array{h24:float,h24_anterior:float,d7:float,mes:float}>
     */
    private function chuvaAcumulada(array $ids, DateTimeImmutable $agora): array
    {
        $ontem = $agora->modify('-24 hours');
        $anteontem = $agora->modify('-48 hours');
        $seteDias = $agora->modify('-6 days')->setTime(0, 0);
        $inicioMes = $agora->modify('first day of this month')->setTime(0, 0);
        $inicio = min($anteontem, $seteDias, $inicioMes);    // leitura mais antiga que interessa a alguma das somas

        $m = $this->marcadores($ids, 'r');
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id,
                    SUM(CASE WHEN recorded_at >  :ontem THEN value ELSE 0 END)                               AS h24,
                    SUM(CASE WHEN recorded_at >  :anteontem AND recorded_at <= :ontem2 THEN value ELSE 0 END) AS h24_anterior,
                    SUM(CASE WHEN recorded_at >= :sete THEN value ELSE 0 END)                                AS d7,
                    SUM(CASE WHEN recorded_at >= :mes  THEN value ELSE 0 END)                                AS mes
               FROM readings
              WHERE metric = 'precipitation'
                AND reservoir_id IN ({$m['sql']})
                AND recorded_at >= :inicio AND recorded_at <= :agora
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + [
            'ontem'     => Clock::sql($ontem),
            'ontem2'    => Clock::sql($ontem),
            'anteontem' => Clock::sql($anteontem),
            'sete'      => Clock::sql($seteDias),
            'mes'       => Clock::sql($inicioMes),
            'inicio'    => Clock::sql($inicio),
            'agora'     => Clock::sql($agora),
        ]);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['reservoir_id']] = [
                'h24'          => round((float) $l['h24'], 1),
                'h24_anterior' => round((float) $l['h24_anterior'], 1),
                'd7'           => round((float) $l['d7'], 1),
                'mes'          => round((float) $l['mes'], 1),
            ];
        }
        return $mapa;
    }

    /**
     * Menor e maior pH das últimas 24 h.
     *
     * @param array<int,string> $ids
     * @return array<string,array{min:float,max:float}>
     */
    private function faixaDePh(array $ids, DateTimeImmutable $agora): array
    {
        $m = $this->marcadores($ids, 'r');
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id, MIN(value) AS minimo, MAX(value) AS maximo
               FROM readings
              WHERE metric = 'ph'
                AND reservoir_id IN ({$m['sql']})
                AND recorded_at > :ontem AND recorded_at <= :agora
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + [
            'ontem' => Clock::sql($agora->modify('-24 hours')),
            'agora' => Clock::sql($agora),
        ]);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['reservoir_id']] = ['min' => round((float) $l['minimo'], 2), 'max' => round((float) $l['maximo'], 2)];
        }
        return $mapa;
    }

    /**
     * Valor que cada grandeza tinha em um instante do passado: a leitura mais
     * recente feita até aquele momento.
     *
     * A subconsulta acha o instante dessa leitura para cada par (represa,
     * grandeza) e o JOIN traz o valor — o jeito usual de buscar "o registro
     * mais recente de cada grupo".
     *
     * @param array<int,string> $ids
     * @param array<int,string> $metricas
     * @return array<string,array<string,float>> [represa][grandeza] => valor
     */
    private function valoresEm(array $ids, array $metricas, DateTimeImmutable $quando): array
    {
        $r = $this->marcadores($ids, 'r');
        $g = $this->marcadores($metricas, 'g');

        $stmt = $this->pdo->prepare(
            "SELECT l.reservoir_id, l.metric, l.value
               FROM readings l
               JOIN (SELECT reservoir_id, metric, MAX(recorded_at) AS ultimo
                       FROM readings
                      WHERE reservoir_id IN ({$r['sql']})
                        AND metric IN ({$g['sql']})
                        AND recorded_at <= :quando
                      GROUP BY reservoir_id, metric) u
                 ON u.reservoir_id = l.reservoir_id
                AND u.metric = l.metric
                AND u.ultimo = l.recorded_at"
        );
        $stmt->execute($r['valores'] + $g['valores'] + ['quando' => Clock::sql($quando)]);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['reservoir_id']][$l['metric']] = (float) $l['value'];
        }
        return $mapa;
    }

    /**
     * Quantos equipamentos cada represa tem e quantos estão transmitindo
     * (ativos e com leitura dentro da janela de EquipmentRules::ONLINE_WINDOW).
     *
     * @param array<int,string> $ids
     * @return array<string,array{total:int,online:int}>
     */
    private function contagemDeEquipamentos(array $ids, DateTimeImmutable $agora): array
    {
        $m = $this->marcadores($ids, 'r');
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' AND last_reading_at >= :limite THEN 1 ELSE 0 END) AS online
               FROM vw_ultimas_medicoes
              WHERE reservoir_id IN ({$m['sql']})
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + [
            'limite' => Clock::sql($agora->modify('-' . EquipmentRules::ONLINE_WINDOW . ' seconds')),
        ]);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['reservoir_id']] = ['total' => (int) $l['total'], 'online' => (int) $l['online']];
        }
        return $mapa;
    }

    /* ============================================================ séries */

    public function series(string $reservoirId, string $metric, string $period): array
    {
        $agora = Clock::now();

        switch ($period) {
            case '24h':
                $inicio = $agora->modify('-23 hours');
                $inicio = $inicio->setTime((int) $inicio->format('H'), 0);
                return $this->serieAgrupada($reservoirId, $metric, $inicio, $agora, 'hora');

            case '12m':
                $inicio = $agora->modify('first day of -11 months')->setTime(0, 0);
                return $this->serieAgrupada($reservoirId, $metric, $inicio, $agora, 'mes');

            default:
                $dias = ['7d' => 6, '30d' => 29, '90d' => 89][$period] ?? 6;   // 6 dias atrás + hoje = 7 pontos
                return $this->serieAgrupada($reservoirId, $metric, $agora->modify("-{$dias} days")->setTime(0, 0), $agora, 'dia');
        }
    }

    public function dailySeries(string $reservoirId, string $metric, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->serieAgrupada($reservoirId, $metric, $from->setTime(0, 0), $to->setTime(23, 59, 59), 'dia');
    }

    /**
     * Agrupa as leituras de um intervalo por hora, dia ou mês.
     *
     * DATE_FORMAT transforma o instante da leitura no "rótulo" do grupo (ex.:
     * todas as leituras de 10/11 viram '2026-11-10') e o GROUP BY junta as que
     * têm o mesmo rótulo.
     *
     * @param string $agrupamento 'hora' | 'dia' | 'mes'
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    private function serieAgrupada(string $reservoirId, string $metric, DateTimeImmutable $inicio, DateTimeImmutable $fim, string $agrupamento): array
    {
        // Só expressões fixas entram no texto do SQL — nunca um valor da requisição.
        $formato = ['hora' => '%Y-%m-%d %H:00:00', 'dia' => '%Y-%m-%d', 'mes' => '%Y-%m-01'][$agrupamento];
        $agregacao = $metric === 'precipitation' ? 'SUM(value)' : 'AVG(value)';   // somar nível ou pH não teria sentido

        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(recorded_at, '{$formato}') AS grupo, {$agregacao} AS valor
               FROM readings
              WHERE reservoir_id = :rid AND metric = :metric
                AND recorded_at BETWEEN :inicio AND :fim
              GROUP BY grupo
              ORDER BY grupo"
        );
        $stmt->execute([
            'rid'    => $reservoirId,
            'metric' => $metric,
            'inicio' => Clock::sql($inicio),
            'fim'    => Clock::sql($fim),
        ]);

        $casas = EquipmentRules::TYPES[$metric]['decimals'] ?? 1;
        $labels = [];
        $values = [];

        foreach ($stmt->fetchAll() as $linha) {
            $quando = Clock::parse((string) $linha['grupo']);

            if ($agrupamento === 'hora') {
                $labels[] = $quando->format('H:i');
            } elseif ($agrupamento === 'dia') {
                $labels[] = Clock::shortDate($quando);
            } else {
                $labels[] = Clock::shortMonth($quando);
            }
            $values[] = round((float) $linha['valor'], $casas);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /* ========================================================== leituras */

    public function readings(string $reservoirId, string $metric, int $limit = 5): array
    {
        switch ($metric) {
            case 'inflow':
                return $this->leiturasDeVazao($reservoirId, $limit);
            case 'level':
                return $this->leiturasDeNivel($reservoirId, $limit);
            case 'ph':
                return $this->leiturasDePh($reservoirId, $limit);
            case 'storage':
                return $this->leiturasDeVolume($reservoirId, $limit);
            default:
                return [];
        }
    }

    /**
     * Afluência e defluência lado a lado, minuto a minuto.
     *
     * Os dois equipamentos transmitem em instantes diferentes. O agrupamento
     * por minuto junta as leituras de cada um, e o CASE dentro do AVG separa as
     * duas grandezas em colunas.
     */
    private function leiturasDeVazao(string $reservoirId, int $limit): array
    {
        $agora = Clock::now();
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i:00') AS instante,
                    AVG(CASE WHEN metric = 'inflow'  THEN value END) AS entrada,
                    AVG(CASE WHEN metric = 'outflow' THEN value END) AS saida
               FROM readings
              WHERE reservoir_id = :rid AND metric IN ('inflow', 'outflow')
                AND recorded_at BETWEEN :inicio AND :agora
              GROUP BY instante
              ORDER BY instante DESC
              LIMIT :lim"
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('inicio', Clock::sql($agora->modify('-7 days')));
        $stmt->bindValue('agora', Clock::sql($agora));
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $linhas = [];
        foreach ($stmt->fetchAll() as $l) {
            $quando = Clock::parse((string) $l['instante']);
            $entrada = $l['entrada'] === null ? null : round((float) $l['entrada'], 1);
            $saida = $l['saida'] === null ? null : round((float) $l['saida'], 1);

            $linhas[] = [
                'at'      => Clock::iso($quando),
                'time'    => Clock::dateTime($quando),
                'inflow'  => $entrada,
                'outflow' => $saida,
                // só há balanço quando os dois equipamentos transmitiram naquele minuto
                'balance' => ($entrada === null || $saida === null) ? null : round($entrada - $saida, 1),
            ];
        }
        return $linhas;
    }

    /**
     * Cota e nível, com a variação em relação a 24 h antes.
     *
     * A subconsulta do SELECT roda para cada linha e busca a última cota
     * registrada até 24 h antes daquele instante.
     */
    private function leiturasDeNivel(string $reservoirId, int $limit): array
    {
        $agora = Clock::now();
        $stmt = $this->pdo->prepare(
            "SELECT g.instante, g.cota, g.nivel,
                    (SELECT a.value
                       FROM readings a
                      WHERE a.reservoir_id = :rid2 AND a.metric = 'cota'
                        AND a.recorded_at <= DATE_SUB(g.instante, INTERVAL 24 HOUR)
                      ORDER BY a.recorded_at DESC
                      LIMIT 1) AS cota_ontem
               FROM (SELECT DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i:00') AS instante,
                            AVG(CASE WHEN metric = 'cota'  THEN value END) AS cota,
                            AVG(CASE WHEN metric = 'level' THEN value END) AS nivel
                       FROM readings
                      WHERE reservoir_id = :rid AND metric IN ('cota', 'level')
                        AND recorded_at BETWEEN :inicio AND :agora
                      GROUP BY instante
                      ORDER BY instante DESC
                      LIMIT :lim) g
              ORDER BY g.instante DESC"
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('rid2', $reservoirId);
        $stmt->bindValue('inicio', Clock::sql($agora->modify('-7 days')));
        $stmt->bindValue('agora', Clock::sql($agora));
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $linhas = [];
        foreach ($stmt->fetchAll() as $l) {
            $quando = Clock::parse((string) $l['instante']);
            $cota = (float) $l['cota'];

            $linhas[] = [
                'at'        => Clock::iso($quando),
                'time'      => Clock::dateTime($quando),
                'cota'      => round($cota, 1),
                'level'     => round((float) $l['nivel'], 1),
                'variation' => $l['cota_ontem'] === null ? 0.0 : round($cota - (float) $l['cota_ontem'], 1),
            ];
        }
        return $linhas;
    }

    /**
     * Últimas leituras de pH, com o equipamento que enviou (LEFT JOIN: leituras
     * antigas podem não ter equipamento) e a temperatura da água mais próxima.
     */
    private function leiturasDePh(string $reservoirId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.recorded_at, l.value AS ph, s.name AS equipamento,
                    (SELECT t.value
                       FROM readings t
                      WHERE t.reservoir_id = l.reservoir_id AND t.metric = 'water_temp'
                        AND t.recorded_at <= l.recorded_at
                      ORDER BY t.recorded_at DESC
                      LIMIT 1) AS temperatura
               FROM readings l
               LEFT JOIN sensors s ON s.id = l.sensor_id
              WHERE l.reservoir_id = :rid AND l.metric = 'ph' AND l.recorded_at <= :agora
              ORDER BY l.recorded_at DESC
              LIMIT :lim"
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('agora', Clock::sql());
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $linhas = [];
        foreach ($stmt->fetchAll() as $l) {
            $quando = Clock::parse((string) $l['recorded_at']);
            $ph = round((float) $l['ph'], 2);

            $linhas[] = [
                'at'     => Clock::iso($quando),
                'time'   => $quando->format('d/m/Y H:i:s'),
                'ph'     => $ph,
                'temp'   => $l['temperatura'] === null ? null : round((float) $l['temperatura'], 1),
                'point'  => (string) ($l['equipamento'] ?? '—'),
                'status' => StatusRules::fromPh($ph),
            ];
        }
        return $linhas;
    }

    /** Volume e ocupação médios de cada dia, com a variação de um dia para o outro. */
    private function leiturasDeVolume(string $reservoirId, int $limit): array
    {
        $agora = Clock::now();
        $stmt = $this->pdo->prepare(
            "SELECT DATE(recorded_at) AS dia,
                    AVG(CASE WHEN metric = 'storage' THEN value END) AS volume,
                    AVG(CASE WHEN metric = 'level'   THEN value END) AS ocupacao
               FROM readings
              WHERE reservoir_id = :rid AND metric IN ('storage', 'level')
                AND recorded_at BETWEEN :inicio AND :agora
              GROUP BY dia
              ORDER BY dia DESC
              LIMIT :lim"
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('inicio', Clock::sql($agora->modify('-60 days')));
        $stmt->bindValue('agora', Clock::sql($agora));
        $stmt->bindValue('lim', $limit + 1, PDO::PARAM_INT);   // um dia a mais, para calcular a variação da última linha
        $stmt->execute();
        $dias = $stmt->fetchAll();

        $linhas = [];
        foreach ($dias as $i => $l) {
            if ($i >= $limit) {
                break;
            }

            $quando = Clock::parse((string) $l['dia']);
            $volume = (float) $l['volume'];
            $anterior = isset($dias[$i + 1]) ? (float) $dias[$i + 1]['volume'] : $volume;
            $ocupacao = (float) $l['ocupacao'];

            $linhas[] = [
                'at'        => Clock::iso($quando),
                'date'      => Clock::date($quando),
                'volume'    => round($volume, 0),
                'occupancy' => round($ocupacao, 1),
                'variation' => round($volume - $anterior, 0),
                'status'    => StatusRules::fromLevel($ocupacao),
            ];
        }
        return $linhas;
    }

    /* ====================================================== equipamentos */

    public function sensors(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sensor_id, sensor_name, metric, location, status, last_value, last_reading_at
               FROM vw_ultimas_medicoes
              WHERE reservoir_id = :rid
              ORDER BY metric, sensor_id'
        );
        $stmt->execute(['rid' => $reservoirId]);

        $agora = Clock::now();
        $lista = [];

        foreach ($stmt->fetchAll() as $l) {
            $ultima = $l['last_reading_at'] === null ? null : Clock::parse((string) $l['last_reading_at']);
            $comunicacao = EquipmentRules::communication((string) $l['status'], $ultima, $agora);

            $lista[] = [
                'id'                 => (string) $l['sensor_id'],
                'name'               => (string) $l['sensor_name'],
                'location'           => (string) $l['location'],
                'type'               => (string) $l['metric'],
                'type_label'         => EquipmentRules::label((string) $l['metric']),
                'unit'               => EquipmentRules::unit((string) $l['metric']),
                'status'             => $comunicacao,
                'status_label'       => EquipmentRules::communicationLabel($comunicacao),
                'last_value'         => $l['last_value'] === null ? null : (float) $l['last_value'],
                'last_reading_at'    => $ultima === null ? null : Clock::iso($ultima),
                'last_reading_label' => $ultima === null ? 'Nenhuma leitura' : Clock::relative($ultima),
            ];
        }
        return $lista;
    }

    /* ============================================================ alertas */

    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        $e = $this->escopo('r.company_id');
        $sql = "SELECT a.id, a.reservoir_id, a.sensor_id, a.severity, a.metric, a.status, a.title, a.metric_label,
                       a.detected_at, a.resolved_at, a.owner, a.current_value, a.threshold, a.detail, a.threshold_detail
                  FROM alerts a
                  JOIN reservoirs r ON r.id = a.reservoir_id
                 WHERE 1 = 1{$e['sql']}";
        $params = $e['valores'];

        if ($reservoirId !== 'all') {
            $sql .= ' AND a.reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($severity !== 'all') {
            $sql .= ' AND a.severity = :sev';
            $params['sev'] = $severity;
        }
        if ($status !== 'all') {
            $sql .= ' AND a.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY a.detected_at DESC, a.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();

        if ($linhas === []) {
            return [];
        }

        $etapas = $this->linhasDoTempo(array_column($linhas, 'id'));
        $alertas = [];

        foreach ($linhas as $l) {
            $alertas[] = [
                'id'               => (string) $l['id'],
                'reservoir_id'     => (string) $l['reservoir_id'],
                'sensor_id'        => $l['sensor_id'] === null ? null : (string) $l['sensor_id'],
                'severity'         => (string) $l['severity'],
                'metric_key'       => $l['metric'] === null ? null : (string) $l['metric'],
                'title'            => (string) $l['title'],
                'metric'           => (string) $l['metric_label'],
                'detected_at'      => Clock::iso(Clock::parse((string) $l['detected_at'])),
                'resolved_at'      => $l['resolved_at'] === null ? null : Clock::iso(Clock::parse((string) $l['resolved_at'])),
                'owner'            => (string) $l['owner'],
                'status'           => (string) $l['status'],
                'current_value'    => (string) $l['current_value'],
                'threshold'        => (string) $l['threshold'],
                'detail'           => (string) $l['detail'],
                'threshold_detail' => (string) $l['threshold_detail'],
                'timeline'         => $etapas[(string) $l['id']] ?? [],
            ];
        }
        return $alertas;
    }

    /**
     * Etapas de vários alertas em uma única consulta (em vez de uma por alerta).
     *
     * @param array<int,string> $ids
     * @return array<string,array<int,array{at:string,text:string}>>
     */
    private function linhasDoTempo(array $ids): array
    {
        $m = $this->marcadores($ids, 'a');
        $stmt = $this->pdo->prepare(
            "SELECT alert_id, happened_at, description
               FROM alert_timeline
              WHERE alert_id IN ({$m['sql']})
              ORDER BY alert_id, step_order"
        );
        $stmt->execute($m['valores']);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[(string) $l['alert_id']][] = [
                'at'   => Clock::iso(Clock::parse((string) $l['happened_at'])),
                'text' => (string) $l['description'],
            ];
        }
        return $mapa;
    }

    public function openAlertCount(): int
    {
        $e = $this->escopo('r.company_id');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM alerts a
               JOIN reservoirs r ON r.id = a.reservoir_id
              WHERE a.status <> 'resolved'{$e['sql']}"
        );
        $stmt->execute($e['valores']);

        return (int) $stmt->fetchColumn();
    }

    /* ========================================================= relatórios */

    public function reports(string $reservoirId = 'all', string $type = 'all'): array
    {
        $e = $this->escopo('r.company_id');
        $sql = "SELECT p.id, p.reservoir_id, p.name, p.type, p.period_label, p.period_start, p.period_end,
                       p.generated_at, p.owner, p.icon
                  FROM reports p
                  JOIN reservoirs r ON r.id = p.reservoir_id
                 WHERE 1 = 1{$e['sql']}";
        $params = $e['valores'];

        if ($reservoirId !== 'all') {
            $sql .= ' AND p.reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($type !== 'all') {
            $sql .= ' AND p.type = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY p.generated_at DESC, p.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $relatorios = [];
        foreach ($stmt->fetchAll() as $l) {
            $relatorios[] = [
                'id'           => (string) $l['id'],
                'name'         => (string) $l['name'],
                'type'         => (string) $l['type'],
                'reservoir_id' => (string) $l['reservoir_id'],
                'period'       => (string) $l['period_label'],
                'period_start' => $l['period_start'] === null ? null : (string) $l['period_start'],
                'period_end'   => $l['period_end'] === null ? null : (string) $l['period_end'],
                'generated_at' => Clock::iso(Clock::parse((string) $l['generated_at'])),
                'owner'        => (string) $l['owner'],
                'icon'         => (string) $l['icon'],
            ];
        }
        return $relatorios;
    }

    /* ======================================================== manutenções */

    public function maintenances(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, scheduled_date, equipment, type, priority, description
               FROM maintenances
              WHERE reservoir_id = :rid AND status = 'scheduled'
              ORDER BY scheduled_date ASC, id ASC"
        );
        $stmt->execute(['rid' => $reservoirId]);

        $lista = [];
        foreach ($stmt->fetchAll() as $l) {
            $lista[] = [
                'id'          => (int) $l['id'],
                'date'        => (string) $l['scheduled_date'],
                'equipment'   => (string) $l['equipment'],
                'type'        => (string) $l['type'],
                'priority'    => (string) $l['priority'],
                'description' => (string) ($l['description'] ?? ''),
            ];
        }
        return $lista;
    }

    /* ============================================================ limites */

    public function limits(): array
    {
        if ($this->limites !== null) {
            return $this->limites;                            // já lidos nesta requisição
        }

        $linhas = $this->pdo->query(
            'SELECT metric, classification, min_value, max_value FROM operational_limits'
        )->fetchAll();

        $mapa = [];
        foreach ($linhas as $l) {
            $mapa[$l['metric']][$l['classification']] = [
                'min' => $l['min_value'] === null ? null : (float) $l['min_value'],
                'max' => $l['max_value'] === null ? null : (float) $l['max_value'],
            ];
        }

        return $this->limites = $mapa;
    }
}
