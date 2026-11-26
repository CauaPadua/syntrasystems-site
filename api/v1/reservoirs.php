<?php
/**
 * Represas.
 *
 * GET  /api/v1/reservoirs.php?company_id={id|all}   represas que o usuário pode ver (seletores de represa)
 * POST /api/v1/reservoirs.php   { "action": "create", ...campos do cadastro }        só administrador
 * POST /api/v1/reservoirs.php   { "action": "update", "id", ...campos do cadastro }  só administrador
 *
 * O cadastro completo de cada represa vem em GET api/v1/settings.php. O SQL fica
 * em Repositories\RegistryRepository.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Repositories\RegistryRepository;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;
use Aquapulse\Support\Validator;

[$repo, $user] = aq_api_boot(['GET', 'POST']);

if (Request::isMethod('POST')) {
    Guard::requireCan($user, 'registry.edit');
    $cadastro = new RegistryRepository(Container::requireDatabase());
    $body = aq_json_body();

    switch (Request::stringField($body, 'action')) {
        case 'create':
            ApiResponse::success(['id' => $cadastro->createReservoir($body)], [], 201);
            break;

        case 'update':
            $id = Request::stringField($body, 'id');
            $cadastro->updateReservoir($id, $body);
            ApiResponse::success(['id' => $id]);
            break;

        default:
            ApiResponse::error('INVALID_ACTION', 'Ação inválida. Use "create" ou "update".', 400);
    }
}

$companyId = Validator::companyId($repo->companies());                   // valida ?company_id contra as empresas existentes

$list = array_map(static function (array $r): array {                   // transforma cada represa em um item resumido
    return [
        'id'         => $r['id'],
        'code'       => $r['code'],
        'name'       => $r['name'],
        'company_id' => $r['company_id'],
        'city'       => $r['city'],
        'level'      => $r['level_pct'],
        'status'     => StatusRules::describe(StatusRules::fromLevel($r['level_pct'], $r)),   // {key, label, icon}: mesma regra do sistema todo
    ];
}, $repo->reservoirs($companyId));                                       // represas filtradas pela empresa

ApiResponse::success(
    ['reservoirs' => $list],
    ['company_id' => $companyId]                                         // meta: filtro efetivamente aplicado
);
