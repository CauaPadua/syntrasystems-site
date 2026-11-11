<?php
/**
 * Aquapulse — abertura e tratamento de alertas.
 *
 * Os alertas nascem das leituras: a cada medição recebida, evaluate() aplica os
 * limites de StatusRules. Se o valor saiu da faixa normal e ainda não há alerta
 * aberto para aquela grandeza na represa, um alerta é criado; se já há, ele é
 * atualizado (e agravado, quando o valor passa de atenção para crítico).
 *
 * O tratamento é feito por pessoas, pela tela de alertas: "assumir" e
 * "resolver" gravam o novo status e uma etapa na linha do tempo.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use DateTimeImmutable;
use PDO;

final class AlertService
{
    public const SEM_RESPONSAVEL = 'Não atribuído';

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
        $classificacao = StatusRules::classify($metric, $value);

        if ($classificacao === 'normal') {
            return ['classification' => 'normal', 'alert_id' => null];
        }

        $aberto = $this->alertaAberto($reservoirId, $metric);

        if ($aberto === null) {
            $id = $this->abrir($reservoirId, $sensorId, $metric, $value, $classificacao, $when);
            return ['classification' => $classificacao, 'alert_id' => $id];
        }

        // Já existe alerta em aberto: atualiza o valor e só agrava, nunca rebaixa
        // sozinho — quem encerra o alerta é o operador.
        $agravou = $classificacao === 'critical' && $aberto['severity'] !== 'critical';

        $stmt = $this->pdo->prepare(
            'UPDATE alerts
                SET current_value = :valor, severity = :sev, title = :titulo,
                    threshold = :limite, threshold_detail = :detalhe
              WHERE id = :id'
        );
        $stmt->execute([
            'valor'   => $this->formatar($metric, $value),
            'sev'     => $agravou ? 'critical' : $aberto['severity'],
            'titulo'  => $agravou ? $this->titulo($metric, 'critical') : $aberto['title'],
            'limite'  => $agravou ? StatusRules::thresholdText($metric, 'critical') : $aberto['threshold'],
            'detalhe' => $agravou ? $this->detalheDoLimite($metric, 'critical') : $aberto['threshold_detail'],
            'id'      => $aberto['id'],
        ]);

        if ($agravou) {
            $this->etapa((string) $aberto['id'], 'Agravado para crítico: ' . $this->formatar($metric, $value) . '.', $when);
        }

        return ['classification' => $classificacao, 'alert_id' => (string) $aberto['id']];
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

    /** Alerta ainda não resolvido da mesma grandeza na mesma represa, se houver. */
    private function alertaAberto(string $reservoirId, string $metric): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, severity, title, threshold, threshold_detail
               FROM alerts
              WHERE reservoir_id = :rid AND metric = :metric AND status <> 'resolved'
              ORDER BY detected_at DESC
              LIMIT 1"
        );
        $stmt->execute(['rid' => $reservoirId, 'metric' => $metric]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    private function abrir(string $reservoirId, ?string $sensorId, string $metric, float $value, string $classificacao, DateTimeImmutable $when): string
    {
        // Código sequencial no formato ALT-1001: pega o maior número já usado e soma 1.
        $numero = (int) $this->pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)), 1000) + 1 FROM alerts WHERE id LIKE 'ALT-%'"
        )->fetchColumn();
        $id = 'ALT-' . $numero;

        $valor = $this->formatar($metric, $value);
        $limite = StatusRules::thresholdText($metric, $classificacao);

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
            'detalhe_limite' => $this->detalheDoLimite($metric, $classificacao),
        ]);

        $this->etapa($id, 'Alerta aberto automaticamente: ' . $valor . ' (limite ' . $limite . ').', $when);

        return $id;
    }

    /** Acrescenta uma etapa concluída ao fim da linha do tempo do alerta. */
    private function etapa(string $alertId, string $texto, DateTimeImmutable $quando): void
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(step_order), 0) + 1 FROM alert_timeline WHERE alert_id = :id');
        $stmt->execute(['id' => $alertId]);
        $ordem = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_timeline (alert_id, step_order, happened_at, description, done)
             VALUES (:id, :ordem, :quando, :texto, 1)'
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

    private function detalheDoLimite(string $metric, string $classificacao): string
    {
        if ($metric === 'ph') {
            return 'Faixa ideal';
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
