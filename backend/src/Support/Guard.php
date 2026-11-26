<?php
/**
 * Aquapulse — controle de acesso das páginas e endpoints do sistema.
 *
 * Três verificações, sempre feitas no servidor:
 *   1. sessão   há um usuário logado e a conta está ativa?
 *   2. cargo    o cargo dele pode fazer esta ação?  (can / requireCan)
 *   3. alcance  quais represas ele pode ver?        (reservoirScope)
 *
 * Cargos (coluna users.role):
 *   admin     administrador: vê todas as represas; aprova pedidos e cadastra
 *             empresas, represas, contas e equipamentos;
 *   manager   gestor da represa: só as represas vinculadas a ele; cadastra os
 *             equipamentos delas e gera relatórios;
 *   operator  operador: só as represas vinculadas a ele; acompanha, trata
 *             alertas, abre chamados e consulta relatórios.
 * O vínculo entre conta e represa fica na tabela user_reservoir_access.
 *
 * Páginas sem sessão redirecionam para o login; a API responde 401 em JSON.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Auth\AuthService;

final class Guard
{
    /** Cargos, do maior para o menor, e o nome exibido na tela. */
    public const ROLES = [
        'admin'    => 'Administrador',
        'manager'  => 'Gestor da represa',
        'operator' => 'Operador',
    ];

    /** Quais cargos podem fazer cada ação. O que não está aqui é livre para quem está logado. */
    private const PERMISSIONS = [
        'equipment.edit'  => ['admin', 'manager'],   // cadastrar, editar e excluir equipamentos
        'reports.create'  => ['admin', 'manager'],   // gerar relatórios
        'requests.manage' => ['admin'],              // aprovar ou recusar pedidos
        'registry.edit'   => ['admin'],              // cadastrar empresas, represas e contas
        'limits.edit'     => ['admin'],              // mudar os limites de pH e de chuva
    ];

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

    /** O cargo do usuário permite esta ação? (chaves de PERMISSIONS) */
    public static function can(array $user, string $action): bool
    {
        return in_array($user['role'] ?? '', self::PERMISSIONS[$action] ?? [], true);
    }

    /** Exige que o cargo permita a ação em um endpoint (403 em JSON se não permitir). */
    public static function requireCan(array $user, string $action): void
    {
        if (!self::can($user, $action)) {
            ApiResponse::error('FORBIDDEN', 'Seu cargo não tem permissão para esta ação.', 403);
        }
    }

    /**
     * Represas que o usuário pode ver.
     *
     * @return array<int,string>|null null = todas (administrador); [] = nenhuma
     */
    public static function reservoirScope(array $user): ?array
    {
        if (self::isAdmin($user)) {
            return null;
        }

        return Container::users()->reservoirIds((int) $user['id']);
    }

    /** Nome do cargo para exibição. */
    public static function roleLabel(string $role): string
    {
        return self::ROLES[$role] ?? 'Operador';
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
