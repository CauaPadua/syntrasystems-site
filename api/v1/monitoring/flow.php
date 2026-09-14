<?php
/** GET /api/v1/monitoring/flow.php?reservoir_id={id}&period={p} — vazão afluente e defluente */
/*
 * Chamado por: assets/js/pages/flow.js (dashboard/monitoramento/vazao.php).
 * Entrada: reservoir_id OBRIGATÓRIO e period (padrão 24h).
 * Erros: 400 RESERVOIR_REQUIRED / INVALID_PERIOD, 404 INVALID_RESERVOIR / NO_DATA.
 * Os demais endpoints desta pasta seguem exatamente o mesmo roteiro.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';                                   // exige GET e sessão

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

// análises detalhadas exigem uma represa específica (nunca 'all')
$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');    // false = 'all' não é aceito; '' = sem valor padrão (obriga o cliente a informar)
$period      = Validator::period('24h');

$service = new MonitoringService($repo);
$data    = $service->flow($reservoirId, $period);                         // monta os dados da tela de vazão

if ($data === []) {                                                       // o service devolve [] quando não encontra a represa
    ApiResponse::error('NO_DATA', 'Não há dados disponíveis para esta represa no período selecionado.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'period'       => $period,
]);
