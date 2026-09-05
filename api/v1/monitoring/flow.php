<?php
/** GET /api/v1/monitoring/flow.php?reservoir_id={id}&period={p} — vazão afluente e defluente */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

// análises detalhadas exigem uma represa específica (nunca 'all')
$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');
$period      = Validator::period('24h');

$service = new MonitoringService($repo);
$data    = $service->flow($reservoirId, $period);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa no período selecionado.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
