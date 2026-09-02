<?php
/**
 * POST /api/v1/auth/logout.php
 *
 * Encerra a sessão atual. É idempotente: chamar sem sessão ativa também
 * responde 200, porque o resultado desejado (sem sessão) já foi alcançado.
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/backend/bootstrap.php';

use Aquapulse\Auth\AuthService;
use Aquapulse\Http\JsonResponse;
use Aquapulse\Http\Request;
use Aquapulse\Repositories\MockUserRepository;

if (!Request::isMethod('POST')) {
    JsonResponse::methodNotAllowed(['POST']);
}

aq_start_session();

// TROCA FUTURA: substituir MockUserRepository por PdoUserRepository.
$auth = new AuthService(new MockUserRepository());
$auth->logout();

JsonResponse::data(['message' => 'Sessão encerrada.']);
