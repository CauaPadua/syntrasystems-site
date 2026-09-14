<?php
/** GET /api/v1/monitoring/level.php?reservoir_id={id}&period={p} — nível, cota, capacidade e tendência */
/*
 * Chamado por: assets/js/pages/level.js (monitoramento/nivel.php) e
 * assets/js/pages/levels.js (dashboard/niveis.php). Período padrão: 7 dias.
 * Mesmo roteiro de flow.php: valida -> monta no service -> responde.
 */

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
$data    = $service->level($reservoirId, $period);                        // nível %, cota em metros, faixas e projeção

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa no período selecionado.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
