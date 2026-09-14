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
 *
 * O nome começa com "_" para indicar que não é um endpoint: não deve ser
 * chamado diretamente pelo navegador, só incluído pelos outros arquivos.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';          // sobe 2 pastas (api/v1 -> raiz) e carrega o back-end; require_once evita carregar duas vezes

use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

/**
 * Prepara o endpoint: valida método, exige sessão e devolve o repositório.
 *
 * Fluxo:
 *  1. lê o método HTTP da requisição;
 *  2. se não estiver na lista permitida, responde 405 e encerra;
 *  3. exige usuário logado (sem sessão: 401 e encerra);
 *  4. devolve o repositório de dados e o usuário autenticado.
 *
 * Uso típico nos endpoints: [$repo] = aq_api_boot();
 * (a desestruturação pega só o primeiro item do array, o repositório).
 *
 * @param array<int,string> $allowedMethods
 * @return array{0:\Aquapulse\Contracts\MonitoringRepositoryInterface,1:array<string,mixed>}
 */
function aq_api_boot(array $allowedMethods = ['GET']): array
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')); // método da requisição atual, em maiúsculas

    if (!in_array($method, $allowedMethods, true)) {                   // ex.: POST em um endpoint só de leitura
        ApiResponse::methodNotAllowed($allowedMethods);                // 405 + cabeçalho Allow; encerra a requisição
    }

    $user = Guard::requireApiSession();                                // só continua se houver sessão válida

    return [Container::monitoring(), $user];                           // repositório escolhido pelo Container (mock ou banco) + usuário logado
}
