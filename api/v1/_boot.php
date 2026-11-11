<?php
/**
 * Aquapulse — inicialização comum dos endpoints do sistema.
 *
 * Todo endpoint usado pelas telas inclui este arquivo. Ele garante, em um só lugar:
 *   - método HTTP permitido;
 *   - sessão válida de uma conta ativa — sem ela, 401 em JSON;
 *   - repositório já restrito às empresas que o usuário pode consultar.
 *
 * O nome começa com "_" para indicar que não é um endpoint.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

use Aquapulse\Http\Request;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

/**
 * Prepara o endpoint: valida o método, exige sessão e devolve o repositório.
 *
 * Uso: [$repo, $user] = aq_api_boot(['GET', 'POST']);
 *
 * @param array<int,string> $allowedMethods
 * @return array{0:\Aquapulse\Contracts\MonitoringRepositoryInterface,1:array<string,mixed>}
 */
function aq_api_boot(array $allowedMethods = ['GET']): array
{
    if (!in_array(Request::method(), $allowedMethods, true)) {
        ApiResponse::methodNotAllowed($allowedMethods);
    }

    $user = Guard::requireApiSession();

    return [Container::monitoring(Guard::companyScope($user)), $user];
}

/**
 * Corpo JSON de uma requisição de escrita (POST), já decodificado.
 * Responde 400 quando o conteúdo não é um objeto JSON válido.
 *
 * @return array<string,mixed>
 */
function aq_json_body(): array
{
    if (!Request::hasJsonContentType()) {
        ApiResponse::error('INVALID_CONTENT_TYPE', 'Envie os dados com Content-Type: application/json.', 400);
    }

    $body = Request::decodeJson(Request::rawBody());

    if ($body === null) {
        ApiResponse::error('INVALID_JSON', 'Corpo da requisição não é um JSON válido.', 400);
    }

    return $body;
}

/**
 * IDs das represas que o usuário pode consultar — usado pelas operações de
 * escrita para conferir se o registro pertence ao escopo dele.
 *
 * @return array<int,string>
 */
function aq_reservoir_ids(\Aquapulse\Contracts\MonitoringRepositoryInterface $repo): array
{
    return array_column($repo->reservoirs(), 'id');
}
