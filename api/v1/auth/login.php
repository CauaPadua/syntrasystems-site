<?php
/**
 * POST /api/v1/auth/login.php
 *
 * Ponto de entrada: valida o formato da requisição e delega ao AuthService.
 * Nenhuma regra de autenticação vive aqui.
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

if (!Request::hasJsonContentType()) {
    JsonResponse::error(
        'INVALID_CONTENT_TYPE',
        'Envie os dados com Content-Type: application/json.',
        400
    );
}

$body = Request::decodeJson(Request::rawBody());

if ($body === null) {
    JsonResponse::error('INVALID_JSON', 'Corpo da requisição não é um JSON válido.', 400);
}

$email    = Request::normalizeEmail(Request::stringField($body, 'email'));
$password = Request::stringField($body, 'password', false);

/* ------------------------------------------- validação dos campos (repetida no servidor) */
$erros = [];

if ($email === '') {
    $erros['email'] = 'Informe o e-mail.';
} elseif (!Request::isValidEmail($email)) {
    $erros['email'] = 'Informe um e-mail válido.';
}

if ($password === '') {
    $erros['password'] = 'Informe a senha.';
}

if ($erros !== []) {
    JsonResponse::error('VALIDATION_ERROR', 'Verifique os campos informados.', 422, $erros);
}

/* ----------------------------------------------------------------- autenticação */
aq_start_session();

// TROCA FUTURA: substituir MockUserRepository por PdoUserRepository.
$auth = new AuthService(new MockUserRepository());

$user = $auth->attempt($email, $password);

if ($user === null) {
    // Mensagem genérica: não revela se o e-mail existe.
    JsonResponse::error('INVALID_CREDENTIALS', 'E-mail ou senha inválidos.', 401);
}

$auth->login($user);

JsonResponse::data(['user' => $user]);
