<?php
/**
 * Aquapulse — repositório de usuários com persistência real (PDO).
 *
 * Implementação de produção da UserRepositoryInterface. Substitui o
 * MockUserRepository quando o banco de dados entra em uso. Nenhuma outra
 * parte do sistema precisa mudar: o AuthService continua dependendo apenas
 * da interface.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $emailNormalizado = mb_strtolower(trim($email), 'UTF-8');

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, password_hash
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $emailNormalizado]);

        return $this->mapear($stmt->fetch());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, password_hash
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $this->mapear($stmt->fetch());
    }

    /**
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    private function mapear(array|false $registro): ?array
    {
        if ($registro === false) {
            return null;
        }

        return [
            'id'            => (int) $registro['id'],
            'name'          => (string) $registro['name'],
            'email'         => (string) $registro['email'],
            'role'          => (string) $registro['role'],
            'password_hash' => (string) $registro['password_hash'],
        ];
    }
}
