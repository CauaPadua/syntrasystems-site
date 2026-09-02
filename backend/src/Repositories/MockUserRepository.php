<?php
/**
 * Aquapulse — repositório simulado, exclusivo para desenvolvimento local.
 *
 * Lê um único usuário fixo de backend/storage/mock/users.php. Não há banco,
 * não há escrita e não há cadastro nesta etapa. Será substituído por um
 * PdoUserRepository quando o banco de dados entrar no projeto.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

final class MockUserRepository implements UserRepositoryInterface
{
    /** @var array<int,array{id:int,name:string,email:string,role:string,password_hash:string}>|null */
    private ?array $users = null;

    private string $arquivo;

    public function __construct(?string $arquivo = null)
    {
        $this->arquivo = $arquivo ?? AQ_BACKEND_PATH . '/storage/mock/users.php';
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->all() as $user) {
            if (hash_equals($user['email'], $email)) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        foreach ($this->all() as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    /** Carrega e valida os registros simulados uma única vez por requisição. */
    private function all(): array
    {
        if ($this->users !== null) {
            return $this->users;
        }

        $dados = is_file($this->arquivo) ? require $this->arquivo : [];
        $this->users = [];

        foreach ((array) $dados as $registro) {
            if (!is_array($registro)) {
                continue;
            }

            $obrigatorios = ['id', 'name', 'email', 'role', 'password_hash'];
            foreach ($obrigatorios as $chave) {
                if (!isset($registro[$chave])) {
                    continue 2;
                }
            }

            $this->users[] = [
                'id'            => (int) $registro['id'],
                'name'          => (string) $registro['name'],
                'email'         => mb_strtolower(trim((string) $registro['email']), 'UTF-8'),
                'role'          => (string) $registro['role'],
                'password_hash' => (string) $registro['password_hash'],
            ];
        }

        return $this->users;
    }
}
