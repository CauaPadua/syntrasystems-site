<?php
/**
 * GET /api/v1/monitoring/flow-comparison.php?reservoir_id={id}&current={range}&previous={range}
 *
 * Compara a vazão entre dois períodos. Intervalos inválidos ou iguais são
 * recusados com mensagem clara ao usuário.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');

$ranges = ['16 – 22 mai', '09 – 15 mai', '02 – 08 mai', '25 abr – 01 mai'];

$current  = Validator::option('current', $ranges, $ranges[0], 'INVALID_RANGE', 'O período atual informado não é válido.');
$previous = Validator::option('previous', $ranges, $ranges[1], 'INVALID_RANGE', 'O período de comparação informado não é válido.');

if ($current === $previous) {
    ApiResponse::error(
        'INVALID_RANGE',
        'Selecione períodos diferentes para comparar. O período atual e o anterior não podem ser iguais.',
        400
    );
}

$service = new MonitoringService($repo);
$data    = $service->flowComparison($reservoirId, $current, $previous);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados de vazão para esta represa.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'current'      => $current,
    'previous'     => $previous,
]);
