<?php
/**
 * Aquapulse — limites de pH e de chuva (tabela operational_limits).
 *
 * Valem para todas as represas e são mudados pelo administrador em
 * Configurações › Limites (api/v1/limits.php). A mudança vale para a próxima
 * leitura e para a próxima tela aberta; alertas já abertos continuam abertos.
 *
 * Os limites do nível não ficam aqui: são as cotas de cada represa, mudadas no
 * cadastro dela (RegistryRepository).
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Support\Fields;
use PDO;
use Throwable;

final class LimitRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Grava os quatro limites de uma vez: ou todos entram, ou nenhum.
     *
     * @param array<string,mixed> $dados ph_min, ph_max, rain_attention, rain_critical
     */
    public function update(array $dados): void
    {
        $f = new Fields($dados);
        $phMin = $f->number('ph_min', 0, 14, 'Use um valor de 0 a 14.');
        $phMax = $f->number('ph_max', 0, 14, 'Use um valor de 0 a 14.');
        $atencao = $f->number('rain_attention', 0.1, 1000, 'Use um valor de 0,1 a 1000 mm.');
        $critico = $f->number('rain_critical', 0.1, 1000, 'Use um valor de 0,1 a 1000 mm.');

        // Regras entre campos: o mínimo vem antes do máximo e a atenção vem antes do crítico.
        if (!$f->hasError('ph_min', 'ph_max') && $phMax <= $phMin) {
            $f->error('ph_max', 'Precisa ser maior que o pH mínimo.');
        }
        if (!$f->hasError('rain_attention', 'rain_critical') && $critico <= $atencao) {
            $f->error('rain_critical', 'Precisa ser maior que o limite de atenção.');
        }
        $f->check();

        $stmt = $this->pdo->prepare(
            'INSERT INTO operational_limits (metric, classification, min_value, max_value, unit)
             VALUES (:metric, :classification, :min, :max, :unit)
             ON DUPLICATE KEY UPDATE min_value = VALUES(min_value), max_value = VALUES(max_value)'
        );

        $propria = !$this->pdo->inTransaction();              // os testes chamam dentro da transação deles
        if ($propria) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt->execute(['metric' => 'ph', 'classification' => 'normal', 'min' => $phMin, 'max' => $phMax, 'unit' => 'pH']);
            $stmt->execute(['metric' => 'precipitation', 'classification' => 'attention', 'min' => $atencao, 'max' => $critico, 'unit' => 'mm']);
            $stmt->execute(['metric' => 'precipitation', 'classification' => 'critical', 'min' => $critico, 'max' => null, 'unit' => 'mm']);
            if ($propria) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($propria && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
