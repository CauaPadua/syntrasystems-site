<?php
/**
 * Situação operacional.
 *
 * GET  /api/v1/monitoring/operation.php?reservoir_id={id}
 *      Equipamentos, alertas em aberto e manutenções da represa.
 *
 * POST /api/v1/monitoring/operation.php
 *      { "reservoir_id", "equipment_id", "priority", "description" }
 *      Abre um chamado de manutenção (INSERT na tabela maintenances).
 *
 * Chamado por assets/js/pages/operation.js (monitoramento/operacional.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Services\MaintenanceService;
use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Validator;

[$repo, $user] = aq_api_boot(['GET', 'POST']);

if (Request::isMethod('POST')) {
    $pdo = Container::requireDatabase();
    $body = aq_json_body();

    $represa = $repo->reservoir(is_string($body['reservoir_id'] ?? null) ? $body['reservoir_id'] : '');
    if ($represa === null) {
        ApiResponse::error('INVALID_RESERVOIR', 'A represa informada não existe ou não está disponível.', 404);
    }

    $id = (new MaintenanceService($pdo))->open($represa['id'], $body, $repo->sensors($represa['id']), $user);

    ApiResponse::success(['id' => $id], [], 201);
}

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');    // represa obrigatória

$data = (new MonitoringService($repo))->operation($reservoirId);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados operacionais para esta represa.', 404);
}

ApiResponse::success($data, ['reservoir_id' => $reservoirId]);
