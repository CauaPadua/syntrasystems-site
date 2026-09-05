<?php
/** GET /api/v1/monitoring/operation.php?reservoir_id={id} — situação operacional. */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');

$service = new MonitoringService($repo);
$data    = $service->operation($reservoirId);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados operacionais para esta represa.', 404);
}

ApiResponse::success($data, ['reservoir_id' => $reservoirId]);
