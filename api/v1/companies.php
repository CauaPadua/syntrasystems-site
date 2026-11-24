<?php
/**
 * Empresas.
 *
 * GET  /api/v1/companies.php   empresas que o usuário pode ver (preenche os seletores de empresa)
 * POST /api/v1/companies.php   { "action": "create", "name", "manager", "status" }        só administrador
 * POST /api/v1/companies.php   { "action": "update", "id", "name", "manager", "status" }  só administrador
 *
 * O SQL do cadastro fica em Repositories\RegistryRepository.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Repositories\RegistryRepository;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

[$repo, $user] = aq_api_boot(['GET', 'POST']);

if (Request::isMethod('POST')) {
    Guard::requireCan($user, 'registry.edit');
    $cadastro = new RegistryRepository(Container::requireDatabase());
    $body = aq_json_body();

    switch (Request::stringField($body, 'action')) {
        case 'create':
            ApiResponse::success(['id' => $cadastro->createCompany($body)], [], 201);
            break;

        case 'update':
            $id = Request::stringField($body, 'id');
            $cadastro->updateCompany($id, $body);
            ApiResponse::success(['id' => $id]);
            break;

        default:
            ApiResponse::error('INVALID_ACTION', 'Ação inválida. Use "create" ou "update".', 400);
    }
}

ApiResponse::success(
    ['companies' => $repo->companies()],
    ['company_id' => 'all']                                              // meta: nenhum filtro de empresa aplicado
);
