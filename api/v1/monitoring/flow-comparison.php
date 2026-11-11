<?php
/**
 * GET /api/v1/monitoring/flow-comparison.php?reservoir_id={id}&current={n}&previous={n}
 *
 * Compara a afluência média diária de duas semanas. "current" e "previous" são
 * o número da semana contado para trás: 0 = últimos 7 dias, 1 = os 7 dias
 * anteriores, e assim por diante (MonitoringService::weeks()).
 *
 * Chamado por assets/js/pages/comparison.js (monitoramento/comparativo.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');    // represa obrigatória

$semanas = array_map('strval', array_keys(MonitoringService::weeks()));   // ['0', '1', '2', '3']

$current  = Validator::option('current', $semanas, '0', 'INVALID_RANGE', 'A semana analisada não é válida.');
$previous = Validator::option('previous', $semanas, '1', 'INVALID_RANGE', 'A semana de comparação não é válida.');

if ($current === $previous) {
    ApiResponse::error('INVALID_RANGE', 'Selecione semanas diferentes para comparar.', 400);
}

$data = (new MonitoringService($repo))->flowComparison($reservoirId, (int) $current, (int) $previous);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados de vazão para esta represa.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'current'      => (int) $current,
    'previous'     => (int) $previous,
]);
