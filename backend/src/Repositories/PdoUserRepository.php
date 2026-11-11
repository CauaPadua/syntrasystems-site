<?php
/**
 * Aquapulse — usuários no banco de dados (PDO).
 *
 * Tabelas: users (contas) e user_company_access (empresas que cada conta pode ver).
 * Todas as consultas são preparadas: o valor vai em um parâmetro separado do
 * texto SQL, então nunca é interpretado como comando (proteção contra SQL Injection).
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, status, password_hash
               FROM users
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email), 'UTF-8')]);

        return $this->mapear($stmt->fetch());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, status, password_hash
               FROM users
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $this->mapear($stmt->fetch());
    }

    public function companyIds(int $userId): array
    {
        // Só valem vínculos ativos e ainda não encerrados.
        $stmt = $this->pdo->prepare(
            "SELECT company_id
               FROM user_company_access
              WHERE user_id = :id AND status = 'active' AND ended_at IS NULL
              ORDER BY company_id"
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function registerLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    public function all(): array
    {
        $linhas = $this->pdo->query(
            'SELECT id, name, email, role, status, last_login_at
               FROM users
              ORDER BY name'
        )->fetchAll();

        return array_map(static fn (array $l): array => [
            'id'            => (int) $l['id'],
            'name'          => (string) $l['name'],
            'email'         => (string) $l['email'],
            'role'          => (string) $l['role'],
            'status'        => (string) $l['status'],
            'last_login_at' => $l['last_login_at'] === null ? null : (string) $l['last_login_at'],
        ], $linhas);
    }

    /**
     * Converte a linha do banco para o formato da interface (o MySQL devolve
     * números como texto).
     *
     * @param array<string,mixed>|false $registro
     */
    private function mapear($registro): ?array
    {
        if ($registro === false) {
            return null;
        }

        return [
            'id'            => (int) $registro['id'],
            'name'          => (string) $registro['name'],
            'email'         => (string) $registro['email'],
            'role'          => (string) $registro['role'],
            'status'        => (string) $registro['status'],
            'password_hash' => (string) $registro['password_hash'],
        ];
    }
}
