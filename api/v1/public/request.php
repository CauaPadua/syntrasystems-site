<?php
/**
 * POST /api/v1/public/request.php — recebe um pedido de quem ainda não tem conta.
 *
 * É o único endpoint de gravação sem sessão. Chamado por assets/js/solicitar.js
 * (página solicitar.php). As regras ficam em Repositories\RequestRepository.
 *
 * Entrada (JSON):
 *   { "type": "access", name, email, registration, company_name, reservoir_name,
 *     manager_name, message, password, password_confirm }
 *   { "type": "demo", name, email, phone, company_name, reservoir_name, city, message }
 *
 * Respostas: 201 { id } | 409 REQUEST_PENDING | 422 VALIDATION_ERROR | 429 TOO_MANY_REQUESTS
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Repositories\RequestRepository;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;

if (!Request::isMethod('POST')) {
    ApiResponse::methodNotAllowed(['POST']);
}

$body = aq_json_body();                                      // exige JSON: um formulário de outro site não consegue enviar
$type = Request::stringField($body, 'type');

if (!isset(RequestRepository::TYPES[$type])) {
    ApiResponse::error('INVALID_TYPE', 'Tipo de pedido inválido.', 400);
}

// Campo que fica fora da tela: uma pessoa não o vê, um robô costuma preencher.
// O robô recebe "recebido" e nada é gravado.
if (Request::stringField($body, 'website') !== '') {
    ApiResponse::success(['id' => null], [], 201);
}

$id = (new RequestRepository(Container::requireDatabase()))->create($type, $body);

ApiResponse::success(['id' => $id], [], 201);
