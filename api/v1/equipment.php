<?php
/**
 * Cadastro de equipamentos — CRUD completo da tabela sensors.
 *
 * GET  /api/v1/equipment.php?reservoir_id={id|all}     CONSULTAR  (SELECT)
 * POST /api/v1/equipment.php  { "action": "create", "id", "name", "location", "type", "status", "reservoir_id" }
 *                                                      CADASTRAR  (INSERT)
 * POST /api/v1/equipment.php  { "action": "update", "id", "name", "location", "type", "status", "reservoir_id" }
 *                                                      EDITAR     (UPDATE)
 * POST /api/v1/equipment.php  { "action": "delete", "id" }
 *                                                      EXCLUIR    (DELETE)
 *
 * Consultar é permitido a qualquer usuário logado (dentro das empresas que ele
 * pode ver). Cadastrar, editar e excluir exigem perfil de administrador.
 *
 * As três escritas usam POST com o campo "action" porque algumas hospedagens
 * compartilhadas só aceitam os métodos GET e POST.
 *
 * Chamado por assets/js/pages/equipment.js (dashboard/equipamentos.php). O SQL
 * fica em Repositories\EquipmentRepository.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Repositories\EquipmentRepository;
use Aquapulse\Services\EquipmentRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;
use Aquapulse\Support\Validator;

[$repo, $user] = aq_api_boot(['GET', 'POST']);

$pdo = Container::requireDatabase();
$equipamentos = new EquipmentRepository($pdo);

$represas = $repo->reservoirs();                             // represas das empresas que o usuário pode ver
$permitidas = array_column($represas, 'id');

/* --------------------------------------------- POST: cadastrar/editar/excluir */

if (Request::isMethod('POST')) {
    Guard::requireAdmin($user);

    $body = aq_json_body();
    $acao = is_string($body['action'] ?? null) ? $body['action'] : '';
    $id = is_string($body['id'] ?? null) ? mb_strtoupper(trim($body['id']), 'UTF-8') : '';

    switch ($acao) {
        case 'create':
            ApiResponse::success(['equipment' => $equipamentos->create($body, $permitidas)], [], 201);
            break;

        case 'update':
            ApiResponse::success(['equipment' => $equipamentos->update($id, $body, $permitidas)]);
            break;

        case 'delete':
            $equipamentos->delete($id, $permitidas);
            ApiResponse::success(['deleted' => $id]);
            break;

        default:
            ApiResponse::error('INVALID_ACTION', 'Ação inválida. Use "create", "update" ou "delete".', 400);
    }
}

/* ----------------------------------------------------------- GET: consultar */

$reservoirId = Validator::reservoirId($represas, true);
$lista = $equipamentos->all($permitidas, $reservoirId);

$online = count(array_filter($lista, static fn (array $e): bool => $e['communication'] === 'online'));
$ativos = count(array_filter($lista, static fn (array $e): bool => $e['status'] === 'active'));

ApiResponse::success([
    'equipment' => $lista,
    'summary'   => [
        'total'       => count($lista),
        'online'      => $online,
        'offline'     => $ativos - $online,                  // ativos no cadastro que não estão transmitindo
        'unavailable' => count($lista) - $ativos,            // em manutenção ou desativados
    ],
    // listas para os campos do formulário, na mesma ordem das regras do servidor
    'types'      => array_map(static fn (string $k, array $t): array => ['value' => $k, 'label' => $t['label'], 'unit' => $t['unit']],
                              array_keys(EquipmentRules::TYPES), EquipmentRules::TYPES),
    'statuses'   => array_map(static fn (string $k, string $l): array => ['value' => $k, 'label' => $l],
                              array_keys(EquipmentRules::STATUS), EquipmentRules::STATUS),
    'reservoirs' => array_map(static fn (array $r): array => ['id' => $r['id'], 'name' => $r['name']], $represas),
    'can_edit'   => Guard::isAdmin($user),
    'online_window_minutes' => intdiv(EquipmentRules::ONLINE_WINDOW, 60),
], [
    'reservoir_id' => $reservoirId,
]);
