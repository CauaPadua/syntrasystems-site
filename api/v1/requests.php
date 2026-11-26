<?php
/**
 * Pedidos de acesso e de demonstração — só para o administrador.
 *
 * GET  /api/v1/requests.php?type={all|access|demo}&status={all|pending|approved|rejected}
 * POST /api/v1/requests.php  { "action": "approve",  "id", "role", "reservoirs": [...] }   cria a conta (acesso ou demonstração)
 * POST /api/v1/requests.php  { "action": "conclude", "id", "note" }                        demonstração antiga, sem senha
 * POST /api/v1/requests.php  { "action": "reject",   "id", "note" }                        recusa com motivo
 * POST /api/v1/requests.php  { "action": "delete",   "id" }                                apaga um já respondido
 *
 * Chamado por assets/js/pages/requests.js (dashboard/solicitacoes.php). O SQL
 * fica em Repositories\RequestRepository.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Repositories\RequestRepository;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;
use Aquapulse\Support\Validator;

[$repo, $user] = aq_api_boot(['GET', 'POST']);
Guard::requireCan($user, 'requests.manage');

$pedidos = new RequestRepository(Container::requireDatabase());

/* ----------------------------------------------------- POST: responder pedido */

if (Request::isMethod('POST')) {
    $body = aq_json_body();
    $id = is_numeric($body['id'] ?? null) ? (int) $body['id'] : 0;
    $adminId = (int) $user['id'];

    switch (Request::stringField($body, 'action')) {
        case 'approve':
            $pedidos->approve($id, $body, $adminId);
            break;

        case 'conclude':
            $pedidos->conclude($id, $body, $adminId);
            break;

        case 'reject':
            $pedidos->reject($id, $body, $adminId);
            break;

        case 'delete':
            $pedidos->delete($id);
            break;

        default:
            ApiResponse::error('INVALID_ACTION', 'Ação inválida. Use "approve", "conclude", "reject" ou "delete".', 400);
    }

    ApiResponse::success(['id' => $id]);
}

/* ---------------------------------------------------------------- GET: listar */

$tipo = Validator::option('type', ['all', 'access', 'demo'], 'all', 'INVALID_TYPE', 'O tipo informado não é válido.');
$situacao = Validator::option('status', ['all', 'pending', 'approved', 'rejected'], 'all', 'INVALID_STATUS', 'A situação informada não é válida.');

$empresas = array_column($repo->companies(), 'name', 'id');
$represas = array_map(static fn (array $r): array => [
    'id'      => $r['id'],
    'name'    => $r['name'],
    'company' => $empresas[$r['company_id']] ?? '',
], $repo->reservoirs());

ApiResponse::success([
    'requests'   => $pedidos->all($tipo, $situacao),
    'summary'    => $pedidos->summary(),
    'roles'      => array_map(static fn (string $r): array => ['value' => $r, 'label' => Guard::roleLabel($r)], RequestRepository::ROLES),
    'reservoirs' => $represas,
], ['type' => $tipo, 'status' => $situacao]);
