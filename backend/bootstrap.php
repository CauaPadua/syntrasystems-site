<?php
/**
 * Aquapulse — inicialização do back-end.
 *
 * Carregado exclusivamente pelos pontos de entrada em api/. O front-end
 * (login.php, index.php) nunca inclui este arquivo.
 *
 * Responsabilidades:
 *  - registrar o autoload das classes do namespace Aquapulse\;
 *  - silenciar a exibição de erros (a saída é sempre JSON);
 *  - converter erros e exceções não tratadas em uma resposta 500 genérica;
 *  - iniciar a sessão com a configuração centralizada.
 */

declare(strict_types=1);

define('AQ_BACKEND_PATH', __DIR__);

/* --------------------------------------------------------------- autoload */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Aquapulse\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = AQ_BACKEND_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

/* ------------------------------------------------- erros nunca vão para a saída */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/**
 * Qualquer falha inesperada vira um 500 genérico, sem vazar caminhos,
 * mensagens internas ou stack traces.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log('[aquapulse] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());

    if (class_exists(\Aquapulse\Http\JsonResponse::class)) {
        \Aquapulse\Http\JsonResponse::error('INTERNAL_ERROR', 'Erro interno inesperado.', 500);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo '{"error":{"code":"INTERNAL_ERROR","message":"Erro interno inesperado."}}';
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/* ---------------------------------------------------------------- sessão */

/** Carrega a configuração da sessão (avaliada a cada requisição por causa do HTTPS). */
function aq_session_config(): array
{
    return require AQ_BACKEND_PATH . '/config/session.php';
}

/**
 * Inicia a sessão aplicando a configuração central e os limites de expiração
 * verificados no servidor (inatividade e duração absoluta).
 */
function aq_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = aq_session_config();

    // Só aceita identificadores gerados pelo próprio PHP e transportados por cookie.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name($config['name']);
    session_set_cookie_params([
        'lifetime' => $config['cookie_lifetime'],
        'path'     => $config['path'],
        'domain'   => $config['domain'],
        'secure'   => $config['secure'],
        'httponly' => $config['httponly'],
        'samesite' => $config['samesite'],
    ]);

    session_start();

    $now = time();
    $startedAt = $_SESSION['auth']['started_at'] ?? null;
    $seenAt    = $_SESSION['auth']['last_seen_at'] ?? null;

    $expirouPorInatividade = $seenAt !== null && ($now - (int) $seenAt) > $config['idle_timeout'];
    $expirouPorDuracao     = $startedAt !== null && ($now - (int) $startedAt) > $config['absolute_timeout'];

    if ($expirouPorInatividade || $expirouPorDuracao) {
        aq_destroy_session();
        session_start();
        return;
    }

    if (isset($_SESSION['auth'])) {
        $_SESSION['auth']['last_seen_at'] = $now;
    }
}

/** Encerra a sessão atual e remove o cookie do navegador. */
function aq_destroy_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
