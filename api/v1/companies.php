<?php
/** GET /api/v1/companies.php — lista as empresas disponíveis. */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Support\ApiResponse;

[$repo] = aq_api_boot();

ApiResponse::success(
    ['companies' => $repo->companies()],
    ['company_id' => 'all']
);
