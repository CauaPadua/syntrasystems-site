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

return [                                               // o arquivo devolve uma LISTA de usuários; hoje há apenas um
    [
        'id'            => 1,                        // identificador gravado na sessão após o login ($_SESSION['auth']['user_id'])
        'name'          => 'Ana Silva',              // nome exibido na topbar do dashboard e usado para gerar as iniciais do avatar
        'email'         => 'demo@aquapulse.local',   // login do usuário; comparado já em minúsculas
        'role'          => 'admin',                  // perfil de acesso; a topbar mostra "Operador" quando o valor é admin
        'password_hash' => '$2y$10$4t4v7IbdN0wwnjU5ITX.i.AUA9CkfUd63mZGZKWDO0U3/mhyj9Sd.', // hash bcrypt (password_hash); conferido com password_verify() em AuthService::attempt()
    ],
];
