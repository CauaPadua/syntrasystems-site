<?php
/**
 * Aquapulse — abertura e tratamento de alertas.
 *
 * Os alertas nascem das leituras: a cada medição recebida, evaluate() aplica os
 * limites de StatusRules. Se o valor saiu da faixa normal e ainda não há alerta
 * aberto para aquela grandeza na represa, um alerta é criado; se já há, ele é
 * atualizado (e agravado, quando o valor passa de atenção para crítico).
 *
 * Um alerta resolvido não reabre sozinho: enquanto o valor continua fora da faixa,
 * o problema é o mesmo que alguém já tratou. Um alerta novo só nasce depois que o
 * valor volta ao normal (normalized_at) e sai de novo, ou se ele piora (atenção ->
 * crítico). Do mesmo jeito, um equipamento parado só ganha alerta novo se voltar
 * a transmitir e parar outra vez.
 *
 * Há também o alerta de COMUNICAÇÃO: um equipamento ativo que já transmitiu e
 * ficou mais de EquipmentRules::ONLINE_WINDOW sem nenhuma leitura aceita
 * (sensor com defeito, cabo rompido, sem energia) ganha o alerta "Equipamento
 * sem leituras". Quando ele volta a transmitir, isso entra na linha do tempo.
 *
 * O tratamento é feito por pessoas, pela tela de alertas: "assumir" e
 * "resolver" gravam o novo status e uma etapa na linha do tempo.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Sequence;
use DateTimeImmutable;
use PDO;

final class AlertService
{
    public const SEM_RESPONSAVEL = 'Não atribuído';

    /** Grandeza usada nos alertas de equipamento sem leituras. */
    public const COMUNICACAO = 'communication';

    private const VOLTOU = 'Equipamento voltou a transmitir';
    private const PAROU = 'Equipamento parou de transmitir';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Avalia um valor e abre ou atualiza o alerta correspondente.
     *
     * @param float $value para chuva, o acumulado das últimas 24 h; para as demais grandezas, a própria leitura
     * @return array{classification:string,alert_id:?string}
     */
    public function evaluate(string $reservoirId, ?string $sensorId, string $metric, float $value, DateTimeImmutable $when): array
    {
        $represa = $metric === 'level' ? $this->cotas($reservoirId) : [];       // os limites do nível são os da represa
        $classificacao = StatusRules::classify($metric, $value, $represa);

        if ($classificacao === 'normal') {
            $this->normalizou($reservoirId, $metric, $when);
            return ['classification' => 'normal', 'alert_id' => null];
        }

        $aberto = $this->alertaAberto($reservoirId, $metric);

        if ($aberto === null) {
            if ($this->jaTratado($reservoirId, $metric, $classificacao)) {
                return ['classification' => $classificacao, 'alert_id' => null];   // resolvido e ainda sem voltar ao normal
            }
            $id = $this->abrir($reservoirId, $sensorId, $metric, $value, $classificacao, $represa, $when);
            return ['classification' => $classificacao, 'alert_id' => $id];
        }

        // Já existe alerta em aberto: atualiza o valor e só agrava, nunca rebaixa
        // sozinho — quem encerra o alerta é o operador. O limite mostrado é o que está
        // em vigor (ele pode ter mudado); o da abertura fica na linha do tempo.
        $agravou = $classificacao === 'critical' && $aberto['severity'] !== 'critical';
        $gravidade = $agravou ? 'critical' : (string) $aberto['severity'];

        $stmt = $this->pdo->prepare(
            'UPDATE alerts
                SET current_value = :valor, severity = :sev, title = :titulo,
                    threshold = :limite, threshold_detail = :detalhe, normalized_at = NULL
              WHERE id = :id'
        );
        $stmt->execute([
            'valor'   => $this->formatar($metric, $value),
            'sev'     => $gravidade,
            'titulo'  => $agravou ? $this->titulo($metric, 'critical') : $aberto['title'],
            'limite'  => StatusRules::thresholdText($metric, $gravidade, $represa),
            'detalhe' => $this->detalheDoLimite($metric, $gravidade, $represa),
            'id'      => $aberto['id'],
        ]);

        if ($agravou) {
            $this->etapa((string) $aberto['id'], 'Agravado para crítico: ' . $this->formatar($metric, $value) . '.', $when);
        }

        return ['classification' => $classificacao, 'alert_id' => (string) $aberto['id']];
    }

    /**
     * Abre o alerta "Equipamento sem leituras" para cada equipamento que a estação
     * lê e que está há mais de EquipmentRules::ONLINE_WINDOW sem nenhuma leitura
     * aceita, contados a partir de quando a estação ligou. Roda a cada pacote que a
     * estação envia (api/v1/ingest/batch.php), inclusive o aviso de "estou ligada"
     * que ela manda quando não tem leituras novas.
     *
     * Contar a partir da ligação da estação evita alarme falso: logo depois de ligar,
     * ainda não chegou leitura de todos os sensores, e a última gravada no banco pode
     * ser de horas antes.
     *
     * @param list<string> $codigos equipamentos que a estação lê
     * @return list<array{alert_id:string,equipment_id:string}> alertas abertos agora
     */
    public function checkCommunication(DateTimeImmutable $agora, array $codigos, DateTimeImmutable $estacaoLigadaEm): array
    {
        if ($codigos === []) {
            return [];
        }

        $marcadores = implode(', ', array_fill(0, count($codigos), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT s.id, s.name, s.reservoir_id,
                    (SELECT MAX(l.recorded_at) FROM readings l WHERE l.sensor_id = s.id) AS ultima
               FROM sensors s
              WHERE s.status = 'active' AND s.id IN ({$marcadores})"
        );
        $stmt->execute(array_values($codigos));
        $abertos = [];

        foreach ($stmt->fetchAll() as $e) {
            $ultima = $e['ultima'] === null ? null : new DateTimeImmutable((string) $e['ultima'], Clock::timezone());

            // Esperando desde a última leitura, ou desde que a estação ligou (o que for mais recente).
            $desde = ($ultima === null || $ultima < $estacaoLigadaEm) ? $estacaoLigadaEm : $ultima;
            if ($agora->getTimestamp() - $desde->getTimestamp() <= EquipmentRules::ONLINE_WINDOW) {
                continue;                                         // transmitindo normalmente (ou estação acabou de ligar)
            }

            $sensor = (string) $e['id'];
            $parado = $ultima === null ? 'nenhuma leitura recebida' : 'sem leitura desde ' . $ultima->format('H:i:s');
            $aberto = $this->alertaDeComunicacao($sensor);

            if ($aberto === null && $this->paradaJaTratada($sensor, $ultima)) {
                continue;                                         // a mesma parada já foi resolvida por alguém
            }

            if ($aberto !== null) {
                // Já existe alerta. Se ele tinha voltado e parou de novo, registra.
                if (strpos($this->ultimaEtapa($aberto), self::VOLTOU) === 0) {
                    $this->pdo->prepare('UPDATE alerts SET current_value = :v WHERE id = :id')->execute(['v' => $parado, 'id' => $aberto]);
                    $this->etapa($aberto, self::PAROU . ' de novo (' . $parado . ').', $agora);
                }
                continue;
            }

            $id = $this->proximoId();
            $minutos = intdiv(EquipmentRules::ONLINE_WINDOW, 60);
            $stmt2 = $this->pdo->prepare(
                'INSERT INTO alerts (id, reservoir_id, sensor_id, severity, metric, status, title, metric_label,
                                     detected_at, owner, current_value, threshold, detail, threshold_detail)
                 VALUES (:id, :rid, :sid, :sev, :metric, :status, :titulo, :rotulo,
                         :quando, :owner, :valor, :limite, :detalhe, :detalhe_limite)'
            );
            $stmt2->execute([
                'id'             => $id,
                'rid'            => (string) $e['reservoir_id'],
                'sid'            => $sensor,
                'sev'            => 'attention',
                'metric'         => self::COMUNICACAO,
                'status'         => 'new',
                'titulo'         => 'Equipamento sem leituras',
                'rotulo'         => 'Comunicação',
                'quando'         => Clock::sql($agora),
                'owner'          => self::SEM_RESPONSAVEL,
                'valor'          => $parado,
                'limite'         => "{$minutos} min sem leitura",
                'detalhe'        => "Equipamento {$sensor} ({$e['name']})",
                'detalhe_limite' => 'Janela de comunicação',
            ]);
            $this->etapa($id, self::PAROU . ": {$sensor} {$parado} (mais de {$minutos} min com a estação ligada). Verificar o sensor em campo.", $agora);

            $abertos[] = ['alert_id' => $id, 'equipment_id' => $sensor];
        }

        return $abertos;
    }

    /**
     * Uma leitura do equipamento foi aceita: se ele tinha alerta de comunicação
     * aberto, registra na linha do tempo que voltou a transmitir. O alerta
     * continua aberto até o operador resolvê-lo.
     */
    public function communicationRestored(string $sensorId, DateTimeImmutable $quando): void
    {
        $aberto = $this->alertaDeComunicacao($sensorId);
        if ($aberto === null || strpos($this->ultimaEtapa($aberto), self::VOLTOU) === 0) {
            return;
        }

        $this->pdo->prepare('UPDATE alerts SET current_value = :v WHERE id = :id')
            ->execute(['v' => 'voltou às ' . $quando->format('H:i:s'), 'id' => $aberto]);
        $this->etapa($aberto, self::VOLTOU . ' (leitura das ' . $quando->format('H:i:s') . '). Resolva o alerta depois de conferir.', $quando);
    }

    /**
     * "Assumir alerta": passa de novo para em análise e registra o responsável.
     *
     * @param array{id:int,name:string} $user
     */
    public function acknowledge(string $alertId, array $user): void
    {
        $alerta = $this->buscar($alertId);

        if ($alerta['status'] !== 'new') {
            throw new ApiException('INVALID_TRANSITION', 'Só é possível assumir um alerta que ainda está como novo.', 409);
        }

        $stmt = $this->pdo->prepare("UPDATE alerts SET status = 'analysis', owner = :owner WHERE id = :id");
        $stmt->execute(['owner' => $user['name'], 'id' => $alertId]);

        $this->etapa($alertId, 'Alerta assumido por ' . $user['name'] . '.', Clock::now());
    }

    /**
     * "Marcar como resolvido": encerra o alerta e guarda quando foi resolvido.
     *
     * @param array{id:int,name:string} $user
     */
    public function resolve(string $alertId, array $user): void
    {
        $alerta = $this->buscar($alertId);

        if ($alerta['status'] === 'resolved') {
            throw new ApiException('INVALID_TRANSITION', 'Este alerta já está resolvido.', 409);
        }

        $agora = Clock::now();
        $stmt = $this->pdo->prepare(
            "UPDATE alerts SET status = 'resolved', resolved_at = :quando, owner = :owner WHERE id = :id"
        );
        $stmt->execute([
            'quando' => Clock::sql($agora),
            // quem resolve sem ter assumido antes passa a ser o responsável
            'owner'  => $alerta['owner'] === self::SEM_RESPONSAVEL ? $user['name'] : $alerta['owner'],
            'id'     => $alertId,
        ]);

        $this->etapa($alertId, 'Alerta resolvido por ' . $user['name'] . '.', $agora);
    }

    /* ---------------------------------------------------------- internos */

    /** @return array<string,mixed> */
    private function buscar(string $alertId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, status, owner FROM alerts WHERE id = :id');
        $stmt->execute(['id' => $alertId]);
        $alerta = $stmt->fetch();

        if ($alerta === false) {
            throw new ApiException('ALERT_NOT_FOUND', 'Alerta não encontrado.', 404);
        }
        return $alerta;
    }

    /** Cotas do cadastro da represa: é delas que saem os limites do nível (StatusRules::limitsFor). */
    private function cotas(string $reservoirId): array
    {
        $stmt = $this->pdo->prepare('SELECT cota_min_m, cota_max_m, cota_alert_m, cota_spill_m FROM reservoirs WHERE id = :id');
        $stmt->execute(['id' => $reservoirId]);
        $cotas = $stmt->fetch();

        if ($cotas === false) {
            throw new ApiException('RESERVOIR_NOT_FOUND', 'Represa não encontrada.', 404);
        }
        return $cotas;
    }

    /** O valor voltou ao normal: registra nos alertas da grandeza que ainda não sabiam disso. */
    private function normalizou(string $reservoirId, string $metric, DateTimeImmutable $when): void
    {
        $this->pdo->prepare(
            'UPDATE alerts SET normalized_at = :quando
              WHERE reservoir_id = :rid AND metric = :metric AND normalized_at IS NULL'
        )->execute(['quando' => Clock::sql($when), 'rid' => $reservoirId, 'metric' => $metric]);
    }

    /**
     * O último alerta da grandeza foi resolvido e o valor não voltou ao normal desde
     * então? Se o valor agora é mais grave que o daquele alerta, não conta como tratado.
     */
    private function jaTratado(string $reservoirId, string $metric, string $classificacao): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, severity, normalized_at
               FROM alerts
              WHERE reservoir_id = :rid AND metric = :metric
              ORDER BY detected_at DESC, id DESC
              LIMIT 1'
        );
        $stmt->execute(['rid' => $reservoirId, 'metric' => $metric]);
        $ultimo = $stmt->fetch();

        $peso = ['attention' => 1, 'critical' => 2];

        return $ultimo !== false
            && $ultimo['status'] === 'resolved'
            && $ultimo['normalized_at'] === null
            && ($peso[$ultimo['severity']] ?? 0) >= ($peso[$classificacao] ?? 0);
    }

    /** O último alerta de comunicação do equipamento foi resolvido e ele não transmitiu desde então? */
    private function paradaJaTratada(string $sensorId, ?DateTimeImmutable $ultimaLeitura): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT resolved_at
               FROM alerts
              WHERE sensor_id = :sid AND metric = :metric
              ORDER BY detected_at DESC, id DESC
              LIMIT 1'
        );
        $stmt->execute(['sid' => $sensorId, 'metric' => self::COMUNICACAO]);
        $resolvido = $stmt->fetchColumn();

        if ($resolvido === false || $resolvido === null) {
            return false;                                         // nunca teve alerta, ou o último ainda está aberto
        }
        return $ultimaLeitura === null || $ultimaLeitura <= new DateTimeImmutable((string) $resolvido, Clock::timezone());
    }

    /** Alerta ainda não resolvido da mesma grandeza na mesma represa, se houver. */
    private function alertaAberto(string $reservoirId, string $metric): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, severity, title
               FROM alerts
              WHERE reservoir_id = :rid AND metric = :metric AND status <> 'resolved'
              ORDER BY detected_at DESC
              LIMIT 1"
        );
        $stmt->execute(['rid' => $reservoirId, 'metric' => $metric]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /** Alerta de comunicação ainda não resolvido do equipamento, se houver. */
    private function alertaDeComunicacao(string $sensorId): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM alerts WHERE sensor_id = :s AND metric = :m AND status <> 'resolved' ORDER BY detected_at DESC LIMIT 1"
        );
        $stmt->execute(['s' => $sensorId, 'm' => self::COMUNICACAO]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    private function ultimaEtapa(string $alertId): string
    {
        $stmt = $this->pdo->prepare('SELECT description FROM alert_timeline WHERE alert_id = :id ORDER BY step_order DESC LIMIT 1');
        $stmt->execute(['id' => $alertId]);
        return (string) $stmt->fetchColumn();
    }

    /** Código sequencial no formato ALT-1001. */
    private function proximoId(): string
    {
        return 'ALT-' . Sequence::next($this->pdo, 'alert');
    }

    /** @param array<string,mixed> $represa cotas da represa, quando a grandeza é o nível */
    private function abrir(string $reservoirId, ?string $sensorId, string $metric, float $value, string $classificacao, array $represa, DateTimeImmutable $when): string
    {
        $id = $this->proximoId();

        $valor = $this->formatar($metric, $value);
        $limite = StatusRules::thresholdText($metric, $classificacao, $represa);

        $stmt = $this->pdo->prepare(
            'INSERT INTO alerts (id, reservoir_id, sensor_id, severity, metric, status, title, metric_label,
                                 detected_at, owner, current_value, threshold, detail, threshold_detail)
             VALUES (:id, :rid, :sid, :sev, :metric, :status, :titulo, :rotulo,
                     :quando, :owner, :valor, :limite, :detalhe, :detalhe_limite)'
        );
        $stmt->execute([
            'id'             => $id,
            'rid'            => $reservoirId,
            'sid'            => $sensorId,
            'sev'            => $classificacao,
            'metric'         => $metric,
            'status'         => 'new',
            'titulo'         => $this->titulo($metric, $classificacao),
            'rotulo'         => $metric === 'precipitation' ? 'Precipitação (24 h)' : EquipmentRules::label($metric),
            'quando'         => Clock::sql($when),
            'owner'          => self::SEM_RESPONSAVEL,
            'valor'          => $valor,
            'limite'         => $limite,
            'detalhe'        => $metric === 'precipitation' ? 'Acumulado das últimas 24 h' : ($sensorId === null ? 'Leitura da represa' : 'Leitura do equipamento ' . $sensorId),
            'detalhe_limite' => $this->detalheDoLimite($metric, $classificacao, $represa),
        ]);

        $this->etapa($id, 'Alerta aberto automaticamente: ' . $valor . ' (limite ' . $limite . ').', $when);

        return $id;
    }

    /**
     * Acrescenta uma etapa concluída ao fim da linha do tempo do alerta.
     * FOR UPDATE: se a estação e um operador mexem no mesmo alerta ao mesmo tempo,
     * um espera o outro e as duas etapas recebem números diferentes.
     */
    private function etapa(string $alertId, string $texto, DateTimeImmutable $quando): void
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(step_order), 0) + 1 FROM alert_timeline WHERE alert_id = :id FOR UPDATE');
        $stmt->execute(['id' => $alertId]);
        $ordem = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_timeline (alert_id, step_order, happened_at, description)
             VALUES (:id, :ordem, :quando, :texto)'
        );
        $stmt->execute(['id' => $alertId, 'ordem' => $ordem, 'quando' => Clock::sql($quando), 'texto' => $texto]);
    }

    private function titulo(string $metric, string $classificacao): string
    {
        $critico = $classificacao === 'critical';

        switch ($metric) {
            case 'level':
                return $critico ? 'Nível crítico do reservatório' : 'Nível do reservatório em atenção';
            case 'ph':
                return 'pH fora da faixa ideal';
            case 'precipitation':
                return $critico ? 'Chuva acumulada crítica' : 'Chuva acumulada em atenção';
            default:
                return EquipmentRules::label($metric) . ($critico ? ' em nível crítico' : ' em atenção');
        }
    }

    /** @param array<string,mixed> $represa cotas da represa, quando a grandeza é o nível */
    private function detalheDoLimite(string $metric, string $classificacao, array $represa): string
    {
        if ($metric === 'ph') {
            return 'Faixa ideal';
        }
        if ($metric === 'level') {
            return $classificacao === 'critical'
                ? 'Cota de vertimento (' . StatusRules::number((float) $represa['cota_spill_m']) . ' m)'
                : 'Cota de alerta (' . StatusRules::number((float) $represa['cota_alert_m']) . ' m)';
        }
        return $classificacao === 'critical' ? 'Limite crítico' : 'Limite de atenção';
    }

    /** Valor com unidade, no formato exibido na tela (ex.: "82,4%", "7,9", "23,5 mm"). */
    private function formatar(string $metric, float $value): string
    {
        $numero = number_format($value, $metric === 'ph' ? 2 : 1, ',', '.');
        $unidade = EquipmentRules::unit($metric);

        if ($unidade === '%') {
            return $numero . '%';
        }
        return $unidade === 'pH' || $unidade === '' ? $numero : $numero . ' ' . $unidade;
    }
}
