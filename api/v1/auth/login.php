<?php
/**
 * POST /api/v1/auth/login.php
 *
 * Ponto de entrada: valida o formato da requisição e delega ao AuthService.
 * Nenhuma regra de autenticação vive aqui.
 *
 * Chamado por: assets/js/login.js, ao enviar o formulário de login.php.
 *
 * Entrada (corpo JSON): { "email": "...", "password": "..." }
 * Respostas:
 *   200 { data: { user: {id, name, email, role} } } + cookie de sessão novo
 *   400 INVALID_CONTENT_TYPE / INVALID_JSON  -> requisição mal formada
 *   401 INVALID_CREDENTIALS                  -> e-mail ou senha incorretos
 *   405 METHOD_NOT_ALLOWED                   -> método diferente de POST
 *   422 VALIDATION_ERROR (+ details por campo) -> campos vazios ou e-mail inválido
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/backend/bootstrap.php';                  // sobe 3 pastas (auth -> v1 -> api -> raiz) e carrega o back-end

use Aquapulse\Auth\AuthService;
use Aquapulse\Http\JsonResponse;
use Aquapulse\Http\Request;
use Aquapulse\Support\Container;

if (!Request::isMethod('POST')) {                                         // login só por POST: credenciais nunca devem ir na URL (GET fica em histórico e logs)
    JsonResponse::methodNotAllowed(['POST']);
}

if (!Request::hasJsonContentType()) {                                     // o front-end envia JSON; qualquer outro formato é recusado
    JsonResponse::error(
        'INVALID_CONTENT_TYPE',
        'Envie os dados com Content-Type: application/json.',
        400
    );
}

$body = Request::decodeJson(Request::rawBody());                          // lê o corpo bruto e transforma o JSON em array

if ($body === null) {                                                     // JSON quebrado, vazio ou que não é um objeto
    JsonResponse::error('INVALID_JSON', 'Corpo da requisição não é um JSON válido.', 400);
}

$email    = Request::normalizeEmail(Request::stringField($body, 'email')); // e-mail sem espaços e em minúsculas
$password = Request::stringField($body, 'password', false);               // senha exatamente como digitada (false = sem trim)

/* ------------------------------------------- validação dos campos (repetida no servidor) */
/* O login.js já valida no navegador, mas essa validação pode ser contornada
   (ex.: chamando a API diretamente). Por isso as mesmas regras são aplicadas aqui. */
$erros = [];                                                              // mensagens por campo: ['email' => '...', 'password' => '...']

if ($email === '') {
    $erros['email'] = 'Informe o e-mail.';
} elseif (!Request::isValidEmail($email)) {                               // só testa o formato se o campo não estiver vazio
    $erros['email'] = 'Informe um e-mail válido.';
}

if ($password === '') {
    $erros['password'] = 'Informe a senha.';
}

if ($erros !== []) {                                                      // algum campo inválido: 422 com o detalhe de cada campo
    JsonResponse::error('VALIDATION_ERROR', 'Verifique os campos informados.', 422, $erros);
}

/* ----------------------------------------------------------------- autenticação */
aq_start_session();                                                       // a sessão precisa estar aberta para gravar o usuário depois

// O repositório de usuários vem do Container: com DB_HOST definido é o
// PdoUserRepository (tabela users do MySQL); sem ele, o MockUserRepository.
$auth = new AuthService(Container::users());

$user = $auth->attempt($email, $password);                                // confere e-mail e senha; null se não conferirem

if ($user === null) {
    // Mensagem genérica: não revela se o e-mail existe.
    JsonResponse::error('INVALID_CREDENTIALS', 'E-mail ou senha inválidos.', 401);
}

$auth->login($user);                                                      // regenera o ID da sessão e grava user_id e horários

JsonResponse::data(['user' => $user]);                                    // 200 com os dados públicos do usuário; login.js então redireciona ao dashboard
