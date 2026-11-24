<?php
/**
 * Contas de acesso — só para o administrador.
 *
 * POST /api/v1/users.php  { "action": "create", "name", "email", "role", "status", "password", "reservoirs": [...] }
 * POST /api/v1/users.php  { "action": "update", "id", ...os mesmos campos; senha em branco mantém a atual }
 *
 * A lista de contas vem em GET api/v1/settings.php. Chamado por
 * assets/js/pages/settings.js (dashboard/configuracoes.php). O SQL fica em
 * Repositories\AccountRepository.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Repositories\AccountRepository;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

[, $user] = aq_api_boot(['POST']);
Guard::requireCan($user, 'registry.edit');

$contas = new AccountRepository(Container::requireDatabase());
$body = aq_json_body();

switch (Request::stringField($body, 'action')) {
    case 'create':
        ApiResponse::success(['id' => $contas->create($body)], [], 201);
        break;

    case 'update':
        $id = is_numeric($body['id'] ?? null) ? (int) $body['id'] : 0;
        $contas->update($id, $body, (int) $user['id']);
        ApiResponse::success(['id' => $id]);
        break;

    default:
        ApiResponse::error('INVALID_ACTION', 'Ação inválida. Use "create" ou "update".', 400);
}
