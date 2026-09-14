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
    private ?array $users = null;                                        // cache dos usuários lidos; null = arquivo ainda não carregado

    private string $arquivo;                                             // caminho do arquivo PHP que contém os usuários simulados

    /**
     * @param string|null $arquivo permite apontar outro arquivo (útil em testes); por padrão usa storage/mock/users.php
     */
    public function __construct(?string $arquivo = null)
    {
        $this->arquivo = $arquivo ?? AQ_BACKEND_PATH . '/storage/mock/users.php'; // "??" usa o padrão quando nenhum caminho foi informado
    }

    /** Procura o usuário cujo e-mail é igual ao informado. */
    public function findByEmail(string $email): ?array
    {
        foreach ($this->all() as $user) {                                // percorre todos os usuários simulados
            if (hash_equals($user['email'], $email)) {                   // comparação em tempo constante: não revela, pelo tempo, quantos caracteres coincidiram
                return $user;
            }
        }

        return null;                                                     // nenhum e-mail igual
    }

    /** Procura o usuário pelo ID (vindo da sessão). */
    public function findById(int $id): ?array
    {
        foreach ($this->all() as $user) {
            if ($user['id'] === $id) {                                   // comparação estrita entre inteiros
                return $user;
            }
        }

        return null;
    }

    /** Carrega e valida os registros simulados uma única vez por requisição. */
    private function all(): array
    {
        if ($this->users !== null) {                                     // já carregado: evita ler o arquivo de novo
            return $this->users;
        }

        $dados = is_file($this->arquivo) ? require $this->arquivo : [];  // o arquivo PHP faz "return [...]"; sem arquivo, lista vazia
        $this->users = [];

        foreach ((array) $dados as $registro) {                          // percorre cada usuário declarado no arquivo
            if (!is_array($registro)) {                                  // ignora entradas mal formadas
                continue;
            }

            $obrigatorios = ['id', 'name', 'email', 'role', 'password_hash'];
            foreach ($obrigatorios as $chave) {                          // confere se o registro tem todos os campos exigidos pela interface
                if (!isset($registro[$chave])) {
                    continue 2;                                          // falta um campo: pula para o PRÓXIMO registro (sai dos dois loops)
                }
            }

            $this->users[] = [                                           // normaliza tipos e formato, igual ao repositório do banco
                'id'            => (int) $registro['id'],
                'name'          => (string) $registro['name'],
                'email'         => mb_strtolower(trim((string) $registro['email']), 'UTF-8'), // e-mail sempre minúsculo, para bater com o digitado já normalizado
                'role'          => (string) $registro['role'],
                'password_hash' => (string) $registro['password_hash'],
            ];
        }

        return $this->users;
    }
}
