<?php
/**
 * GET /api/v1/overview.php?company_id={id|all}&reservoir_id={id|all}&period={p}
 *
 * Visão geral. Aceita 'all' na represa: nesse caso devolve os dados
 * consolidados; com uma represa específica, devolve apenas os dados dela.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\OverviewService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId   = Validator::companyId($repo->companies());
$reservoirId = Validator::reservoirId($repo->reservoirs($companyId), true);
$period      = Validator::period('7d');

$service = new OverviewService($repo);
$data    = $service->build($companyId, $reservoirId, $period);

ApiResponse::success($data, [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
