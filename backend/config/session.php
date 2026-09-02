<?php
/**
 * Aquapulse — configuração centralizada da sessão PHP.
 *
 * Este arquivo apenas descreve a sessão. Quem aplica a configuração é
 * aq_start_session(), em backend/bootstrap.php.
 */

declare(strict_types=1);

/** A requisição atual está sob HTTPS? */
$aqIsHttps = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
);

return [
    // Nome do cookie de sessão.
    'name' => 'AQUAPULSE_SESSION',

    // 0 = cookie de sessão: o navegador o descarta ao ser fechado.
    // O tempo de vida real é controlado no servidor pelos dois limites abaixo.
    'cookie_lifetime' => 0,

    // Inatividade máxima permitida: 30 minutos.
    'idle_timeout' => 1800,

    // Duração máxima absoluta da sessão, mesmo em uso contínuo: 12 horas.
    'absolute_timeout' => 43200,

    'path'     => '/',
    'domain'   => '',
    'secure'   => $aqIsHttps,   // só trafega em HTTPS quando disponível
    'httponly' => true,         // inacessível ao JavaScript
    'samesite' => 'Lax',        // bloqueia envio em requisições cross-site
];
