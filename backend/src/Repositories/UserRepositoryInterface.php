<?php
/**
 * Aquapulse — contrato de acesso a usuários.
 *
 * PONTO DE TROCA PARA O BANCO DE DADOS.
 *
 * O AuthService depende apenas desta interface. Na etapa do banco, basta criar
 * um PdoUserRepository que a implemente e trocar a instância nos pontos de
 * entrada da API. Nada mais precisa mudar: nem a tela, nem o JavaScript,
 * nem os endpoints, nem o contrato da API, nem o AuthService.
 *
 * Situação atual do código: o PdoUserRepository já existe, e a troca é feita
 * automaticamente em Support\Container::users() (usa o banco quando há DB_HOST).
 * A exceção é api/v1/auth/login.php, que ainda cria MockUserRepository direto.
 *
 * Uma interface define QUAIS métodos uma classe precisa ter, sem dizer COMO.
 * Implementações: MockUserRepository (arquivo PHP) e PdoUserRepository (MySQL).
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

interface UserRepositoryInterface
{
    /**
     * Busca um usuário pelo e-mail já normalizado (minúsculas, sem espaços).
     *
     * O array retornado DEVE conter exatamente estas chaves:
     *   - id            int
     *   - name          string
     *   - email         string
     *   - role          string
     *   - password_hash string  (hash gerado por password_hash())
     *
     * Usado no login (AuthService::attempt) para localizar a conta digitada.
     *
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Busca um usuário pelo identificador. Mesmo formato de findByEmail().
     *
     * Usado a cada requisição autenticada (AuthService::currentUser), com o ID
     * guardado na sessão, para confirmar que o usuário ainda existe.
     *
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    public function findById(int $id): ?array;
}
