-- =============================================================================
-- 014 — limites operacionais (faixas Normal / Atenção / Crítico)
--
-- Tira do código os números que classificam cada indicador. Hoje eles também
-- existem em backend/src/Services/StatusRules.php; esta tabela é a fonte lida
-- pelo endpoint de configurações (limites exibidos na tela) e permite, no
-- futuro, limites diferentes por empresa ou por represa sem alterar código.
--
-- Histórico: em vez de sobrescrever um limite, encerra-se o anterior
-- (valid_to preenchido) e insere-se outro. Fica registrado o que valia em cada
-- época, e por quê (justification).
-- =============================================================================

CREATE TABLE operational_limits (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope_type     VARCHAR(20)  NOT NULL DEFAULT 'global', -- global | company | reservoir
    scope_id       VARCHAR(40)  NULL,                      -- id da empresa/represa; NULL quando global
    metric         VARCHAR(20)  NOT NULL,                  -- level | ph | precipitation...
    classification VARCHAR(20)  NOT NULL,                  -- normal | attention | critical
    min_value      DECIMAL(10,3) NULL,                     -- limite inferior; NULL = sem mínimo
    max_value      DECIMAL(10,3) NULL,                     -- limite superior; NULL = sem máximo
    unit           VARCHAR(10)  NOT NULL,                  -- unidade dos limites (%, pH, mm)
    valid_from     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valid_to       DATETIME     NULL,                      -- NULL = limite vigente
    changed_by     INT UNSIGNED NULL,                      -- auditoria: quem alterou
    justification  VARCHAR(255) NULL,

    PRIMARY KEY (id),
    KEY idx_limits_vigentes (metric, scope_type, valid_to), -- busca "limites vigentes desta métrica"
    CONSTRAINT fk_limits_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_limits_scope   CHECK (scope_type IN ('global', 'company', 'reservoir')),
    CONSTRAINT ck_limits_class   CHECK (classification IN ('normal', 'attention', 'critical')),
    -- pelo menos um dos dois lados da faixa precisa estar preenchido
    CONSTRAINT ck_limits_valores CHECK (min_value IS NOT NULL OR max_value IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
