<?php
/**
 * Aquapulse — regras de autenticação.
 *
 * Depende apenas de UserRepositoryInterface e não imprime nada: quem responde
 * é o ponto de entrada da API.
 *
 * Quem usa:
 *   api/v1/auth/login.php   attempt() + login()
 *   api/v1/auth/logout.php  logout()
 *   api/v1/auth/me.php      currentUser()
 *   Support\Guard           currentUser(), para proteger páginas e endpoints
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
     * Confere e-mail e senha.
     *
     * Devolve o usuário mesmo quando a conta está suspensa: o endpoint de login
     * decide a resposta. Assim "conta suspensa" só é informado a quem digitou a
     * senha correta, e a API continua sem revelar quais e-mails existem.
     *
     * @return array{id:int,name:string,email:string,role:string,status:string}|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_HASH);
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $this->publicUser($user);
    }

    /**
     * Registra o usuário na sessão. Guarda só o identificador e os horários:
     * nome, perfil e status são relidos do repositório a cada requisição.
     */
    public function login(array $user): void
    {
        session_regenerate_id(true);                         // impede fixação de sessão

        $agora = time();
        $_SESSION['auth'] = [
            'user_id'      => (int) $user['id'],
            'started_at'   => $agora,
            'last_seen_at' => $agora,
        ];

        $this->users->registerLogin((int) $user['id']);
    }

    /**
     * Usuário da sessão atual, ou null quando não há sessão válida.
     *
     * A conta é relida a cada requisição: se foi excluída ou suspensa depois
     * do login, a sessão deixa de valer imediatamente.
     *
     * @return array{id:int,name:string,email:string,role:string,status:string}|null
     */
    public function currentUser(): ?array
    {
        $id = $_SESSION['auth']['user_id'] ?? null;

        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        $user = $this->users->findById((int) $id);

        if ($user === null || $user['status'] !== 'active') {
            $this->logout();
            return null;
        }

        return $this->publicUser($user);
    }

    public function logout(): void
    {
        aq_destroy_session();
    }

    /**
     * Remove o que não pode sair do servidor — em especial o hash da senha.
     *
     * @return array{id:int,name:string,email:string,role:string,status:string}
     */
    private function publicUser(array $user): array
    {
        return [
            'id'     => (int) $user['id'],
            'name'   => (string) $user['name'],
            'email'  => (string) $user['email'],
            'role'   => (string) $user['role'],
            'status' => (string) $user['status'],
        ];
    }
}
