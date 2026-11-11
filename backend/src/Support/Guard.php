<?php
/**
 * Aquapulse — controle de acesso das páginas e endpoints do sistema.
 *
 * Três verificações, sempre feitas no servidor:
 *   1. sessão      há um usuário logado e a conta está ativa?
 *   2. perfil      a ação exige administrador?
 *   3. empresa     quais empresas o usuário pode consultar?
 *
 * Perfis (coluna users.role):
 *   admin     vê todas as empresas e pode cadastrar equipamentos e alterar limites;
 *   operator  vê só as empresas vinculadas a ele (user_company_access), trata
 *             alertas e abre chamados, mas não altera cadastros.
 *
 * Páginas sem sessão redirecionam para o login; a API responde 401 em JSON.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Auth\AuthService;

final class Guard
{
    /**
     * Exige sessão válida em um endpoint da API (401 em JSON se não houver).
     *
     * @return array{id:int,name:string,email:string,role:string,status:string}
     */
    public static function requireApiSession(): array
    {
        aq_start_session();

        $user = (new AuthService(Container::users()))->currentUser();

        if ($user === null) {
            ApiResponse::unauthorized();
        }

        return $user;
    }

    /**
     * Exige sessão válida em uma página do dashboard (redireciona ao login se não houver).
     *
     * @return array{id:int,name:string,email:string,role:string,status:string}
     */
    public static function requirePageSession(string $loginUrl = '../login.php'): array
    {
        aq_start_session();

        $user = (new AuthService(Container::users()))->currentUser();

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

    public static function isAdmin(array $user): bool
    {
        return ($user['role'] ?? '') === 'admin';
    }

    /** Exige perfil de administrador em um endpoint (403 em JSON se não for). */
    public static function requireAdmin(array $user): void
    {
        if (!self::isAdmin($user)) {
            ApiResponse::error('FORBIDDEN', 'Seu perfil não tem permissão para esta ação.', 403);
        }
    }

    /**
     * Empresas que o usuário pode consultar.
     *
     * @return array<int,string>|null null = todas (administrador)
     */
    public static function companyScope(array $user): ?array
    {
        if (self::isAdmin($user)) {
            return null;
        }

        return Container::users()->companyIds((int) $user['id']);
    }

    /** Nome do perfil para exibição. */
    public static function roleLabel(string $role): string
    {
        return $role === 'admin' ? 'Administrador' : 'Operador';
    }

    /** Iniciais para o avatar da topbar (ex.: "Ana Silva" -> "AS"). */
    public static function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

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
