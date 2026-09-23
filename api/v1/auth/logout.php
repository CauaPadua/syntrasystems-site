<?php
/**
 * POST /api/v1/auth/logout.php
 *
 * Encerra a sessão atual. É idempotente: chamar sem sessão ativa também
 * responde 200, porque o resultado desejado (sem sessão) já foi alcançado.
 *
 * Chamado por: assets/js/dashboard-shell.js, no botão "Sair do sistema" da topbar.
 * Usa POST (e não GET) para que um simples link ou imagem em outra página não
 * consiga deslogar o usuário sem ele querer.
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/backend/bootstrap.php';

use Aquapulse\Auth\AuthService;
use Aquapulse\Http\JsonResponse;
use Aquapulse\Http\Request;
use Aquapulse\Support\Container;

if (!Request::isMethod('POST')) {                                        // qualquer método diferente de POST recebe 405
    JsonResponse::methodNotAllowed(['POST']);
}

aq_start_session();                                                      // abre a sessão existente (se houver) para poder destruí-la

// O repositório de usuários vem do Container: com DB_HOST definido é o
// PdoUserRepository (tabela users do MySQL); sem ele, o MockUserRepository.
$auth = new AuthService(Container::users());
$auth->logout();                                                         // apaga os dados da sessão no servidor e o cookie no navegador

JsonResponse::data(['message' => 'Sessão encerrada.']);                  // 200 sempre, com ou sem sessão anterior
