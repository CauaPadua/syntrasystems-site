<?php
/**
 * Aquapulse — usuários do modo demonstração (sem banco).
 *
 * Lê backend/storage/mock/users.php. É somente leitura: não há cadastro,
 * vínculo com empresa nem registro de login.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

final class MockUserRepository implements UserRepositoryInterface
{
    /** @var array<int,array<string,mixed>>|null */
    private ?array $users = null;

    private string $arquivo;

    public function __construct(?string $arquivo = null)
    {
        $this->arquivo = $arquivo ?? AQ_BACKEND_PATH . '/storage/mock/users.php';
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->carregar() as $user) {
            if (hash_equals($user['email'], $email)) {
                return $user;
            }
        }
        return null;
    }

    public function findById(int $id): ?array
    {
        foreach ($this->carregar() as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    /** Sem banco não há vínculos: o usuário de demonstração é administrador e vê tudo. */
    public function companyIds(int $userId): array
    {
        return [];
    }

    public function registerLogin(int $userId): void
    {
        // nada a gravar no modo demonstração
    }

    public function all(): array
    {
        return array_map(static fn (array $u): array => [
            'id'            => $u['id'],
            'name'          => $u['name'],
            'email'         => $u['email'],
            'role'          => $u['role'],
            'status'        => $u['status'],
            'last_login_at' => null,
        ], $this->carregar());
    }

    /** Carrega e normaliza os registros uma única vez por requisição. */
    private function carregar(): array
    {
        if ($this->users !== null) {
            return $this->users;
        }

        $dados = is_file($this->arquivo) ? require $this->arquivo : [];
        $this->users = [];

        foreach ((array) $dados as $registro) {
            if (!is_array($registro) || !isset($registro['id'], $registro['name'], $registro['email'], $registro['role'], $registro['password_hash'])) {
                continue;                                     // registro incompleto é ignorado
            }

            $this->users[] = [
                'id'            => (int) $registro['id'],
                'name'          => (string) $registro['name'],
                'email'         => mb_strtolower(trim((string) $registro['email']), 'UTF-8'),
                'role'          => (string) $registro['role'],
                'status'        => (string) ($registro['status'] ?? 'active'),
                'password_hash' => (string) $registro['password_hash'],
            ];
        }

        return $this->users;
    }
}
