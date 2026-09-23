<?php
/**
 * Aquapulse — repositório de monitoramento lendo o banco MySQL (PDO).
 *
 * Implementa MonitoringRepositoryInterface devolvendo EXATAMENTE as mesmas
 * estruturas que o MockMonitoringRepository devolvia. É essa igualdade que
 * permite trocar a origem dos dados sem mexer em services, endpoints, telas
 * ou JavaScript — o Support\Container escolhe uma implementação ou outra
 * conforme a variável de ambiente DB_HOST.
 *
 * COMO O ESTADO ATUAL É MONTADO
 * A tabela `reservoirs` guarda só o cadastro (capacidade, cotas, localização).
 * Os valores de agora (nível, vazão, pH, volume...) são a ÚLTIMA leitura de
 * cada métrica na tabela `readings`, e os acumulados (chuva de 24 h, 7 dias e
 * do mês, mínimo/máximo de pH, variação da cota) são calculados em SQL. Assim
 * cada medição existe em um lugar só.
 *
 * RELÓGIO
 * Todas as janelas de tempo usam Support\Clock::now() — o relógio da
 * aplicação, não NOW() do MySQL. No modo demonstrativo o "agora" é fixo
 * (22/05/2024 09:30), o mesmo instante em que os dados foram carregados.
 *
 * SEGURANÇA
 * Todo valor vindo da requisição entra por parâmetro nomeado (:rid, :metric,
 * :inicio...) em consulta preparada — nunca concatenado no SQL. Os poucos
 * trechos montados como texto (a lista de filtros opcionais) só acrescentam
 * nomes de coluna fixos, nunca conteúdo digitado pelo usuário.
 *
 * DESEMPENHO
 * As listas de represas resolvem tudo em poucas consultas agregadas (uma para
 * o cadastro, uma para os valores atuais, uma para os acumulados, uma para os
 * sensores), em vez de uma consulta por represa dentro de um laço.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;
use DateTimeImmutable;
use PDO;

final class PdoMonitoringRepository implements MonitoringRepositoryInterface
{
    /** Conexão compartilhada, criada por Support\Database. */
    private PDO $pdo;

    /** Limites vigentes lidos de operational_limits, em cache por requisição. */
    private ?array $limites = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ====================================================== apoio interno */

    /** Data/hora "agora" da aplicação, no formato aceito pelo MySQL. */
    private function agora(): DateTimeImmutable
    {
        return Clock::now();
    }

    /** Converte DateTimeImmutable para o texto que o MySQL entende em comparações. */
    private function sql(DateTimeImmutable $d): string
    {
        return $d->format('Y-m-d H:i:s');
    }

    /** Converte o texto de data do MySQL em objeto, no fuso da aplicação. */
    private function data(string $texto): DateTimeImmutable
    {
        return new DateTimeImmutable($texto, Clock::timezone());
    }

    /**
     * Converte número vindo do banco (que chega como texto) para int ou float.
     *
     * O MySQL devolve DECIMAL como string ("82.400"). Valores redondos viram
     * int e o restante vira float, reproduzindo os tipos que o arquivo
     * simulado usava — o JSON da API continua com a mesma aparência.
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
     * Limites operacionais vigentes (valid_to nulo), agrupados por métrica e
     * classificação. Substituem números fixos no código desta classe.
     *
     * @return array<string,array<string,array{min:?float,max:?float}>>
     */
    private function limites(): array
    {
        if ($this->limites !== null) {                                   // já lidos nesta requisição
            return $this->limites;
        }

        $linhas = $this->pdo->query(
            "SELECT metric, classification, min_value, max_value
               FROM operational_limits
              WHERE valid_to IS NULL AND scope_type = 'global'"
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

    /**
     * Classifica um nível percentual usando os limites do banco.
     * Espelha StatusRules::level(), mas com os valores vindos da tabela.
     */
    private function statusDoNivel(float $pct): string
    {
        $limites = $this->limites();
        $critico = $limites['level']['critical']['min'] ?? 90.0;
        $atencao = $limites['level']['attention']['min'] ?? 80.0;

        if ($pct >= $critico) {
            return 'critical';
        }
        return $pct >= $atencao ? 'attention' : 'normal';
    }

    /** Classifica um pH pela faixa ideal cadastrada (fora da faixa = atenção). */
    private function statusDoPh(float $ph): string
    {
        $faixa = $this->limites()['ph']['normal'] ?? ['min' => 6.5, 'max' => 8.5];
        $min = $faixa['min'] ?? 6.5;
        $max = $faixa['max'] ?? 8.5;

        return ($ph >= $min && $ph <= $max) ? 'normal' : 'attention';
    }

    /* ========================================================== empresas */

    /**
     * Todas as empresas, na ordem de exibição cadastrada (sort_order).
     * Colunas explícitas (e não SELECT *) para o JSON não ganhar campos de
     * controle como created_at.
     */
    public function companies(): array
    {
        return $this->pdo->query(
            'SELECT id, code, name, manager, status, status_label
               FROM companies
              ORDER BY sort_order, name'
        )->fetchAll();
    }

    /* ========================================================== represas */

    /**
     * Monta a lista de represas com o estado atual completo.
     *
     * @param string|null $companyId   filtra por empresa quando informado
     * @param string|null $reservoirId filtra uma represa específica
     * @return array<int,array<string,mixed>>
     */
    private function montarRepresas(?string $companyId = null, ?string $reservoirId = null): array
    {
        /* ---------------------------------------- 1. cadastro + situação */
        $sql = 'SELECT r.*,
                       s.gates_online, s.availability_pct, s.telemetry_pct,
                       s.communication_pct, s.power_pct, s.humidity_pct
                  FROM reservoirs r
                  LEFT JOIN reservoir_operational_status s ON s.reservoir_id = r.id';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' WHERE r.company_id = :cid';
            $params['cid'] = $companyId;
        } elseif ($reservoirId !== null) {
            $sql .= ' WHERE r.id = :rid';
            $params['rid'] = $reservoirId;
        }

        // sort_order define a ordem de exibição: a primeira represa da lista é
        // a que o painel abre por padrão, então a ordem é um dado, não um acaso.
        $sql .= ' ORDER BY r.sort_order, r.code';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cadastro = $stmt->fetchAll();

        if ($cadastro === []) {
            return [];
        }

        $ids = array_column($cadastro, 'id');

        /* ------------------------- 2. último valor de cada métrica (todas) */
        $atuais = $this->valoresAtuais($ids);

        /* ----------------------------------- 3. acumulados e derivados */
        $derivados = $this->valoresDerivados($ids);

        /* ------------------------------------------ 4. contagem de sensores */
        $sensores = $this->contagemDeSensores($ids);

        /* ------------------------------------------------ 5. montagem final */
        $represas = [];
        foreach ($cadastro as $r) {
            $id = (string) $r['id'];
            $atual = $atuais[$id] ?? [];
            $extra = $derivados[$id] ?? [];
            $nivel = (float) ($atual['level'] ?? 0);

            $represas[] = [
                // --- identificação e localização (cadastro)
                'id'                 => $id,
                'code'               => (string) $r['code'],
                'name'               => (string) $r['name'],
                'company_id'         => (string) $r['company_id'],
                'city'               => (string) $r['city'],
                'basin'              => (string) $r['basin'],
                'lat'                => (float) $r['lat'],
                'lng'                => (float) $r['lng'],
                'coordinates_label'  => (string) $r['coordinates_label'],

                // --- valores atuais (última leitura de cada métrica)
                'level_pct'          => $this->numero($atual['level'] ?? 0),
                'volume_hm3'         => $this->numero($atual['storage'] ?? 0),
                'capacity_hm3'       => $this->numero($r['capacity_hm3']),
                'flow_m3s'           => $this->numero($atual['flow'] ?? 0),
                'inflow_m3s'         => $this->numero($atual['inflow'] ?? 0),
                'outflow_m3s'        => $this->numero($atual['outflow'] ?? 0),
                'ph'                 => $this->numero($atual['ph'] ?? 0),
                'ph_min'             => $this->numero($extra['ph_min'] ?? 0),
                'ph_max'             => $this->numero($extra['ph_max'] ?? 0),
                'rain_24h_mm'        => $this->numero($extra['rain_24h'] ?? 0),
                'rain_7d_mm'         => $this->numero($extra['rain_7d'] ?? 0),
                'rain_month_mm'      => $this->numero($extra['rain_month'] ?? 0),
                'duration_days'      => (int) $r['duration_days'],
                'status'             => $this->statusDoNivel($nivel),      // recalculado a partir do nível e dos limites

                // --- cotas
                'cota_m'             => $this->numero($atual['cota'] ?? 0),
                'cota_variation_m'   => $this->numero($extra['cota_variacao'] ?? 0),
                'cota_critical_m'    => $this->numero($r['cota_critical_m']),
                'cota_spill_m'       => $this->numero($r['cota_spill_m']),
                'cota_alert_m'       => $this->numero($r['cota_alert_m']),

                // --- planejamento hídrico (cadastro)
                'useful_volume_hm3'      => $this->numero($r['useful_volume_hm3']),
                'technical_reserve_hm3'  => $this->numero($r['technical_reserve_hm3']),
                'daily_consumption_hm3'  => $this->numero($r['daily_consumption_hm3']),
                'forecast_reliability_pct' => (int) $r['forecast_reliability_pct'],

                // --- situação operacional
                'sensors_online'     => (int) ($sensores[$id]['online'] ?? 0),
                'sensors_total'      => (int) ($sensores[$id]['total'] ?? 0),
                'gates_online'       => (int) $r['gates_online'],
                'gates_total'        => (int) $r['gates_total'],
                'availability_pct'   => $this->numero($r['availability_pct']),
                'telemetry_pct'      => $this->numero($r['telemetry_pct']),
                'communication_pct'  => $this->numero($r['communication_pct']),
                'power_pct'          => $this->numero($r['power_pct']),
                'humidity_pct'       => $this->numero($r['humidity_pct']),
                'last_reading_time'  => (string) ($extra['ultima_leitura'] ?? ''),
                'water_temp_c'       => $this->numero($atual['water_temp'] ?? 0),
            ];
        }

        return $represas;
    }

    /**
     * Último valor de cada métrica, para várias represas de uma vez.
     *
     * A subconsulta descobre o instante mais recente de cada par
     * (represa, métrica) e o JOIN traz o valor daquele instante — o jeito
     * padrão de resolver "o registro mais recente de cada grupo" em MySQL.
     *
     * @param array<int,string> $ids
     * @return array<string,array<string,float>> [represa][métrica] => valor
     */
    private function valoresAtuais(array $ids): array
    {
        $marcadores = $this->marcadores($ids, 'r');

        $stmt = $this->pdo->prepare(
            "SELECT l.reservoir_id, l.metric, l.value
               FROM readings l
               JOIN (SELECT reservoir_id, metric, MAX(recorded_at) AS ultimo
                       FROM readings
                      WHERE granularity = 'hour' AND reservoir_id IN ({$marcadores['sql']})
                      GROUP BY reservoir_id, metric) u
                 ON u.reservoir_id = l.reservoir_id AND u.metric = l.metric AND u.ultimo = l.recorded_at
              WHERE l.granularity = 'hour'"
        );
        $stmt->execute($marcadores['valores']);

        $mapa = [];
        foreach ($stmt->fetchAll() as $linha) {
            $mapa[$linha['reservoir_id']][$linha['metric']] = (float) $linha['value'];
        }
        return $mapa;
    }

    /**
     * Acumulados e comparações que a tela mostra como se fossem campos da
     * represa, mas que são contas sobre o histórico:
     *   chuva de 24 h / 7 dias / mês, mínimo e máximo de pH em 24 h,
     *   variação da cota nas últimas 24 h e horário da última leitura.
     *
     * @param array<int,string> $ids
     * @return array<string,array<string,mixed>>
     */
    private function valoresDerivados(array $ids): array
    {
        $agora = $this->agora();
        $ontem = $this->sql($agora->modify('-24 hours'));
        $seteDias = $this->sql($agora->modify('-6 days')->setTime(0, 0));
        $inicioMes = $this->sql($agora->modify('first day of this month')->setTime(0, 0));
        $agoraSql = $this->sql($agora);

        $m = $this->marcadores($ids, 'r');
        $resultado = [];

        // chuva acumulada: 24 h vem das leituras horárias; 7 dias e mês, das diárias
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id,
                    SUM(CASE WHEN granularity = 'hour' AND recorded_at > :ontem THEN value ELSE 0 END)     AS rain_24h,
                    SUM(CASE WHEN granularity = 'day'  AND recorded_at >= :sete  THEN value ELSE 0 END)    AS rain_7d,
                    SUM(CASE WHEN granularity = 'day'  AND recorded_at >= :mes   THEN value ELSE 0 END)    AS rain_month
               FROM readings
              WHERE metric = 'precipitation' AND reservoir_id IN ({$m['sql']}) AND recorded_at <= :agora
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + ['ontem' => $ontem, 'sete' => $seteDias, 'mes' => $inicioMes, 'agora' => $agoraSql]);
        foreach ($stmt->fetchAll() as $l) {
            $resultado[$l['reservoir_id']]['rain_24h'] = round((float) $l['rain_24h'], 1);
            $resultado[$l['reservoir_id']]['rain_7d'] = round((float) $l['rain_7d'], 1);
            $resultado[$l['reservoir_id']]['rain_month'] = round((float) $l['rain_month'], 1);
        }

        // faixa de pH nas últimas 24 h
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id, MIN(value) AS ph_min, MAX(value) AS ph_max
               FROM readings
              WHERE metric = 'ph' AND granularity = 'hour'
                AND reservoir_id IN ({$m['sql']}) AND recorded_at > :ontem AND recorded_at <= :agora
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + ['ontem' => $ontem, 'agora' => $agoraSql]);
        foreach ($stmt->fetchAll() as $l) {
            $resultado[$l['reservoir_id']]['ph_min'] = round((float) $l['ph_min'], 2);
            $resultado[$l['reservoir_id']]['ph_max'] = round((float) $l['ph_max'], 2);
        }

        // variação da cota: valor atual menos o de 24 h atrás
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id,
                    MAX(CASE WHEN recorded_at = :agora THEN value END) -
                    MAX(CASE WHEN recorded_at = :ontem THEN value END) AS variacao
               FROM readings
              WHERE metric = 'cota' AND granularity = 'hour'
                AND reservoir_id IN ({$m['sql']}) AND recorded_at IN (:agora2, :ontem2)
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + [
            'agora' => $agoraSql, 'ontem' => $ontem,
            'agora2' => $agoraSql, 'ontem2' => $ontem,
        ]);
        foreach ($stmt->fetchAll() as $l) {
            $resultado[$l['reservoir_id']]['cota_variacao'] = round((float) $l['variacao'], 1);
        }

        // horário da leitura mais recente (exibido como "última leitura")
        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id, MAX(recorded_at) AS ultima
               FROM readings
              WHERE granularity = 'hour' AND reservoir_id IN ({$m['sql']}) AND recorded_at <= :agora
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores'] + ['agora' => $agoraSql]);
        foreach ($stmt->fetchAll() as $l) {
            $resultado[$l['reservoir_id']]['ultima_leitura'] = $this->data((string) $l['ultima'])->format('H:i');
        }

        return $resultado;
    }

    /**
     * Quantos sensores cada represa tem e quantos estão transmitindo.
     *
     * @param array<int,string> $ids
     * @return array<string,array{total:int,online:int}>
     */
    private function contagemDeSensores(array $ids): array
    {
        $m = $this->marcadores($ids, 'r');

        $stmt = $this->pdo->prepare(
            "SELECT reservoir_id,
                    COUNT(*) AS total,
                    SUM(status = 'online') AS online
               FROM sensors
              WHERE reservoir_id IN ({$m['sql']})
              GROUP BY reservoir_id"
        );
        $stmt->execute($m['valores']);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['reservoir_id']] = ['total' => (int) $l['total'], 'online' => (int) $l['online']];
        }
        return $mapa;
    }

    /**
     * Monta a lista de marcadores nomeados de um IN (...).
     *
     * O PDO não aceita um array em um único parâmetro: é preciso um marcador
     * por item (:r0, :r1, ...). Isso mantém a consulta preparada — os IDs
     * continuam como parâmetros, nunca concatenados no SQL.
     *
     * @param array<int,string> $ids
     * @return array{sql:string,valores:array<string,string>}
     */
    private function marcadores(array $ids, string $prefixo): array
    {
        $nomes = [];
        $valores = [];

        foreach (array_values($ids) as $i => $id) {
            $nomes[] = ':' . $prefixo . $i;
            $valores[$prefixo . $i] = $id;
        }

        return ['sql' => implode(', ', $nomes), 'valores' => $valores];
    }

    /** Represas de todas as empresas, ou só de uma. */
    public function reservoirs(string $companyId = 'all'): array
    {
        return $this->montarRepresas($companyId === 'all' ? null : $companyId);
    }

    /** Uma represa pelo ID; null quando não existe. */
    public function reservoir(string $reservoirId): ?array
    {
        $lista = $this->montarRepresas(null, $reservoirId);
        return $lista[0] ?? null;
    }

    /* ============================================================ séries */

    /**
     * Série temporal de uma métrica, pronta para o gráfico.
     *
     * Cada período usa a resolução adequada:
     *   24h        -> leituras horárias das últimas 24 horas (rótulo "09:30")
     *   7d/30d/90d -> leituras diárias do intervalo (rótulo "22 Mai")
     *   12m        -> leituras diárias agrupadas por mês (rótulo "Mai"); a
     *                 chuva é somada (total do mês) e as demais métricas são
     *                 médias, porque somar nível ou pH não teria sentido.
     *
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function series(string $reservoirId, string $metric, string $period): array
    {
        $agora = $this->agora();
        $fim = $this->sql($agora);

        if ($period === '12m') {
            return $this->serieMensal($reservoirId, $metric, $agora);
        }

        if ($period === '24h') {
            $inicio = $this->sql($agora->modify('-23 hours'));
            $granularidade = 'hour';
        } else {
            $dias = ['7d' => 6, '30d' => 29, '90d' => 89];             // 6 dias atrás + hoje = 7 pontos
            $inicio = $this->sql($agora->modify('-' . ($dias[$period] ?? 6) . ' days'));
            $granularidade = 'day';
        }

        $stmt = $this->pdo->prepare(
            'SELECT recorded_at, value
               FROM readings
              WHERE reservoir_id = :rid AND metric = :metric AND granularity = :gran
                AND recorded_at BETWEEN :inicio AND :fim
              ORDER BY recorded_at ASC'
        );
        $stmt->execute([
            'rid'    => $reservoirId,
            'metric' => $metric,
            'gran'   => $granularidade,
            'inicio' => $inicio,
            'fim'    => $fim,
        ]);

        $labels = [];
        $values = [];

        foreach ($stmt->fetchAll() as $linha) {
            $quando = $this->data((string) $linha['recorded_at']);
            $labels[] = $granularidade === 'hour' ? $quando->format('H:i') : Clock::shortDate($quando);
            $values[] = (float) $linha['value'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Série de 12 meses: agrupa as leituras diárias por mês.
     * Usa SUM para chuva (acumulado do mês) e AVG para as demais métricas.
     */
    private function serieMensal(string $reservoirId, string $metric, DateTimeImmutable $agora): array
    {
        $inicio = $this->sql($agora->modify('first day of -11 months')->setTime(0, 0));
        $agregacao = $metric === 'precipitation' ? 'SUM(value)' : 'AVG(value)';

        // $agregacao entra no texto do SQL, mas só pode ser uma das duas
        // expressões acima — nunca um valor vindo da requisição.
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(recorded_at, '%Y-%m-01') AS mes, {$agregacao} AS valor
               FROM readings
              WHERE reservoir_id = :rid AND metric = :metric AND granularity = 'day'
                AND recorded_at BETWEEN :inicio AND :fim
              GROUP BY mes
              ORDER BY mes ASC"
        );
        $stmt->execute([
            'rid'    => $reservoirId,
            'metric' => $metric,
            'inicio' => $inicio,
            'fim'    => $this->sql($agora),
        ]);

        $labels = [];
        $values = [];

        foreach ($stmt->fetchAll() as $linha) {
            $labels[] = Clock::shortMonth($this->data((string) $linha['mes']));
            $values[] = round((float) $linha['valor'], $metric === 'ph' ? 2 : 1);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /* ========================================================== leituras */

    /**
     * Últimas leituras de uma métrica, da mais recente para a mais antiga.
     *
     * Cada tela mostra colunas diferentes, então o formato da linha muda
     * conforme a métrica — exatamente como o repositório simulado fazia.
     */
    public function readings(string $reservoirId, string $metric, int $limit = 5): array
    {
        switch ($metric) {
            case 'flow':
                return $this->leiturasDeVazao($reservoirId, $limit);
            case 'level':
                return $this->leiturasDeNivel($reservoirId, $limit);
            case 'ph':
                return $this->leiturasDePh($reservoirId, $limit);
            case 'storage':
                return $this->leiturasDeVolume($reservoirId, $limit);
            default:
                return $this->leiturasGenericas($reservoirId, $metric, $limit);
        }
    }

    /**
     * Busca as últimas N leituras horárias de uma ou mais métricas e devolve
     * os valores agrupados por instante — a base das tabelas de leituras.
     *
     * @param array<int,string> $metricas
     * @return array<string,array<string,float>> [instante][métrica] => valor
     */
    private function ultimasPorInstante(string $reservoirId, array $metricas, int $limit, string $granularidade = 'hour'): array
    {
        $m = $this->marcadores($metricas, 'm');

        $stmt = $this->pdo->prepare(
            "SELECT recorded_at, metric, value
               FROM readings
              WHERE reservoir_id = :rid AND granularity = :gran
                AND metric IN ({$m['sql']}) AND recorded_at <= :agora
              ORDER BY recorded_at DESC
              LIMIT :lim"
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('gran', $granularidade);
        $stmt->bindValue('agora', $this->sql($this->agora()));
        foreach ($m['valores'] as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        // LIMIT precisa de inteiro; o total pedido cobre todas as métricas do grupo
        $stmt->bindValue('lim', $limit * count($metricas), PDO::PARAM_INT);
        $stmt->execute();

        $porInstante = [];
        foreach ($stmt->fetchAll() as $linha) {
            $porInstante[(string) $linha['recorded_at']][$linha['metric']] = (float) $linha['value'];
        }

        return $porInstante;
    }

    /** Tabela da tela de vazão: afluência, defluência e o balanço entre elas. */
    private function leiturasDeVazao(string $reservoirId, int $limit): array
    {
        $linhas = [];

        foreach ($this->ultimasPorInstante($reservoirId, ['inflow', 'outflow'], $limit) as $instante => $valores) {
            $quando = $this->data($instante);
            $entrada = $valores['inflow'] ?? 0.0;
            $saida = $valores['outflow'] ?? 0.0;

            $linhas[] = [
                'at'      => Clock::iso($quando),
                'time'    => Clock::dateTime($quando),
                'inflow'  => round($entrada, 1),
                'outflow' => round($saida, 1),
                'balance' => round($entrada - $saida, 1),                // quanto o reservatório ganhou (ou perdeu) naquele instante
                'status'  => 'online',
            ];
        }

        return array_slice($linhas, 0, $limit);
    }

    /**
     * Tabela da tela de nível: cota, percentual e variação em 24 horas.
     *
     * A coluna da tela é "Variação (24h)", então cada linha é comparada com a
     * leitura do MESMO horário do dia anterior — não com a leitura de uma hora
     * antes, que quase não muda.
     */
    private function leiturasDeNivel(string $reservoirId, int $limit): array
    {
        $instantes = $this->ultimasPorInstante($reservoirId, ['cota', 'level'], $limit);
        $chaves = array_keys($instantes);

        if ($chaves === []) {
            return [];
        }

        // cotas de 24 h antes de cada linha, buscadas de uma vez só
        $anteriores = [];
        foreach ($chaves as $instante) {
            $anteriores[$instante] = $this->sql($this->data($instante)->modify('-24 hours'));
        }

        $m = $this->marcadores(array_values($anteriores), 'v');
        $stmt = $this->pdo->prepare(
            "SELECT recorded_at, value
               FROM readings
              WHERE reservoir_id = :rid AND metric = 'cota' AND granularity = 'hour'
                AND recorded_at IN ({$m['sql']})"
        );
        $stmt->execute($m['valores'] + ['rid' => $reservoirId]);

        $cotaOntem = [];
        foreach ($stmt->fetchAll() as $l) {
            $cotaOntem[(string) $l['recorded_at']] = (float) $l['value'];
        }

        $linhas = [];
        foreach ($chaves as $instante) {
            $quando = $this->data($instante);
            $cota = $instantes[$instante]['cota'] ?? 0.0;
            $ontem = $cotaOntem[$anteriores[$instante]] ?? $cota;        // sem leitura de 24 h atrás, a variação fica em zero

            $linhas[] = [
                'at'        => Clock::iso($quando),
                'time'      => Clock::dateTime($quando),
                'cota'      => round($cota, 1),
                'level'     => round($instantes[$instante]['level'] ?? 0.0, 1),
                'variation' => round($cota - $ontem, 1),
            ];
        }

        return $linhas;
    }

    /**
     * Tabela da tela de pH: as coletas mais recentes, com o ponto onde foram
     * feitas. Vem de ph_point_readings (medições por ponto), com JOIN em
     * ph_points para trazer o nome.
     */
    private function leiturasDePh(string $reservoirId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.recorded_at, l.ph, l.water_temp_c, p.name
               FROM ph_point_readings l
               JOIN ph_points p ON p.id = l.ph_point_id
              WHERE p.reservoir_id = :rid AND l.recorded_at <= :agora
              ORDER BY l.recorded_at DESC, p.sort_order ASC
              LIMIT :lim'
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('agora', $this->sql($this->agora()));
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $linhas = [];
        foreach ($stmt->fetchAll() as $l) {
            $quando = $this->data((string) $l['recorded_at']);
            $ph = (float) $l['ph'];

            $linhas[] = [
                'at'     => Clock::iso($quando),
                'time'   => Clock::dateTime($quando),
                'ph'     => round($ph, 1),
                'temp'   => round((float) $l['water_temp_c'], 1),
                'point'  => (string) $l['name'],
                'status' => $this->statusDoPh($ph),                        // classificado pela faixa ideal cadastrada
            ];
        }

        return $linhas;
    }

    /** Tabela da tela de volume: valores diários, com a variação de um dia para o outro. */
    private function leiturasDeVolume(string $reservoirId, int $limit): array
    {
        $instantes = $this->ultimasPorInstante($reservoirId, ['storage', 'level'], $limit + 1, 'day');
        $chaves = array_keys($instantes);
        $linhas = [];

        foreach ($chaves as $i => $instante) {
            if ($i >= $limit) {
                break;
            }

            $quando = $this->data($instante);
            $volume = $instantes[$instante]['storage'] ?? 0.0;
            $anterior = isset($chaves[$i + 1]) ? ($instantes[$chaves[$i + 1]]['storage'] ?? $volume) : $volume;
            $ocupacao = $instantes[$instante]['level'] ?? 0.0;

            $linhas[] = [
                'at'        => Clock::iso($quando),
                'date'      => Clock::date($quando),
                'volume'    => round($volume, 0),
                'occupancy' => round($ocupacao, 1),
                'variation' => round($volume - $anterior, 0),              // ganho ou perda de volume no dia
                'status'    => $this->statusDoNivel($ocupacao),
            ];
        }

        return $linhas;
    }

    /** Formato simples, para métricas que não têm tabela própria na interface. */
    private function leiturasGenericas(string $reservoirId, string $metric, int $limit): array
    {
        $linhas = [];

        foreach ($this->ultimasPorInstante($reservoirId, [$metric], $limit) as $instante => $valores) {
            $quando = $this->data($instante);
            $linhas[] = [
                'at'     => Clock::iso($quando),
                'time'   => Clock::dateTime($quando),
                'value'  => round($valores[$metric] ?? 0.0, 1),
                'status' => 'normal',
            ];
        }

        return $linhas;
    }

    /* =================================================== listas auxiliares */

    /** Sensores instalados na represa, na ordem de exibição cadastrada. */
    public function sensors(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, location, type, status
               FROM sensors
              WHERE reservoir_id = :rid
              ORDER BY sort_order, id'
        );
        $stmt->execute(['rid' => $reservoirId]);

        return $stmt->fetchAll();
    }

    /**
     * Pontos de coleta de pH com a medição mais recente de cada um.
     * O valor não fica guardado no cadastro do ponto: vem sempre da última
     * linha de ph_point_readings.
     */
    public function phPoints(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.name, l.ph
               FROM ph_points p
               JOIN ph_point_readings l ON l.ph_point_id = p.id
               JOIN (SELECT ph_point_id, MAX(recorded_at) AS ultimo
                       FROM ph_point_readings
                      WHERE recorded_at <= :agora
                      GROUP BY ph_point_id) u
                 ON u.ph_point_id = l.ph_point_id AND u.ultimo = l.recorded_at
              WHERE p.reservoir_id = :rid
              ORDER BY p.sort_order, p.id'
        );
        $stmt->execute(['rid' => $reservoirId, 'agora' => $this->sql($this->agora())]);

        $pontos = [];
        foreach ($stmt->fetchAll() as $l) {
            $ph = round((float) $l['ph'], 1);
            $pontos[] = [
                'name'   => (string) $l['name'],
                'ph'     => $ph,
                'status' => $this->statusDoPh($ph),
            ];
        }

        return $pontos;
    }

    /**
     * Estações pluviométricas com o acumulado das últimas 24 horas, somado
     * das medições horárias de cada estação.
     */
    public function rainStations(string $reservoirId): array
    {
        $agora = $this->agora();

        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.name, e.status,
                    COALESCE(SUM(l.rain_mm), 0) AS rain_24h
               FROM rain_stations e
               LEFT JOIN rain_station_readings l
                 ON l.station_id = e.id AND l.recorded_at > :ontem AND l.recorded_at <= :agora
              WHERE e.reservoir_id = :rid
              GROUP BY e.id, e.name, e.status, e.sort_order
              ORDER BY e.sort_order, e.id'
        );
        $stmt->execute([
            'rid'   => $reservoirId,
            'ontem' => $this->sql($agora->modify('-24 hours')),
            'agora' => $this->sql($agora),
        ]);

        $estacoes = [];
        foreach ($stmt->fetchAll() as $l) {
            $estacoes[] = [
                'id'       => (string) $l['id'],
                'name'     => (string) $l['name'],
                'rain_24h' => round((float) $l['rain_24h'], 1),
                'status'   => (string) $l['status'],
            ];
        }

        return $estacoes;
    }

    /** Eventos operacionais da represa, dos mais recentes para os mais antigos. */
    public function operationEvents(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT occurred_at, component, event, priority, status
               FROM operation_events
              WHERE reservoir_id = :rid
              ORDER BY occurred_at DESC'
        );
        $stmt->execute(['rid' => $reservoirId]);

        $eventos = [];
        foreach ($stmt->fetchAll() as $l) {
            $eventos[] = [
                'at'        => Clock::iso($this->data((string) $l['occurred_at'])),
                'component' => (string) $l['component'],
                'event'     => (string) $l['event'],
                'priority'  => (string) $l['priority'],
                'status'    => (string) $l['status'],
            ];
        }

        return $eventos;
    }

    /** Manutenções programadas, da mais próxima para a mais distante. */
    public function maintenances(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT scheduled_date, equipment, type, priority
               FROM maintenances
              WHERE reservoir_id = :rid AND status = :status
              ORDER BY scheduled_date ASC'
        );
        $stmt->execute(['rid' => $reservoirId, 'status' => 'scheduled']);

        $lista = [];
        foreach ($stmt->fetchAll() as $l) {
            $lista[] = [
                'date'      => (string) $l['scheduled_date'],              // 'AAAA-MM-DD'; a tela formata para dd/mm/aaaa
                'equipment' => (string) $l['equipment'],
                'type'      => (string) $l['type'],
                'priority'  => (string) $l['priority'],
            ];
        }

        return $lista;
    }

    /* ======================================================= alertas */

    /**
     * Alertas com os filtros da tela. Cada filtro diferente de 'all'
     * acrescenta uma condição à consulta preparada; os valores continuam
     * indo por parâmetro.
     */
    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        $sql = 'SELECT id, reservoir_id, severity, status, title, metric_label,
                       detected_at, owner, current_value, threshold, detail, threshold_detail
                  FROM alerts
                 WHERE 1 = 1';
        $params = [];

        if ($reservoirId !== 'all') {
            $sql .= ' AND reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($severity !== 'all') {
            $sql .= ' AND severity = :sev';
            $params['sev'] = $severity;
        }
        if ($status !== 'all') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY detected_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();

        if ($linhas === []) {
            return [];
        }

        $timelines = $this->linhasDoTempo(array_column($linhas, 'id'));
        $alertas = [];

        foreach ($linhas as $l) {
            $alertas[] = [
                'id'               => (string) $l['id'],
                'reservoir_id'     => (string) $l['reservoir_id'],
                'severity'         => (string) $l['severity'],
                'title'            => (string) $l['title'],
                'metric'           => (string) $l['metric_label'],
                'detected_at'      => Clock::iso($this->data((string) $l['detected_at'])),
                'owner'            => (string) $l['owner'],
                'status'           => (string) $l['status'],
                'current_value'    => (string) $l['current_value'],
                'threshold'        => (string) $l['threshold'],
                'detail'           => (string) $l['detail'],
                'threshold_detail' => (string) $l['threshold_detail'],
                'timeline'         => $timelines[(string) $l['id']] ?? [],
            ];
        }

        return $alertas;
    }

    /**
     * Etapas de tratamento de vários alertas em uma única consulta
     * (evita uma consulta por alerta dentro do laço).
     *
     * @param array<int,string> $ids
     * @return array<string,array<int,array{at:?string,text:string,done:bool}>>
     */
    private function linhasDoTempo(array $ids): array
    {
        $m = $this->marcadores($ids, 'a');

        $stmt = $this->pdo->prepare(
            "SELECT alert_id, happened_at, description, done
               FROM alert_timeline
              WHERE alert_id IN ({$m['sql']})
              ORDER BY alert_id, step_order"
        );
        $stmt->execute($m['valores']);

        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[(string) $l['alert_id']][] = [
                'at'   => $l['happened_at'] === null ? null : Clock::iso($this->data((string) $l['happened_at'])),
                'text' => (string) $l['description'],
                'done' => (bool) $l['done'],
            ];
        }

        return $mapa;
    }

    /* ==================================================== relatórios */

    /** Relatórios já gerados, com os filtros da tela. */
    public function reports(string $reservoirId = 'all', string $type = 'all', string $status = 'all'): array
    {
        $sql = 'SELECT id, reservoir_id, name, type, period_label, generated_at, owner, status, icon
                  FROM reports
                 WHERE 1 = 1';
        $params = [];

        if ($reservoirId !== 'all') {
            $sql .= ' AND reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($type !== 'all') {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }
        if ($status !== 'all') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY generated_at DESC';

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
                'generated_at' => Clock::iso($this->data((string) $l['generated_at'])),
                'owner'        => (string) $l['owner'],
                'status'       => (string) $l['status'],
                'icon'         => (string) $l['icon'],
            ];
        }

        return $relatorios;
    }

    /** Relatórios com geração automática programada. */
    public function scheduledReports(): array
    {
        $linhas = $this->pdo->query(
            'SELECT name, frequency, next_run
               FROM scheduled_reports
              WHERE enabled = 1
              ORDER BY next_run ASC'
        )->fetchAll();

        $lista = [];
        foreach ($linhas as $l) {
            $lista[] = [
                'name'      => (string) $l['name'],
                'frequency' => (string) $l['frequency'],
                'next_run'  => Clock::iso($this->data((string) $l['next_run'])),
            ];
        }

        return $lista;
    }

    /* ==================================================== configurações */

    /**
     * Configurações exibidas na tela de ajustes, montadas a partir de quatro
     * tabelas: pares chave/valor, indicadores, limites vigentes e canais de
     * notificação.
     */
    public function settings(): array
    {
        /* ------------------------------------------- pares chave/valor */
        $pares = [];
        foreach ($this->pdo->query('SELECT setting_key, setting_value, value_type FROM system_settings')->fetchAll() as $l) {
            $valor = (string) $l['setting_value'];
            // o tipo guardado na própria tabela diz como converter o texto
            if ($l['value_type'] === 'bool') {
                $pares[$l['setting_key']] = $valor === '1';
            } elseif ($l['value_type'] === 'int') {
                $pares[$l['setting_key']] = (int) $valor;
            } elseif ($l['value_type'] === 'float') {
                $pares[$l['setting_key']] = (float) $valor;
            } else {
                $pares[$l['setting_key']] = $valor;
            }
        }

        /* ----------------------------------------------- indicadores */
        $indicadores = [];
        foreach ($this->pdo->query('SELECT id, label, enabled FROM setting_indicators ORDER BY sort_order, id')->fetchAll() as $l) {
            $indicadores[] = [
                'id'      => (string) $l['id'],
                'label'   => (string) $l['label'],
                'enabled' => (bool) $l['enabled'],
            ];
        }

        /* ------------------------------------------------ notificações */
        $notificacoes = [];
        foreach ($this->pdo->query('SELECT id, label, target, enabled FROM notification_channels ORDER BY sort_order, id')->fetchAll() as $l) {
            $notificacoes[] = [
                'id'      => (string) $l['id'],
                'label'   => (string) $l['label'],
                'target'  => (string) $l['target'],
                'enabled' => (bool) $l['enabled'],
            ];
        }

        /* ---------------------------------------------------- limites */
        $limites = $this->limites();

        return [
            'units' => [
                'level'  => (string) ($pares['units.level'] ?? ''),
                'volume' => (string) ($pares['units.volume'] ?? ''),
                'flow'   => (string) ($pares['units.flow'] ?? ''),
            ],
            'refresh_interval' => (string) ($pares['refresh_interval'] ?? ''),
            'auto_refresh'     => (bool) ($pares['auto_refresh'] ?? true),
            'indicators'       => $indicadores,
            'thresholds' => [
                'level_attention_pct' => $this->numero($limites['level']['attention']['min'] ?? 80),
                'ph_min'              => $this->numero($limites['ph']['normal']['min'] ?? 6.5),
                'ph_max'              => $this->numero($limites['ph']['normal']['max'] ?? 8.5),
                'rain_critical_mm'    => $this->numero($limites['precipitation']['critical']['min'] ?? 60),
            ],
            'notifications' => $notificacoes,
        ];
    }
}
