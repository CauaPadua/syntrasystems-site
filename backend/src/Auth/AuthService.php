<?php
/**
 * Aquapulse — regras de autenticação.
 *
 * Depende apenas de UserRepositoryInterface: não conhece o repositório
 * simulado nem, futuramente, o banco de dados. Também não imprime nada —
 * quem responde é o ponto de entrada da API.
 */

declare(strict_types=1);

namespace Aquapulse\Auth;

use Aquapulse\Repositories\UserRepositoryInterface;

final class AuthService
{
    /**
     * Hash descartável usado para igualar o tempo de resposta quando o e-mail
     * não existe. Sem isso, a diferença de tempo revelaria quais e-mails estão
     * cadastrados.
     */
    private const DUMMY_HASH = '$2y$10$usuarioInexistenteUsuarioInexistenteUsuarioInexistenteUsuar';

    private UserRepositoryInterface $users;

    public function __construct(UserRepositoryInterface $users)
    {
        $this->users = $users;
    }

    /**
     * Verifica as credenciais.
     *
     * @return array{id:int,name:string,email:string,role:string}|null
     *         Dados públicos do usuário, ou null se as credenciais não conferem.
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            // Consome tempo equivalente a uma verificação real (anti-enumeração).
            password_verify($password, self::DUMMY_HASH);
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $this->publicUser($user);
    }

    /**
     * Registra o usuário na sessão.
     *
     * Guarda apenas o identificador e os carimbos de tempo: nome, e-mail e
     * função são relidos do repositório a cada consulta.
     */
    public function login(array $user): void
    {
        // Impede fixação de sessão: o identificador anterior é invalidado.
        session_regenerate_id(true);

        $agora = time();
        $_SESSION['auth'] = [
            'user_id'      => (int) $user['id'],
            'started_at'   => $agora,
            'last_seen_at' => $agora,
        ];
    }

    /** Existe uma sessão autenticada válida? */
    public function check(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * Usuário da sessão atual.
     *
     * @return array{id:int,name:string,email:string,role:string}|null
     */
    public function currentUser(): ?array
    {
        $id = $_SESSION['auth']['user_id'] ?? null;

        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        $user = $this->users->findById((int) $id);

        if ($user === null) {
            // O usuário deixou de existir: a sessão não vale mais.
            $this->logout();
            return null;
        }

        return $this->publicUser($user);
    }

    /** Encerra a sessão autenticada. */
    public function logout(): void
    {
        aq_destroy_session();
    }

    /**
     * Remove tudo que não pode sair do servidor — em especial o hash da senha.
     *
     * @return array{id:int,name:string,email:string,role:string}
     */
    private function publicUser(array $user): array
    {
        return [
            'id'    => (int) $user['id'],
            'name'  => (string) $user['name'],
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],
        ];
    }
}
