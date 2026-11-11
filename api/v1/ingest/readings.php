<?php
/**
 * POST /api/v1/ingest/readings.php — recebe uma leitura de um equipamento.
 *
 * Cabeçalhos:  Content-Type: application/json
 *              X-Api-Key: <INGEST_API_KEY de backend/.env>
 * Corpo:       { "equipment_id": "SEN-NIV-01",
 *                "recorded_at":  "2026-11-10T14:03:05-03:00",
 *                "value":        82.4 }
 *
 * Respostas:
 *   201  leitura gravada (com a classificação e o alerta aberto, se houver)
 *   400  corpo que não é JSON
 *   401  chave de API inválida
 *   404  equipamento não cadastrado
 *   409  equipamento desativado/em manutenção, ou leitura repetida
 *   422  campo ausente, horário inválido ou valor fora da faixa física
 *
 * A validação e a gravação ficam em Services\IngestService.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

use Aquapulse\Http\Request;
use Aquapulse\Services\AlertService;
use Aquapulse\Services\IngestService;
use Aquapulse\Support\ApiResponse;

$pdo = aq_ingest_boot('POST');

if (!Request::hasJsonContentType()) {
    ApiResponse::error('INVALID_CONTENT_TYPE', 'Envie os dados com Content-Type: application/json.', 400);
}

$body = Request::decodeJson(Request::rawBody());
if ($body === null) {
    ApiResponse::error('INVALID_JSON', 'Corpo da requisição não é um JSON válido.', 400);
}

$service = new IngestService($pdo, new AlertService($pdo));
$leitura = $service->store($body);                           // lança ApiException se algo estiver errado

ApiResponse::success(['reading' => $leitura], [], 201);
