<?php
/**
 * GET /api/v1/monitoring/flow-comparison.php?reservoir_id={id}&current={range}&previous={range}
 *
 * Compara a vazão entre dois períodos. Intervalos inválidos ou iguais são
 * recusados com mensagem clara ao usuário.
 *
 * Chamado por: assets/js/pages/comparison.js (monitoramento/comparativo.php).
 * Erros: 400 RESERVOIR_REQUIRED / INVALID_RANGE, 404 INVALID_RESERVOIR / NO_DATA.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

use Aquapulse\Services\MonitoringService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$reservoirId = Validator::reservoirId($repo->reservoirs(), false, '');    // represa obrigatória

$ranges = ['16 – 22 mai', '09 – 15 mai', '02 – 08 mai', '25 abr – 01 mai']; // semanas selecionáveis na tela (a allowlist são os próprios textos)

$current  = Validator::option('current', $ranges, $ranges[0], 'INVALID_RANGE', 'O período atual informado não é válido.');          // padrão: semana mais recente
$previous = Validator::option('previous', $ranges, $ranges[1], 'INVALID_RANGE', 'O período de comparação informado não é válido.'); // padrão: semana anterior

if ($current === $previous) {                                              // regra de negócio: comparar um período com ele mesmo não faz sentido
    ApiResponse::error(
        'INVALID_RANGE',
        'Selecione períodos diferentes para comparar. O período atual e o anterior não podem ser iguais.',
        400
    );
}

$service = new MonitoringService($repo);
$data    = $service->flowComparison($reservoirId, $current, $previous);

if ($data === []) {
    ApiResponse::error('NO_DATA', 'Não há dados de vazão para esta represa.', 404);
}

ApiResponse::success($data, [
    'reservoir_id' => $reservoirId,
    'current'      => $current,
    'previous'     => $previous,
]);
