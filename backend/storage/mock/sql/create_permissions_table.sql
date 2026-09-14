-- Aquapulse — vínculo de usuário com empresa e suas permissões.
-- Um usuário pode ter acesso a mais de uma empresa, com permissões
-- e função diferentes em cada uma.
--
-- Dependências: precisa das tabelas users e companies já criadas (chaves estrangeiras).
-- Observação: nenhum arquivo PHP do projeto lê esta tabela ainda; ela prepara o
-- controle de acesso por empresa para uma etapa futura.

CREATE TABLE user_company_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                                  -- qual usuário recebe o acesso
    company_id VARCHAR(40) NOT NULL,                       -- a qual empresa ele passa a ter acesso
    role VARCHAR(50) NOT NULL,                             -- função do usuário DENTRO desta empresa (pode variar entre empresas)
    status VARCHAR(20) NOT NULL DEFAULT 'active',          -- permite suspender um acesso sem apagar o histórico
    permissions JSON NOT NULL,                             -- lista flexível de permissões (ex.: ver relatórios, editar limites)
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- início do acesso
    ended_at DATETIME NULL,                                -- fim do acesso; NULL = ainda vigente
    granted_by INT NULL,                                   -- usuário que concedeu o acesso (auditoria); NULL se foi carga inicial
    FOREIGN KEY (user_id) REFERENCES users(id),            -- garante que o usuário exista
    FOREIGN KEY (company_id) REFERENCES companies(id),     -- garante que a empresa exista
    FOREIGN KEY (granted_by) REFERENCES users(id),         -- quem concedeu também precisa ser um usuário existente
    UNIQUE KEY uniq_user_empresa (user_id, company_id)     -- no máximo um vínculo por par usuário+empresa
);
