<?php
/** GET /api/v1/monitoring/storage.php?reservoir_id={id}&period={p} — volume armazenado e balanço hídrico */
/*
 * Chamado por: assets/js/pages/storage.js (monitoramento/volume.php). Período padrão: 30 dias.
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
$period      = Validator::period('30d');                                  // volume muda devagar: o padrão é um período mais longo

$service = new MonitoringService($repo);
$data    = $service->storage($reservoirId, $period);                      // volume, capacidade, ocupação e balanço hídrico

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa no período selecionado.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
