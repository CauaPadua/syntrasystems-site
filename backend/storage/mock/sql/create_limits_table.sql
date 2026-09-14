-- Aquapulse — limites operacionais configuráveis (Normal/Atenção/Crítico).
-- Substitui as faixas fixas que hoje estão no código.
--
-- Hoje as faixas vivem em backend/src/Services/StatusRules.php (80% / 90% / pH 6,5–8,5);
-- esta tabela permite que elas passem a ser configuradas por empresa ou represa.
-- Nenhum arquivo PHP do projeto lê esta tabela ainda.
-- Dependência: tabela users (coluna changed_by).

CREATE TABLE operational_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_type VARCHAR(20) NOT NULL,      -- 'global', 'company' ou 'reservoir'
    scope_id VARCHAR(40) NULL,            -- id da empresa ou represa; NULL quando scope_type = 'global'
    metric VARCHAR(30) NOT NULL,          -- ex: 'level', 'ph'
    min_value DECIMAL(10,3) NULL,         -- limite inferior da faixa; NULL = sem limite inferior
    max_value DECIMAL(10,3) NULL,         -- limite superior da faixa; NULL = sem limite superior
    unit VARCHAR(20) NOT NULL,            -- unidade dos limites ('%', 'pH'...)
    classification VARCHAR(20) NOT NULL,  -- 'normal', 'attention' ou 'critical'
    valid_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- a partir de quando o limite vale
    valid_to DATETIME NULL,               -- até quando vale; NULL = vigente (mantém histórico de limites antigos)
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    changed_by INT NULL,                  -- usuário que alterou o limite (auditoria)
    justification TEXT NULL,              -- motivo da alteração
    FOREIGN KEY (changed_by) REFERENCES users(id)
);
