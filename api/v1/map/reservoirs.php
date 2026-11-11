<?php
/**
 * GET /api/v1/map/reservoirs.php?company_id={id|all} — marcadores do mapa.
 *
 * Cada marcador traz a posição, o status e os indicadores mostrados no popup.
 * As coordenadas vêm do cadastro das represas (no seed, são fictícias).
 *
 * Chamado por AqApi.mapMarkers() — telas Mapas e Visão geral.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\ReservoirCurve;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId = Validator::companyId($repo->companies());

$markers = array_map(static function (array $r): array {
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
        'flow'        => $r['inflow_m3s'],
        'ph'          => $r['ph'],
        'rain'        => $r['rain_24h_mm'],
        'duration'    => ReservoirCurve::durationDays($r),
        'status'      => StatusRules::describe(StatusRules::fromLevel((float) $r['level_pct'])),
        'updated_at'  => $r['last_reading_at'] === null ? 'Sem leituras' : Clock::dateTime(Clock::parse($r['last_reading_at'])),
    ];
}, $repo->reservoirs($companyId));

ApiResponse::success(['markers' => $markers], ['company_id' => $companyId]);
