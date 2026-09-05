<?php
/** GET /api/v1/reservoirs.php?company_id={id|all} — represas da empresa. */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId = Validator::companyId($repo->companies());

$list = array_map(static function (array $r): array {
    $status = StatusRules::fromLevel((float) $r['level_pct']);
    return [
        'id'         => $r['id'],
        'code'       => $r['code'],
        'name'       => $r['name'],
        'company_id' => $r['company_id'],
        'city'       => $r['city'],
        'level'      => $r['level_pct'],
        'status'     => StatusRules::describe($status),
    ];
}, $repo->reservoirs($companyId));

ApiResponse::success(
    ['reservoirs' => $list],
    ['company_id' => $companyId]
);
