<?php
/**
 * GET /api/v1/auth/me.php
 *
 * Devolve o usuário da sessão atual. A sessão é lida exclusivamente aqui,
 * no servidor: o front-end nunca acessa o cookie nem guarda estado próprio.
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/backend/bootstrap.php';

use Aquapulse\Auth\AuthService;
use Aquapulse\Http\JsonResponse;
use Aquapulse\Http\Request;
use Aquapulse\Repositories\MockUserRepository;

if (!Request::isMethod('GET')) {
    JsonResponse::methodNotAllowed(['GET']);
}

aq_start_session();

// TROCA FUTURA: substituir MockUserRepository por PdoUserRepository.
$auth = new AuthService(new MockUserRepository());

$user = $auth->currentUser();

if ($user === null) {
    JsonResponse::error('UNAUTHENTICATED', 'Sessão não encontrada ou expirada.', 401);
}

JsonResponse::data(['user' => $user]);
