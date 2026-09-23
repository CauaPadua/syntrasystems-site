-- =============================================================================
-- 003 — vínculo entre usuário e empresa (quem enxerga o quê)
--
-- Um usuário pode ter acesso a várias empresas, com função diferente em cada
-- uma. A aplicação ainda NÃO filtra por este vínculo (todo usuário autenticado
-- vê todas as empresas); a tabela prepara esse controle e já é populada pelo
-- seed, para que a regra possa ser ligada sem migração de dados.
--
-- ON DELETE: apagar o usuário apaga seus acessos (CASCADE — o vínculo não
-- existe sem ele); quem concedeu vira NULL (SET NULL), preservando a linha.
-- =============================================================================

CREATE TABLE user_company_access (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    company_id  VARCHAR(40)  NOT NULL,
    role        VARCHAR(30)  NOT NULL,                   -- função DENTRO desta empresa
    status      VARCHAR(20)  NOT NULL DEFAULT 'active',  -- suspende o acesso sem apagar o histórico
    permissions JSON         NOT NULL,                   -- lista de permissões (ex.: ["reports.read"])
    started_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at    DATETIME     NULL,                       -- NULL = vínculo vigente
    granted_by  INT UNSIGNED NULL,                       -- auditoria: quem concedeu

    PRIMARY KEY (id),
    UNIQUE KEY uq_access_user_company (user_id, company_id), -- no máximo um vínculo por par
    KEY idx_access_company (company_id),
    CONSTRAINT fk_access_user    FOREIGN KEY (user_id)    REFERENCES users(id)     ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_access_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_access_granted FOREIGN KEY (granted_by) REFERENCES users(id)     ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_access_status  CHECK (status IN ('active', 'suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
