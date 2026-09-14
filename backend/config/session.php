<?php
/**
 * Aquapulse — configuração centralizada da sessão PHP.
 *
 * Este arquivo apenas descreve a sessão. Quem aplica a configuração é
 * aq_start_session(), em backend/bootstrap.php.
 *
 * Ele RETORNA um array (return [...]) em vez de definir variáveis globais:
 * quem faz `require` recebe esse array como resultado.
 */

declare(strict_types=1);

/** A requisição atual está sob HTTPS? */
$aqIsHttps = (                                                                       // true quando a conexão com o navegador é criptografada
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')  // Apache/IIS informam HTTPS=on (IIS usa "off" quando não é)
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443                                  // porta padrão do HTTPS
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'   // atrás de proxy/CDN, o HTTPS termina no proxy, que avisa por este cabeçalho
);

return [
    // Nome do cookie de sessão.
    'name' => 'AQUAPULSE_SESSION',

    // 0 = cookie de sessão: o navegador o descarta ao ser fechado.
    // O tempo de vida real é controlado no servidor pelos dois limites abaixo.
    'cookie_lifetime' => 0,

    // Inatividade máxima permitida: 30 minutos.
    'idle_timeout' => 1800,                                 // 30 × 60 segundos; comparado com last_seen_at em aq_start_session()

    // Duração máxima absoluta da sessão, mesmo em uso contínuo: 12 horas.
    'absolute_timeout' => 43200,                            // 12 × 60 × 60 segundos; comparado com started_at

    'path'     => '/',                                      // o cookie vale para todas as URLs do site (landing, login, dashboard e API)
    'domain'   => '',                                       // vazio: fica restrito ao domínio exato que o criou
    'secure'   => $aqIsHttps,   // só trafega em HTTPS quando disponível
    'httponly' => true,         // inacessível ao JavaScript
    'samesite' => 'Lax',        // bloqueia envio em requisições cross-site
];
