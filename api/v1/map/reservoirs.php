<?php
/**
 * GET /api/v1/map/reservoirs.php?company_id={id|all} — marcadores do mapa.
 *
 * COORDENADAS DEMONSTRATIVAS: vêm da fonte simulada e deverão ser substituídas
 * pelas coordenadas reais do banco. O navegador nunca acessa o banco direto.
 *
 * Chamado por: AqApi.mapMarkers() — usado por assets/js/maps.js nas telas Mapas
 * e Visão geral. Cada marcador traz posição (lat/lng), status e os indicadores
 * mostrados no popup do mapa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';                                   // este arquivo está em api/v1/map/, por isso sobe uma pasta

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId = Validator::companyId($repo->companies());

$markers = array_map(static function (array $r): array {                  // converte cada represa em um marcador
    $status = StatusRules::fromLevel((float) $r['level_pct']);            // define a cor do marcador

    return [
        'id'          => $r['id'],
        'name'        => $r['name'],
        'company_id'  => $r['company_id'],
        'city'        => $r['city'],
        'basin'       => $r['basin'],
        'lat'         => $r['lat'],                                       // posição no mapa
        'lng'         => $r['lng'],
        'coordinates' => $r['coordinates_label'],                         // texto em graus/minutos/segundos
        'level'       => $r['level_pct'],
        'cota'        => $r['cota_m'],
        'flow'        => $r['flow_m3s'],
        'ph'          => $r['ph'],
        'rain'        => $r['rain_24h_mm'],
        'duration'    => $r['duration_days'],
        'status'      => StatusRules::describe($status),
        'updated_at'  => '22/05/2024, ' . $r['last_reading_time'],        // a data é fixa (relógio demonstrativo); só o horário vem dos dados
    ];
}, $repo->reservoirs($companyId));

ApiResponse::success([
    'markers'          => $markers,
    'coordinates_note' => 'Coordenadas demonstrativas — substituir pelo banco de dados.',
], [
    'company_id' => $companyId,
]);
