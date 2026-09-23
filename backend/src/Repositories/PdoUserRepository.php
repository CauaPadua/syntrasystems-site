<?php
/**
 * Aquapulse — repositório de usuários com persistência real (PDO).
 *
 * Implementação de produção da UserRepositoryInterface. Substitui o
 * MockUserRepository quando o banco de dados entra em uso. Nenhuma outra
 * parte do sistema precisa mudar: o AuthService continua dependendo apenas
 * da interface.
 *
 * Tabela usada: users (criada em backend/storage/mock/sql/create_users_table.sql).
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    /** Conexão compartilhada, aberta por Support\Database e entregue pelo Container. */
    private PDO $pdo;

    /**
     * Recebe a conexão já aberta (Support\Database::conexao(), via Container).
     *
     * A propriedade é declarada acima e atribuída aqui, em vez de usar a
     * promoção com "private readonly" no parâmetro: readonly só existe a
     * partir do PHP 8.1, e o projeto precisa rodar também em PHP 8.0.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Procura a conta pelo e-mail digitado no login.
     *
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    public function findByEmail(string $email): ?array
    {
        $emailNormalizado = mb_strtolower(trim($email), 'UTF-8');        // mesma normalização do cadastro: sem espaços e em minúsculas

        // Consulta preparada: o e-mail vai em um parâmetro (:email) separado do
        // texto SQL, então um valor malicioso nunca é interpretado como comando
        // (proteção contra SQL Injection). LIMIT 1 porque o e-mail é único.
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, password_hash
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $emailNormalizado]);                  // envia o valor real do parâmetro :email

        return $this->mapear($stmt->fetch());                            // fetch() devolve a linha encontrada ou false
    }

    /**
     * Procura a conta pelo ID guardado na sessão.
     *
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    public function findById(int $id): ?array
    {
        // Busca pela chave primária da tabela users; no máximo uma linha.
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
     * Converte a linha do banco para o formato exigido pela interface.
     * O MySQL devolve números como string ("7"); aqui os tipos são garantidos.
     *
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    private function mapear(array|false $registro): ?array
    {
        if ($registro === false) {                                       // nenhuma linha encontrada: usuário inexistente
            return null;
        }

        return [
            'id'            => (int) $registro['id'],
            'name'          => (string) $registro['name'],
            'email'         => (string) $registro['email'],
            'role'          => (string) $registro['role'],               // perfil de acesso (ex.: admin)
            'password_hash' => (string) $registro['password_hash'],      // hash bcrypt; nunca sai do servidor (AuthService remove antes de responder)
        ];
    }
}
