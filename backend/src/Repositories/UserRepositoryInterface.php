<?php
/**
 * Aquapulse — contrato de acesso a usuários.
 *
 * O AuthService e o Guard dependem apenas desta interface. Implementações:
 *   PdoUserRepository   tabelas users e user_company_access do MySQL
 *   MockUserRepository  arquivo backend/storage/mock/users.php (sem banco)
 * Quem escolhe entre elas é Support\Container::users().
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

interface UserRepositoryInterface
{
    /**
     * Busca pelo e-mail já normalizado (minúsculas, sem espaços).
     *
     * @return array{id:int,name:string,email:string,role:string,status:string,password_hash:string}|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Busca pelo identificador guardado na sessão.
     *
     * @return array{id:int,name:string,email:string,role:string,status:string,password_hash:string}|null
     */
    public function findById(int $id): ?array;

    /**
     * Empresas que o usuário pode consultar (vínculos ativos em user_company_access).
     *
     * @return array<int,string> IDs das empresas; vazio quando não há vínculo
     */
    public function companyIds(int $userId): array;

    /** Registra o momento do login (coluna users.last_login_at). */
    public function registerLogin(int $userId): void;

    /**
     * Todos os usuários, sem o hash da senha — tela de configurações.
     *
     * @return array<int,array{id:int,name:string,email:string,role:string,status:string,last_login_at:?string}>
     */
    public function all(): array;
}
