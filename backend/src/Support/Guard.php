<?php
/**
 * Aquapulse — proteção de sessão para páginas e endpoints do sistema.
 *
 * Reutiliza a autenticação e a sessão JÁ EXISTENTES (etapa do login).
 * Não existe um segundo sistema de autenticação.
 *
 *  - Páginas do dashboard sem sessão  -> redirecionam para login.php
 *  - Endpoints da API sem sessão      -> 401 em JSON (nunca HTML)
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Auth\AuthService;

final class Guard
{
    /**
     * Exige sessão válida em um endpoint da API.
     * Sem sessão, responde 401 JSON e encerra.
     *
     * @return array{id:int,name:string,email:string,role:string}
     */
    public static function requireApiSession(): array
    {
        aq_start_session();

        $auth = new AuthService(Container::users());
        $user = $auth->currentUser();

        if ($user === null) {
            ApiResponse::unauthorized();
        }

        return $user;
    }

    /**
     * Exige sessão válida em uma página do dashboard.
     * Sem sessão, redireciona para o login e encerra.
     *
     * @return array{id:int,name:string,email:string,role:string}
     */
    public static function requirePageSession(string $loginUrl = '../login.php'): array
    {
        aq_start_session();

        $auth = new AuthService(Container::users());
        $user = $auth->currentUser();

        if ($user === null) {
            if (!headers_sent()) {
                header('Location: ' . $loginUrl . '?redirect=dashboard', true, 302);
            }
            echo '<!DOCTYPE html><meta charset="utf-8">'
               . '<p>Sessão expirada. <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES) . '">Entrar novamente</a>.</p>';
            exit;
        }

        return $user;
    }

    /** Iniciais do usuário para o avatar da topbar (ex.: "Ana Silva" -> "AS"). */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '?';
        }
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2, 'UTF-8'), 'UTF-8');
        }

        return mb_strtoupper(
            mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8'),
            'UTF-8'
        );
    }
}
