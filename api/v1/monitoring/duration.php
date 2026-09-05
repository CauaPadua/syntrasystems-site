<?php
/** GET /api/v1/monitoring/duration.php?reservoir_id={id}&horizon=90d — previsão de duração. */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\DurationForecastService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');
$horizon = Validator::option(
    'horizon',
    ['30d', '60d', '90d', '180d'],
    '90d',
    'INVALID_HORIZON',
    'O horizonte informado não é válido. Use: 30d, 60d, 90d ou 180d.'
);

$service = new DurationForecastService($repo);
$data    = $service->build($reservoirId, $horizon);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa.', 404);
}

ApiResponse::success($data, ['reservoir_id' => $reservoirId, 'horizon' => $horizon]);
