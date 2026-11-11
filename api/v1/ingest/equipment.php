<?php
/**
 * GET /api/v1/ingest/equipment.php[?equipment_id=X | ?reservoir_id=Y]
 *
 * Lista os equipamentos cadastrados para quem vai transmitir (o simulador):
 * grandeza, unidade, faixa aceita, situação, limites de alerta e o valor mais
 * recente da grandeza na represa. Exige o cabeçalho X-Api-Key.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

use Aquapulse\Services\AlertService;
use Aquapulse\Services\IngestService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

$pdo = aq_ingest_boot('GET');

$equipamento = Validator::query('equipment_id');
$represa = Validator::query('reservoir_id');

$service = new IngestService($pdo, new AlertService($pdo));

ApiResponse::success([
    'equipment' => $service->equipmentList(
        $equipamento === '' ? null : mb_strtoupper($equipamento, 'UTF-8'),
        $represa === '' ? null : $represa
    ),
]);
