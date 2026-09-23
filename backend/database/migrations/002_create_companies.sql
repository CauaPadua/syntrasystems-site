-- =============================================================================
-- 002 — empresas donas das represas
--
-- Lida por PdoMonitoringRepository::companies(); alimenta o filtro "Empresa"
-- da barra de contexto e a validação de ?company_id=.
--
-- A chave primária é o próprio identificador usado nas URLs ('hidrovale'),
-- e não um número: assim o ID que aparece na API é estável e legível.
-- =============================================================================

CREATE TABLE companies (
    id           VARCHAR(40)  NOT NULL,                  -- ex.: 'hidrovale' (slug usado em ?company_id=)
    code         VARCHAR(20)  NOT NULL,                  -- código interno exibido na tela (ex.: HVE-001)
    name         VARCHAR(150) NOT NULL,
    manager      VARCHAR(150) NOT NULL,                  -- responsável pela empresa
    status       VARCHAR(20)  NOT NULL DEFAULT 'active', -- chave do status (define a cor do selo)
    status_label VARCHAR(40)  NOT NULL,                  -- texto exibido ('Ativa')
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,    -- ordem fixa nas listas e filtros da interface
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_code (code),
    CONSTRAINT ck_companies_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
