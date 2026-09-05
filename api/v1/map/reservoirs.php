<?php
/**
 * GET /api/v1/map/reservoirs.php?company_id={id|all} — marcadores do mapa.
 *
 * COORDENADAS DEMONSTRATIVAS: vêm da fonte simulada e deverão ser substituídas
 * pelas coordenadas reais do banco. O navegador nunca acessa o banco direto.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId = Validator::companyId($repo->companies());

$markers = array_map(static function (array $r): array {
    $status = StatusRules::fromLevel((float) $r['level_pct']);

    return [
        'id'          => $r['id'],
        'name'        => $r['name'],
        'company_id'  => $r['company_id'],
        'city'        => $r['city'],
        'basin'       => $r['basin'],
        'lat'         => $r['lat'],
        'lng'         => $r['lng'],
        'coordinates' => $r['coordinates_label'],
        'level'       => $r['level_pct'],
        'cota'        => $r['cota_m'],
        'flow'        => $r['flow_m3s'],
        'ph'          => $r['ph'],
        'rain'        => $r['rain_24h_mm'],
        'duration'    => $r['duration_days'],
        'status'      => StatusRules::describe($status),
        'updated_at'  => '22/05/2024, ' . $r['last_reading_time'],
    ];
}, $repo->reservoirs($companyId));

ApiResponse::success([
    'markers'          => $markers,
    'coordinates_note' => 'Coordenadas demonstrativas — substituir pelo banco de dados.',
], [
    'company_id' => $companyId,
]);
