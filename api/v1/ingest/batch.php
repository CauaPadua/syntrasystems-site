<?php
/**
 * POST /api/v1/ingest/batch.php — recebe várias leituras de uma vez.
 *
 * É o que a estação de telemetria usa: ela junta as leituras de todos os
 * sensores e as envia em um único pacote. Também serve para despejar a fila
 * que ficou guardada quando o link de comunicação caiu.
 *
 * Cabeçalhos:  Content-Type: application/json
 *              X-Api-Key: <INGEST_API_KEY de backend/.env>
 * Corpo:       { "readings": [ { "equipment_id", "recorded_at", "value" }, ... ],     (até 500)
 *                "station":  { "id": "EST-RSC-001", "started_at": "2026-11-19T13:00:00-03:00",
 *                              "equipment": ["SEN-NIV-01", "SEN-VAZ-01", ...] } }   (opcional)
 *
 * "station" diz quais equipamentos a estação lê e desde quando ela está ligada. Com
 * ele, "readings" pode vir vazio: é o aviso de que a estação está ligada, mesmo sem
 * leituras novas (todos os sensores dela parados também precisam virar alerta).
 *
 * Resposta 200 com o resultado de cada leitura, na mesma ordem do envio:
 *   { "received": 5, "stored": 4, "rejected": 1,
 *     "results": [ { "index": 0, "ok": true, "classification": "normal", "alert_id": null },
 *                  { "index": 1, "ok": false, "status": 422, "code": "VALUE_OUT_OF_RANGE", "message": "..." } ],
 *     "communication_alerts": [ { "alert_id": "ALT-1010", "equipment_id": "SEN-PH-01" } ] }
 *
 * Cada leitura é validada e gravada separadamente (Services\IngestService):
 * uma recusada não impede as demais. A estação usa o "status" de cada item para
 * decidir: 4xx é erro da leitura (descarta); 5xx é problema do servidor (tenta de novo).
 *
 * Depois de gravar, se veio "station", confere os equipamentos dela que pararam de
 * transmitir e abre o alerta "Equipamento sem leituras" (AlertService::checkCommunication).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

use Aquapulse\Http\Request;
use Aquapulse\Services\AlertService;
use Aquapulse\Services\IngestService;
use Aquapulse\Support\ApiException;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;

const LOTE_MAXIMO = 500;

$pdo = aq_ingest_boot('POST');

if (!Request::hasJsonContentType()) {
    ApiResponse::error('INVALID_CONTENT_TYPE', 'Envie os dados com Content-Type: application/json.', 400);
}

$body = Request::decodeJson(Request::rawBody());
if ($body === null) {
    ApiResponse::error('INVALID_JSON', 'Corpo da requisição não é um JSON válido.', 400);
}

// Identificação da estação (opcional): equipamentos que ela lê e quando ligou.
$estacao = null;
$informada = $body['station'] ?? null;
if (is_array($informada)) {
    $ligadaEm = is_string($informada['started_at'] ?? null) ? DateTimeImmutable::createFromFormat(DATE_ATOM, $informada['started_at']) : false;
    $codigos = array_values(array_filter((array) ($informada['equipment'] ?? []), 'is_string'));
    if ($ligadaEm !== false && $codigos !== [] && count($codigos) <= LOTE_MAXIMO) {
        $estacao = ['ligada_em' => $ligadaEm->setTimezone(Clock::timezone()), 'codigos' => array_map('strtoupper', $codigos)];
    }
}

$lista = $body['readings'] ?? null;
$minimo = $estacao === null ? 1 : 0;                       // com a estação identificada, pode ser só o aviso de "ligada"
if (!is_array($lista) || count($lista) < $minimo || count($lista) > LOTE_MAXIMO) {
    ApiResponse::error('VALIDATION_ERROR', "Envie de {$minimo} a " . LOTE_MAXIMO . ' leituras em "readings".', 422);
}

$alertas = new AlertService($pdo);
$service = new IngestService($pdo, $alertas);
$resultados = [];
$gravadas = 0;

foreach (array_values($lista) as $i => $item) {
    if (!is_array($item)) {
        $resultados[] = ['index' => $i, 'ok' => false, 'status' => 422, 'code' => 'VALIDATION_ERROR', 'message' => 'Leitura inválida.'];
        continue;
    }

    try {
        $leitura = $service->store($item);
        $gravadas++;
        $resultados[] = ['index' => $i, 'ok' => true, 'classification' => $leitura['classification'], 'alert_id' => $leitura['alert_id']];
    } catch (ApiException $e) {
        $resultados[] = ['index' => $i, 'ok' => false, 'status' => $e->status(), 'code' => $e->errorCode(), 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('[aquapulse] batch: ' . $e->getMessage());
        $resultados[] = ['index' => $i, 'ok' => false, 'status' => 500, 'code' => 'INTERNAL_ERROR', 'message' => 'Erro interno ao gravar a leitura.'];
    }
}

// Equipamentos da estação que pararam de transmitir. Uma falha aqui não invalida o pacote já gravado.
$comunicacao = [];
if ($estacao !== null) {
    try {
        $comunicacao = $alertas->checkCommunication(Clock::now(), $estacao['codigos'], $estacao['ligada_em']);
    } catch (Throwable $e) {
        error_log('[aquapulse] verificação de comunicação: ' . $e->getMessage());
    }
}

ApiResponse::success([
    'received'             => count($resultados),
    'stored'               => $gravadas,
    'rejected'             => count($resultados) - $gravadas,
    'results'              => $resultados,
    'communication_alerts' => $comunicacao,
]);
