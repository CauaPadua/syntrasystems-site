<?php
/**
 * Aquapulse — regras de autenticação.
 *
 * Depende apenas de UserRepositoryInterface: não conhece o repositório
 * simulado nem, futuramente, o banco de dados. Também não imprime nada —
 * quem responde é o ponto de entrada da API.
 *
 * Quem usa:
 *  - api/v1/auth/login.php  -> attempt() + login()
 *  - api/v1/auth/logout.php -> logout()
 *  - api/v1/auth/me.php     -> currentUser()
 *  - Support\Guard          -> currentUser() para proteger páginas e endpoints
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

    private UserRepositoryInterface $users;                              // fonte dos usuários (mock ou banco), recebida de fora

    /** Injeção de dependência: quem cria o serviço escolhe o repositório. */
    public function __construct(UserRepositoryInterface $users)
    {
        $this->users = $users;
    }

    /**
     * Verifica as credenciais.
     *
     * @param string $email    e-mail já normalizado pelo endpoint
     * @param string $password senha em texto puro digitada pelo usuário (nunca é armazenada)
     * @return array{id:int,name:string,email:string,role:string}|null
     *         Dados públicos do usuário, ou null se as credenciais não conferem.
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);                       // 1. localiza a conta pelo e-mail

        if ($user === null) {                                            // 2a. e-mail não cadastrado
            // Consome tempo equivalente a uma verificação real (anti-enumeração).
            password_verify($password, self::DUMMY_HASH);
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {       // 2b. compara a senha digitada com o hash bcrypt salvo (a senha original nunca é guardada)
            return null;                                                 // senha errada: mesmo resultado do e-mail inexistente
        }

        return $this->publicUser($user);                                 // 3. credenciais corretas: devolve só os dados que podem sair do servidor
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
        session_regenerate_id(true);                                     // true = apaga a sessão antiga no servidor; o navegador recebe um cookie com ID novo

        $agora = time();
        $_SESSION['auth'] = [                                            // estrutura lida por aq_start_session() e currentUser()
            'user_id'      => (int) $user['id'],                         // quem está logado
            'started_at'   => $agora,                                    // início da sessão: base do limite absoluto de 12 h
            'last_seen_at' => $agora,                                    // última atividade: base do limite de inatividade de 30 min
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
        $id = $_SESSION['auth']['user_id'] ?? null;                      // ID gravado por login(); ausente quando ninguém está logado

        if (!is_int($id) && !is_numeric($id)) {                          // sessão vazia ou valor corrompido: não autenticado
            return null;
        }

        $user = $this->users->findById((int) $id);                       // relê o usuário na fonte de dados a cada requisição

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
        aq_destroy_session();                                            // função global definida em backend/bootstrap.php (limpa dados e cookie)
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
            'name'  => (string) $user['name'],                           // exibido na topbar do dashboard
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],                           // perfil; a topbar mostra "Operador" quando é "admin"
        ];
    }
}
