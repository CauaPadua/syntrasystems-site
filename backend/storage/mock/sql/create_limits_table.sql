-- Aquapulse — limites operacionais configuráveis (Normal/Atenção/Crítico).
-- Substitui as faixas fixas que hoje estão no código.

CREATE TABLE operational_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_type VARCHAR(20) NOT NULL,      -- 'global', 'company' ou 'reservoir'
    scope_id VARCHAR(40) NULL,            -- id da empresa ou represa; NULL quando scope_type = 'global'
    metric VARCHAR(30) NOT NULL,          -- ex: 'level', 'ph'
    min_value DECIMAL(10,3) NULL,
    max_value DECIMAL(10,3) NULL,
    unit VARCHAR(20) NOT NULL,
    classification VARCHAR(20) NOT NULL,  -- 'normal', 'attention' ou 'critical'
    valid_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valid_to DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    changed_by INT NULL,
    justification TEXT NULL,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);
