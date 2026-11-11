<?php
/**
 * Aquapulse — recebimento das leituras enviadas pelos equipamentos.
 *
 * Fluxo: equipamento (ou simulador) -> api/v1/ingest/readings.php -> store()
 *        -> tabela readings -> painel.
 *
 * Cada envio traz o código do equipamento, o instante da medição e o valor:
 *   { "equipment_id": "SEN-NIV-01", "recorded_at": "2026-11-10T14:03:05-03:00", "value": 82.4 }
 *
 * A grandeza e a represa não vêm no envio: são as do cadastro do equipamento.
 * Assim um equipamento não consegue gravar leitura de outra represa nem de uma
 * grandeza que ele não mede.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use DateTimeImmutable;
use PDO;
use PDOException;

final class IngestService
{
    /** Tolerância para relógio adiantado no equipamento (segundos). */
    private const FUTURO_TOLERADO = 120;

    /** Leituras mais antigas que isto são recusadas (dias). */
    private const ATRASO_MAXIMO = 7;

    private PDO $pdo;
    private AlertService $alerts;

    public function __construct(PDO $pdo, AlertService $alerts)
    {
        $this->pdo = $pdo;
        $this->alerts = $alerts;
    }

    /**
     * Valida e grava uma leitura.
     *
     * @param array<string,mixed> $payload corpo JSON da requisição, já decodificado
     * @return array<string,mixed> resumo do que foi gravado
     * @throws ApiException quando alguma validação falha
     */
    public function store(array $payload): array
    {
        /* ------------------------------------------- 1. equipamento */
        $codigo = $payload['equipment_id'] ?? null;
        if (!is_string($codigo) || trim($codigo) === '') {
            throw new ApiException('VALIDATION_ERROR', 'Informe o código do equipamento em "equipment_id".', 422);
        }

        $equipamento = $this->equipamento(mb_strtoupper(trim($codigo), 'UTF-8'));

        if ($equipamento['status'] !== 'active') {
            throw new ApiException(
                'EQUIPMENT_NOT_ACTIVE',
                'O equipamento está ' . mb_strtolower(EquipmentRules::STATUS[$equipamento['status']], 'UTF-8') . ' e não pode enviar leituras.',
                409
            );
        }

        /* ----------------------------------------------- 2. horário */
        $quando = $this->instante($payload['recorded_at'] ?? null);

        /* ------------------------------------------------- 3. valor */
        $valor = $payload['value'] ?? null;
        if (!is_int($valor) && !is_float($valor)) {
            throw new ApiException('VALIDATION_ERROR', 'Informe a medição em "value", como número.', 422);
        }
        $valor = (float) $valor;

        $grandeza = (string) $equipamento['type'];
        $regra = EquipmentRules::TYPES[$grandeza];

        if (!EquipmentRules::inRange($grandeza, $valor)) {
            throw new ApiException(
                'VALUE_OUT_OF_RANGE',
                sprintf('Valor fora da faixa aceita para "%s" (de %s a %s %s).', $regra['label'],
                    StatusRules::number($regra['min']), StatusRules::number($regra['max']), $regra['unit']),
                422
            );
        }
        $valor = round($valor, $regra['decimals']);

        /* ----------------------------------------------- 4. gravação */
        $represa = (string) $equipamento['reservoir_id'];
        $sensor = (string) $equipamento['id'];

        // Leitura, cota/volume e alerta entram juntos ou nada entra. Se quem chama
        // já abriu uma transação (ex.: os testes), ela é reaproveitada.
        $propria = !$this->pdo->inTransaction();
        if ($propria) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->inserir($sensor, $represa, $grandeza, $valor, $quando);

            // O equipamento de nível mede o percentual; cota e volume são
            // calculados pelo cadastro da represa e gravados no mesmo instante.
            if ($grandeza === 'level') {
                $this->inserir($sensor, $represa, 'cota', ReservoirCurve::cota($equipamento, $valor), $quando);
                $this->inserir($sensor, $represa, 'storage', ReservoirCurve::storage($equipamento, $valor), $quando);
            }

            // A chuva é avaliada pelo acumulado de 24 h, não pela leitura isolada.
            $avaliado = $grandeza === 'precipitation' ? $this->chuva24h($represa, $quando) : $valor;
            $alerta = $this->alerts->evaluate($represa, $sensor, $grandeza, $avaliado, $quando);

            if ($propria) {
                $this->pdo->commit();
            }
        } catch (PDOException $e) {
            if ($propria) {
                $this->pdo->rollBack();
            }

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {     // 1062 = violação de chave única
                throw new ApiException('DUPLICATE_READING', 'Este equipamento já enviou uma leitura para este mesmo instante.', 409);
            }
            throw $e;
        }

        return [
            'equipment_id'   => $sensor,
            'reservoir_id'   => $represa,
            'metric'         => $grandeza,
            'value'          => $valor,
            'unit'           => $regra['unit'],
            'recorded_at'    => Clock::iso($quando),
            'classification' => $alerta['classification'],
            'alert_id'       => $alerta['alert_id'],
        ];
    }

    /**
     * Equipamentos que podem transmitir, com o valor mais recente da grandeza
     * na represa. O simulador usa esta lista para saber o que simular e de que
     * valor partir, mantendo a continuidade com o histórico.
     *
     * @return array<int,array<string,mixed>>
     */
    public function equipmentList(?string $equipmentId = null, ?string $reservoirId = null): array
    {
        $sql = "SELECT s.id, s.name, s.type, s.status, s.reservoir_id, r.name AS reservoir_name,
                       (SELECT l.value
                          FROM readings l
                         WHERE l.reservoir_id = s.reservoir_id AND l.metric = s.type
                         ORDER BY l.recorded_at DESC
                         LIMIT 1) AS current_value
                  FROM sensors s
                  JOIN reservoirs r ON r.id = s.reservoir_id
                 WHERE 1 = 1";
        $params = [];

        if ($equipmentId !== null) {
            $sql .= ' AND s.id = :id';
            $params['id'] = $equipmentId;
        }
        if ($reservoirId !== null) {
            $sql .= ' AND s.reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }

        $sql .= ' ORDER BY s.reservoir_id, s.type, s.id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $limites = StatusRules::limits();
        $lista = [];

        foreach ($stmt->fetchAll() as $l) {
            $tipo = (string) $l['type'];
            $regra = EquipmentRules::TYPES[$tipo];

            $lista[] = [
                'id'             => (string) $l['id'],
                'name'           => (string) $l['name'],
                'type'           => $tipo,
                'type_label'     => $regra['label'],
                'unit'           => $regra['unit'],
                'min'            => $regra['min'],
                'max'            => $regra['max'],
                'decimals'       => $regra['decimals'],
                'status'         => (string) $l['status'],
                'reservoir_id'   => (string) $l['reservoir_id'],
                'reservoir_name' => (string) $l['reservoir_name'],
                'current_value'  => $l['current_value'] === null ? null : (float) $l['current_value'],
                'limits'         => $limites[$tipo] ?? [],
            ];
        }
        return $lista;
    }

    /* ---------------------------------------------------------- internos */

    /** Cadastro do equipamento com os dados da represa usados nos cálculos. */
    private function equipamento(string $codigo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.type, s.status, s.reservoir_id, r.capacity_hm3, r.cota_min_m, r.cota_max_m
               FROM sensors s
               JOIN reservoirs r ON r.id = s.reservoir_id
              WHERE s.id = :id'
        );
        $stmt->execute(['id' => $codigo]);
        $linha = $stmt->fetch();

        if ($linha === false) {
            throw new ApiException('EQUIPMENT_NOT_FOUND', 'Equipamento não cadastrado.', 404);
        }
        return $linha;
    }

    /**
     * Confere o instante informado: precisa estar em ISO 8601 com fuso, não
     * pode estar no futuro nem ser antigo demais.
     *
     * @param mixed $texto
     */
    private function instante($texto): DateTimeImmutable
    {
        $quando = is_string($texto) ? DateTimeImmutable::createFromFormat(DATE_ATOM, $texto) : false;

        if ($quando === false) {
            throw new ApiException(
                'VALIDATION_ERROR',
                'Informe o horário da medição em "recorded_at", no formato 2026-11-10T14:03:05-03:00.',
                422
            );
        }

        $quando = $quando->setTimezone(Clock::timezone());
        $agora = Clock::now();

        if ($quando->getTimestamp() > $agora->getTimestamp() + self::FUTURO_TOLERADO) {
            throw new ApiException('INVALID_TIMESTAMP', 'O horário da medição está no futuro.', 422);
        }
        if ($quando < $agora->modify('-' . self::ATRASO_MAXIMO . ' days')) {
            throw new ApiException('INVALID_TIMESTAMP', 'A medição é antiga demais para ser registrada.', 422);
        }

        return $quando;
    }

    private function inserir(string $sensor, string $represa, string $grandeza, float $valor, DateTimeImmutable $quando): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO readings (sensor_id, reservoir_id, metric, value, recorded_at)
             VALUES (:sensor, :represa, :grandeza, :valor, :quando)'
        );
        $stmt->execute([
            'sensor'   => $sensor,
            'represa'  => $represa,
            'grandeza' => $grandeza,
            'valor'    => $valor,
            'quando'   => Clock::sql($quando),
        ]);
    }

    /** Chuva acumulada na represa nas 24 h que terminam no instante informado. */
    private function chuva24h(string $represa, DateTimeImmutable $ate): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(value), 0)
               FROM readings
              WHERE reservoir_id = :rid AND metric = 'precipitation'
                AND recorded_at > :inicio AND recorded_at <= :fim"
        );
        $stmt->execute([
            'rid'    => $represa,
            'inicio' => Clock::sql($ate->modify('-24 hours')),
            'fim'    => Clock::sql($ate),
        ]);

        return round((float) $stmt->fetchColumn(), 1);
    }
}
