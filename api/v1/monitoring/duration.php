<?php
/** GET /api/v1/monitoring/duration.php?reservoir_id={id}&horizon=90d — previsão de duração. */
/*
 * Chamado por: assets/js/pages/duration.js (monitoramento/duracao.php).
 * Diferente das outras telas, não usa "period" (passado), e sim "horizon":
 * quantos dias para FRENTE a projeção deve mostrar.
 * Erros: 400 RESERVOIR_REQUIRED / INVALID_HORIZON, 404 INVALID_RESERVOIR / NO_DATA.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\DurationForecastService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');    // represa obrigatória
$horizon = Validator::option(                                             // horizonte validado por allowlist própria desta tela
    'horizon',
    ['30d', '60d', '90d', '180d'],                                        // valores aceitos
    '90d',                                                                // padrão
    'INVALID_HORIZON',
    'O horizonte informado não é válido. Use: 30d, 60d, 90d ou 180d.'
);

$service = new DurationForecastService($repo);                            // service separado: algoritmo de previsão isolado
$data    = $service->build($reservoirId, $horizon);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa.', 404);
}

ApiResponse::success($data, ['reservoir_id' => $reservoirId, 'horizon' => $horizon]);
