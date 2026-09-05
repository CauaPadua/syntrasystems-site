<?php
/**
 * Aquapulse — usuário simulado para desenvolvimento local.
 *
 * ATENÇÃO
 *  - Este arquivo NÃO é um banco de dados e não deve ser usado em produção.
 *  - Apenas o hash é armazenado aqui; a senha pura nunca aparece no código.
 *  - Não há cadastro, edição nem remoção nesta etapa: os dados são somente leitura.
 *  - Na etapa do banco, este arquivo é descartado junto com o MockUserRepository.
 *
 * Credencial exclusivamente local (documentada em docs/api-contract.md):
 *   e-mail: demo@aquapulse.local
 *   senha:  definida na documentação local — o hash abaixo foi gerado com password_hash()
 */

declare(strict_types=1);

return [
    [
        'id'            => 1,
        'name'          => 'Ana Silva',
        'email'         => 'demo@aquapulse.local',
        'role'          => 'admin',
        'password_hash' => '$2y$10$4t4v7IbdN0wwnjU5ITX.i.AUA9CkfUd63mZGZKWDO0U3/mhyj9Sd.',
    ],
];
