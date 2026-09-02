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
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Busca um usuário pelo identificador. Mesmo formato de findByEmail().
     *
     * @return array{id:int,name:string,email:string,role:string,password_hash:string}|null
     */
    public function findById(int $id): ?array;
}
