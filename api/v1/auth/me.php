<?php
/**
 * GET /api/v1/auth/me.php
 *
 * Devolve o usuário da sessão atual. A sessão é lida exclusivamente aqui,
 * no servidor: o front-end nunca acessa o cookie nem guarda estado próprio.
 *
 * Chamado por: assets/js/login.js ao abrir a tela de login — se já houver
 * sessão válida, o usuário é mandado direto ao dashboard.
 *
 * Respostas: 200 { data: { user } } | 401 UNAUTHENTICATED | 405 METHOD_NOT_ALLOWED
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/backend/bootstrap.php';

use Aquapulse\Auth\AuthService;
use Aquapulse\Http\JsonResponse;
use Aquapulse\Http\Request;
use Aquapulse\Support\Container;

if (!Request::isMethod('GET')) {                                         // consulta apenas: só GET é aceito
    JsonResponse::methodNotAllowed(['GET']);
}

aq_start_session();                                                      // lê o cookie de sessão e aplica as regras de expiração

// O repositório de usuários vem do Container: com DB_HOST definido é o
// PdoUserRepository (tabela users do MySQL); sem ele, o MockUserRepository.
$auth = new AuthService(Container::users());

$user = $auth->currentUser();                                            // usuário da sessão, ou null

if ($user === null) {                                                    // ninguém logado (ou sessão expirada)
    JsonResponse::error('UNAUTHENTICATED', 'Sessão não encontrada ou expirada.', 401);
}

JsonResponse::data(['user' => $user]);                                   // dados públicos, sem hash de senha
