<?php
/**
 * Aquapulse — inicialização do back-end.
 *
 * Carregado exclusivamente pelos pontos de entrada em api/. O front-end
 * (login.php, index.php) nunca inclui este arquivo.
 *
 * Responsabilidades:
 *  - carregar variáveis de ambiente do arquivo .env, se existir;
 *  - registrar o autoload das classes do namespace Aquapulse\;
 *  - silenciar a exibição de erros (a saída é sempre JSON);
 *  - converter erros e exceções não tratadas em uma resposta 500 genérica;
 *  - iniciar a sessão com a configuração centralizada.
 *
 * Quem inclui este arquivo (com require/require_once):
 *  - api/v1/_boot.php            -> todos os endpoints de dados do dashboard;
 *  - api/v1/auth/*.php           -> login, logout e "quem sou eu";
 *  - dashboard/includes/page.php -> todas as páginas do sistema interno.
 * Ou seja, na prática ele também é a base das páginas do dashboard, não só da API.
 */

declare(strict_types=1);                                    // tipos estritos: passar string onde a função espera int gera TypeError em vez de conversão silenciosa

define('AQ_BACKEND_PATH', __DIR__);                         // caminho absoluto da pasta backend/; usado para localizar src/, config/ e storage/ sem depender do diretório atual

/* ------------------------------------------------------------ variáveis de ambiente */

/**
 * Lê o arquivo .env (se existir) e disponibiliza as variáveis via getenv().
 * Não sobrescreve variáveis já definidas no ambiente do servidor.
 *
 * É uma função anônima executada imediatamente: roda uma única vez, na inclusão
 * deste arquivo, e não deixa variáveis soltas no escopo global.
 * As variáveis lidas aqui (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) decidem,
 * em Support\Container, se o sistema usa o banco MySQL ou os dados simulados.
 */
(static function (): void {
    $arquivoEnv = AQ_BACKEND_PATH . '/.env';                // o .env fica em backend/ e não é versionado (só existe o .env.example)

    if (!is_file($arquivoEnv)) {                            // sem .env (ex.: ambiente local de desenvolvimento) nada é carregado
        return;                                             // e o Container cai para os repositórios simulados
    }

    foreach (file($arquivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {   // percorre o arquivo linha a linha, já sem "\n" e sem linhas vazias
        $linha = trim($linha);                              // remove espaços nas pontas para as comparações abaixo funcionarem

        if ($linha === '' || str_starts_with($linha, '#')) { // linha só com espaços ou comentário ("# ...") é ignorada
            continue;
        }

        if (!str_contains($linha, '=')) {                   // linha sem "=" não tem formato CHAVE=valor; é descartada em vez de gerar erro
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);         // divide só no PRIMEIRO "=": o valor pode conter "=" (ex.: senhas)
        $chave = trim($chave);
        $valor = trim($valor);

        if (getenv($chave) === false) {                     // só define se o servidor ainda não tiver essa variável:
            putenv("{$chave}={$valor}");                    // a configuração real do servidor tem prioridade sobre o arquivo
        }
    }
})();

/* --------------------------------------------------------------- autoload */
/*
 * Autoload no padrão PSR-4 simplificado: quando o código usa uma classe que
 * ainda não foi carregada, o PHP chama esta função com o nome completo dela.
 * Exemplo: Aquapulse\Support\Guard  ->  backend/src/Support/Guard.php
 * Assim nenhum arquivo precisa de require manual para cada classe.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Aquapulse\\';                                // só trata classes do próprio projeto (namespace Aquapulse\)
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { // classe de outro namespace: deixa para outros autoloaders
        return;
    }

    $relative = substr($class, strlen($prefix));            // remove o prefixo: "Aquapulse\Support\Guard" vira "Support\Guard"
    $file = AQ_BACKEND_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php'; // troca "\" por "/" e monta o caminho do arquivo

    if (is_file($file)) {                                   // só inclui se o arquivo existir; senão o PHP gera o erro "classe não encontrada"
        require $file;
    }
});

/* ------------------------------------------------- erros nunca vão para a saída */
ini_set('display_errors', '0');                             // nunca imprime erros na resposta: quebraria o JSON e poderia expor caminhos do servidor
ini_set('display_startup_errors', '0');                     // o mesmo para erros que acontecem na inicialização do PHP
ini_set('log_errors', '1');                                 // em vez de exibir, grava no log do servidor (onde o desenvolvedor consulta)
error_reporting(E_ALL);                                     // registra todos os níveis de erro, inclusive avisos

/**
 * Qualquer falha inesperada vira um 500 genérico, sem vazar caminhos,
 * mensagens internas ou stack traces.
 *
 * Esta função é chamada automaticamente pelo PHP quando uma exceção não é
 * capturada por nenhum try/catch — incluindo os erros convertidos em exceção
 * pelo set_error_handler logo abaixo.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log('[aquapulse] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()); // detalhes completos vão só para o log interno

    if (class_exists(\Aquapulse\Http\JsonResponse::class)) {                     // se a classe de resposta estiver disponível (autoload funcionando)...
        \Aquapulse\Http\JsonResponse::error('INTERNAL_ERROR', 'Erro interno inesperado.', 500); // ...responde pelo formato padrão; esse método encerra o script com exit
    }

    // Plano B: só é alcançado se JsonResponse não puder ser carregada.
    if (!headers_sent()) {                                  // cabeçalhos só podem ser enviados se nada foi impresso ainda
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo '{"error":{"code":"INTERNAL_ERROR","message":"Erro interno inesperado."}}'; // JSON escrito à mão, sem depender de nenhuma classe
    exit;
});

/*
 * Converte avisos e erros "leves" do PHP (warning, notice, deprecated) em
 * ErrorException. Assim eles interrompem a execução e caem no handler acima,
 * em vez de o script continuar com dados inconsistentes.
 */
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {                 // erro silenciado (ex.: operador @ ou nível desligado): devolve o controle ao PHP
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line); // transforma o aviso em exceção tratável
});

/* ---------------------------------------------------------------- sessão */

/** Carrega a configuração da sessão (avaliada a cada requisição por causa do HTTPS). */
function aq_session_config(): array
{
    return require AQ_BACKEND_PATH . '/config/session.php'; // o arquivo retorna um array; usar require (e não require_once) garante que ele seja reavaliado a cada chamada
}

/**
 * Inicia a sessão aplicando a configuração central e os limites de expiração
 * verificados no servidor (inatividade e duração absoluta).
 *
 * Chamada por: Guard::requireApiSession(), Guard::requirePageSession() e pelos
 * endpoints de autenticação (login, logout, me).
 *
 * Fluxo:
 *  1. se a sessão já estiver ativa nesta requisição, não faz nada;
 *  2. aplica as opções de segurança e os parâmetros do cookie;
 *  3. abre a sessão (lê o cookie AQUAPULSE_SESSION enviado pelo navegador);
 *  4. verifica se ela expirou por inatividade ou por duração máxima;
 *  5. se expirou, destrói e abre uma sessão nova e vazia (usuário deslogado);
 *  6. se não expirou e há usuário logado, renova o carimbo de "última atividade".
 */
function aq_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {         // evita "session already started" quando mais de um trecho chama esta função
        return;
    }

    $config = aq_session_config();                          // array definido em backend/config/session.php

    // Só aceita identificadores gerados pelo próprio PHP e transportados por cookie.
    ini_set('session.use_strict_mode', '1');                // rejeita IDs de sessão inventados pelo cliente (proteção contra fixação de sessão)
    ini_set('session.use_only_cookies', '1');               // o ID nunca é aceito pela URL (?PHPSESSID=...), só pelo cookie
    ini_set('session.use_trans_sid', '0');                  // o PHP não reescreve links para anexar o ID da sessão

    session_name($config['name']);                          // nome do cookie: AQUAPULSE_SESSION
    session_set_cookie_params([                             // atributos de segurança do cookie de sessão
        'lifetime' => $config['cookie_lifetime'],           // 0 = expira quando o navegador fecha
        'path'     => $config['path'],                      // cookie válido para todo o site
        'domain'   => $config['domain'],                    // vazio = somente o domínio atual
        'secure'   => $config['secure'],                    // só é enviado por HTTPS quando a requisição é HTTPS
        'httponly' => $config['httponly'],                  // JavaScript não consegue ler o cookie (reduz impacto de XSS)
        'samesite' => $config['samesite'],                  // não é enviado em requisições vindas de outros sites (reduz CSRF)
    ]);

    session_start();                                        // abre a sessão e popula $_SESSION a partir do armazenamento do servidor

    $now = time();                                          // instante atual em segundos (timestamp Unix)
    $startedAt = $_SESSION['auth']['started_at'] ?? null;   // quando o login foi feito (gravado por AuthService::login)
    $seenAt    = $_SESSION['auth']['last_seen_at'] ?? null; // última requisição autenticada; null quando não há ninguém logado

    $expirouPorInatividade = $seenAt !== null && ($now - (int) $seenAt) > $config['idle_timeout'];        // ficou mais de 30 min sem nenhuma requisição
    $expirouPorDuracao     = $startedAt !== null && ($now - (int) $startedAt) > $config['absolute_timeout']; // passou de 12 h desde o login, mesmo com uso contínuo

    if ($expirouPorInatividade || $expirouPorDuracao) {     // qualquer um dos dois limites encerra a sessão
        aq_destroy_session();                               // apaga os dados e o cookie da sessão antiga
        session_start();                                    // abre uma sessão nova e vazia: o usuário passa a ser tratado como não autenticado
        return;
    }

    if (isset($_SESSION['auth'])) {                         // há usuário logado e a sessão continua válida:
        $_SESSION['auth']['last_seen_at'] = $now;           // renova o prazo de inatividade a cada requisição
    }
}

/**
 * Encerra a sessão atual e remove o cookie do navegador.
 *
 * Chamada por: aq_start_session() (sessão expirada) e AuthService::logout()
 * (logout explícito ou usuário que deixou de existir).
 */
function aq_destroy_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {          // não há sessão aberta: nada a destruir
        return;
    }

    $_SESSION = [];                                         // limpa os dados em memória desta requisição

    if (ini_get('session.use_cookies')) {                   // se a sessão usa cookie, é preciso apagá-lo também no navegador
        $params = session_get_cookie_params();              // reutiliza os mesmos atributos com que o cookie foi criado (o navegador exige isso para apagar)
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,                   // data no passado: o navegador remove o cookie imediatamente
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',     // fallback para versões do PHP que não devolvem samesite
        ]);
    }

    session_destroy();                                      // remove o arquivo/registro da sessão no servidor
}
