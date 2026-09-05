<?php
/** GET /api/v1/monitoring/precipitation.php?reservoir_id={id}&period={p} — precipitação observada e prevista */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

// análises detalhadas exigem uma represa específica (nunca 'all')
$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');
$period      = Validator::period('7d');

$service = new MonitoringService($repo);
$data    = $service->precipitation($reservoirId, $period);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa no período selecionado.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
