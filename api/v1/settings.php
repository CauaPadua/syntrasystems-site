<?php
/** GET /api/v1/settings.php — configurações, empresas, represas e limites. */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Support\ApiResponse;

[$repo] = aq_api_boot();

$companies = array_map(static function (array $c) use ($repo): array {
    $c['reservoirs'] = array_map(static fn (array $r): array => [
        'id'       => $r['id'],
        'name'     => $r['name'],
        'city'     => $r['city'],
        'capacity' => $r['capacity_hm3'],
        'telemetry'=> 'Online',
        'status'   => 'Operacional',
    ], $repo->reservoirs($c['id']));
    return $c;
}, $repo->companies());

ApiResponse::success([
    'companies' => $companies,
    'settings'  => $repo->settings(),
]);
