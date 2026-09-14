<?php
/**
 * GET /api/v1/overview.php?company_id={id|all}&reservoir_id={id|all}&period={p}
 *
 * Visão geral. Aceita 'all' na represa: nesse caso devolve os dados
 * consolidados; com uma represa específica, devolve apenas os dados dela.
 *
 * Chamado por: assets/js/pages/overview.js (dashboard/index.php).
 * Erros possíveis: 400 INVALID_PERIOD, 404 INVALID_COMPANY, 404 INVALID_RESERVOIR.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\OverviewService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

// 1. valida os filtros recebidos na URL (cada um encerra com erro se for inválido)
$companyId   = Validator::companyId($repo->companies());
$reservoirId = Validator::reservoirId($repo->reservoirs($companyId), true); // a represa precisa pertencer à empresa escolhida; true = aceita 'all'
$period      = Validator::period('7d');                                     // padrão de 7 dias quando não informado

// 2. monta os dados da tela
$service = new OverviewService($repo);                                      // o service recebe o repositório (injeção de dependência)
$data    = $service->build($companyId, $reservoirId, $period);

// 3. responde com os dados e o contexto aplicado
ApiResponse::success($data, [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
