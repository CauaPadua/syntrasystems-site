<?php
/**
 * Aquapulse — chamados de manutenção.
 *
 * "Abrir chamado" na tela Situação operacional grava uma linha em maintenances,
 * ligada à represa, ao equipamento escolhido e ao usuário que abriu.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Support\ApiException;
use PDO;

final class MaintenanceService
{
    public const PRIORITIES = ['low' => 'Baixa', 'attention' => 'Atenção', 'critical' => 'Crítica'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string,mixed>            $dados     equipment_id, priority, description
     * @param array<int,array<string,mixed>> $equipment equipamentos da represa (MonitoringRepositoryInterface::sensors)
     * @param array{id:int,name:string}      $user
     * @return int identificador do chamado criado
     */
    public function open(string $reservoirId, array $dados, array $equipment, array $user): int
    {
        $codigo = is_string($dados['equipment_id'] ?? null) ? trim($dados['equipment_id']) : '';
        $prioridade = is_string($dados['priority'] ?? null) ? $dados['priority'] : '';
        $descricao = is_string($dados['description'] ?? null) ? trim($dados['description']) : '';

        $erros = [];

        // O equipamento precisa pertencer à represa do chamado.
        $escolhido = null;
        foreach ($equipment as $e) {
            if ($e['id'] === $codigo) {
                $escolhido = $e;
                break;
            }
        }
        if ($escolhido === null) {
            $erros['equipment_id'] = 'Selecione um equipamento desta represa.';
        }
        if (!isset(self::PRIORITIES[$prioridade])) {
            $erros['priority'] = 'Selecione a prioridade.';
        }
        if (mb_strlen($descricao, 'UTF-8') < 5 || mb_strlen($descricao, 'UTF-8') > 255) {
            $erros['description'] = 'Descreva o problema (5 a 255 caracteres).';
        }

        if ($erros !== []) {
            throw new ApiException('VALIDATION_ERROR', 'Verifique os campos informados.', 422, $erros);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO maintenances (reservoir_id, scheduled_date, equipment, type, priority, status, description, requested_by)
             VALUES (:rid, CURDATE(), :equipamento, 'Chamado', :prioridade, 'scheduled', :descricao, :usuario)"
        );
        $stmt->execute([
            'rid'         => $reservoirId,
            'equipamento' => $escolhido['id'] . ' — ' . $escolhido['name'],
            'prioridade'  => $prioridade,
            'descricao'   => $descricao,
            'usuario'     => $user['id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
