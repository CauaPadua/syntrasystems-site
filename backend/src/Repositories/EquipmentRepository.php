<?php
/**
 * Aquapulse — cadastro de equipamentos (CRUD da tabela sensors).
 *
 *   CREATE  create()   INSERT INTO sensors ...
 *   READ    all() / find()   SELECT na view vw_ultimas_medicoes
 *   UPDATE  update()   UPDATE sensors SET ... WHERE id = ...
 *   DELETE  delete()   DELETE FROM sensors WHERE id = ...
 *
 * As regras de preenchimento ficam em validar(); o banco reforça as mesmas
 * regras com chave primária, chave estrangeira e CHECK.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Services\EquipmentRules;
use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use PDO;

final class EquipmentRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ---------------------------------------------------------------- READ */

    /**
     * Equipamentos com a última leitura de cada um.
     *
     * @param array<int,string> $reservoirIds represas que o usuário pode ver
     * @param string            $reservoirId  filtro opcional ('all' = todas)
     * @return array<int,array<string,mixed>>
     */
    public function all(array $reservoirIds, string $reservoirId = 'all'): array
    {
        if ($reservoirIds === []) {
            return [];
        }

        $nomes = [];
        $params = [];
        foreach (array_values($reservoirIds) as $i => $id) {
            $nomes[] = ':r' . $i;
            $params['r' . $i] = $id;
        }

        $sql = 'SELECT sensor_id, sensor_name, metric, location, status, reservoir_id, reservoir_name,
                       last_value, last_reading_at
                  FROM vw_ultimas_medicoes
                 WHERE reservoir_id IN (' . implode(', ', $nomes) . ')';

        if ($reservoirId !== 'all') {
            $sql .= ' AND reservoir_id = :filtro';
            $params['filtro'] = $reservoirId;
        }

        $sql .= ' ORDER BY reservoir_name, metric, sensor_id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'formatar'], $stmt->fetchAll());
    }

    /** Um equipamento pelo código, ou null. */
    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sensor_id, sensor_name, metric, location, status, reservoir_id, reservoir_name,
                    last_value, last_reading_at
               FROM vw_ultimas_medicoes
              WHERE sensor_id = :id'
        );
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $this->formatar($linha);
    }

    /* -------------------------------------------------------------- CREATE */

    /**
     * @param array<string,mixed> $dados id, name, location, type, status, reservoir_id
     * @param array<int,string>   $reservoirIds represas em que o usuário pode cadastrar
     */
    public function create(array $dados, array $reservoirIds): array
    {
        $campos = $this->validar($dados, $reservoirIds, true);

        if ($this->find($campos['id']) !== null) {
            throw new ApiException('VALIDATION_ERROR', 'Verifique os campos informados.', 422, [
                'id' => 'Já existe um equipamento com este código.',
            ]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO sensors (id, reservoir_id, name, location, type, status)
             VALUES (:id, :reservoir_id, :name, :location, :type, :status)'
        );
        $stmt->execute($campos);

        return $this->find($campos['id']);
    }

    /* -------------------------------------------------------------- UPDATE */

    /**
     * Altera nome, local, grandeza, represa e situação. O código não muda: é a
     * identificação que o equipamento usa ao enviar leituras.
     *
     * @param array<string,mixed> $dados
     * @param array<int,string>   $reservoirIds
     */
    public function update(string $id, array $dados, array $reservoirIds): array
    {
        $atual = $this->find($id);
        if ($atual === null || !in_array($atual['reservoir_id'], $reservoirIds, true)) {
            throw new ApiException('EQUIPMENT_NOT_FOUND', 'Equipamento não encontrado.', 404);
        }

        $campos = $this->validar(['id' => $id] + $dados, $reservoirIds, false);

        $stmt = $this->pdo->prepare(
            'UPDATE sensors
                SET reservoir_id = :reservoir_id, name = :name, location = :location,
                    type = :type, status = :status
              WHERE id = :id'
        );
        $stmt->execute($campos);

        return $this->find($id);
    }

    /* -------------------------------------------------------------- DELETE */

    /**
     * Exclui o equipamento. As leituras que ele enviou continuam no histórico
     * da represa, sem equipamento associado (a chave estrangeira de
     * readings.sensor_id é ON DELETE SET NULL).
     *
     * @param array<int,string> $reservoirIds
     */
    public function delete(string $id, array $reservoirIds): void
    {
        $atual = $this->find($id);
        if ($atual === null || !in_array($atual['reservoir_id'], $reservoirIds, true)) {
            throw new ApiException('EQUIPMENT_NOT_FOUND', 'Equipamento não encontrado.', 404);
        }

        $stmt = $this->pdo->prepare('DELETE FROM sensors WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /* ----------------------------------------------------------- internos */

    /**
     * Confere os campos e devolve os valores prontos para o SQL.
     *
     * @param array<string,mixed> $dados
     * @param array<int,string>   $reservoirIds
     * @return array{id:string,reservoir_id:string,name:string,location:string,type:string,status:string}
     */
    private function validar(array $dados, array $reservoirIds, bool $novo): array
    {
        $texto = static fn (string $chave): string => is_string($dados[$chave] ?? null) ? trim($dados[$chave]) : '';

        $campos = [
            'id'           => mb_strtoupper($texto('id'), 'UTF-8'),
            'reservoir_id' => $texto('reservoir_id'),
            'name'         => $texto('name'),
            'location'     => $texto('location'),
            'type'         => $texto('type'),
            'status'       => $texto('status') === '' ? 'active' : $texto('status'),
        ];

        $erros = [];

        if ($novo && !preg_match('/^[A-Z0-9][A-Z0-9-]{2,29}$/', $campos['id'])) {
            $erros['id'] = 'Use de 3 a 30 caracteres: letras, números e hífen (ex.: SEN-NIV-04).';
        }
        if (mb_strlen($campos['name'], 'UTF-8') < 3 || mb_strlen($campos['name'], 'UTF-8') > 120) {
            $erros['name'] = 'Informe um nome com 3 a 120 caracteres.';
        }
        if (mb_strlen($campos['location'], 'UTF-8') < 3 || mb_strlen($campos['location'], 'UTF-8') > 120) {
            $erros['location'] = 'Informe o local de instalação (3 a 120 caracteres).';
        }
        if (!EquipmentRules::isType($campos['type'])) {
            $erros['type'] = 'Selecione a grandeza medida.';
        }
        if (!isset(EquipmentRules::STATUS[$campos['status']])) {
            $erros['status'] = 'Selecione a situação do equipamento.';
        }
        if (!in_array($campos['reservoir_id'], $reservoirIds, true)) {
            $erros['reservoir_id'] = 'Selecione uma represa.';
        }

        if ($erros !== []) {
            throw new ApiException('VALIDATION_ERROR', 'Verifique os campos informados.', 422, $erros);
        }

        return $campos;
    }

    /**
     * Linha da view -> formato da API, com a situação de comunicação calculada.
     *
     * @param array<string,mixed> $l
     */
    private function formatar(array $l): array
    {
        $ultima = $l['last_reading_at'] === null ? null : Clock::parse((string) $l['last_reading_at']);
        $comunicacao = EquipmentRules::communication((string) $l['status'], $ultima, Clock::now());
        $tipo = (string) $l['metric'];

        return [
            'id'                  => (string) $l['sensor_id'],
            'name'                => (string) $l['sensor_name'],
            'location'            => (string) $l['location'],
            'type'                => $tipo,
            'type_label'          => EquipmentRules::label($tipo),
            'unit'                => EquipmentRules::unit($tipo),
            'status'              => (string) $l['status'],
            'status_label'        => EquipmentRules::STATUS[(string) $l['status']] ?? (string) $l['status'],
            'communication'       => $comunicacao,
            'communication_label' => EquipmentRules::communicationLabel($comunicacao),
            'reservoir_id'        => (string) $l['reservoir_id'],
            'reservoir_name'      => (string) $l['reservoir_name'],
            'last_value'          => $l['last_value'] === null ? null : (float) $l['last_value'],
            'last_reading_at'     => $ultima === null ? null : Clock::iso($ultima),
            'last_reading_time'   => $ultima === null ? null : $ultima->format('d/m/Y H:i:s'),
            'last_reading_label'  => $ultima === null ? 'Nenhuma leitura' : Clock::relative($ultima),
        ];
    }
}
