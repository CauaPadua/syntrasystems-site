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
use Aquapulse\Repositories\MockUserRepository;

if (!Request::isMethod('POST')) {                                        // qualquer método diferente de POST recebe 405
    JsonResponse::methodNotAllowed(['POST']);
}

aq_start_session();                                                      // abre a sessão existente (se houver) para poder destruí-la

// TROCA FUTURA: substituir MockUserRepository por PdoUserRepository.
$auth = new AuthService(new MockUserRepository());                       // o repositório não é consultado no logout; é exigido só pelo construtor
$auth->logout();                                                         // apaga os dados da sessão no servidor e o cookie no navegador

JsonResponse::data(['message' => 'Sessão encerrada.']);                  // 200 sempre, com ou sem sessão anterior
