<?php
/**
 * Aquapulse — inicialização comum dos endpoints do sistema.
 *
 * Todo endpoint de dados inclui este arquivo. Ele garante, em um só lugar:
 *   - carregamento do back-end e do autoload;
 *   - método HTTP permitido (somente GET nos endpoints de leitura);
 *   - sessão válida — sem ela, 401 em JSON (nunca uma página HTML);
 *   - acesso ao repositório através do Container (ponto de troca do banco).
 *
 * Não emite nada por conta própria: quem responde é cada endpoint.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

/**
 * Prepara o endpoint: valida método, exige sessão e devolve o repositório.
 *
 * @param array<int,string> $allowedMethods
 * @return array{0:\Aquapulse\Contracts\MonitoringRepositoryInterface,1:array<string,mixed>}
 */
function aq_api_boot(array $allowedMethods = ['GET']): array
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if (!in_array($method, $allowedMethods, true)) {
        ApiResponse::methodNotAllowed($allowedMethods);
    }

    $user = Guard::requireApiSession();

    return [Container::monitoring(), $user];
}
