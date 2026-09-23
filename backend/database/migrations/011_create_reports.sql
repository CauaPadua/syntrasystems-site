-- =============================================================================
-- 011 — relatórios gerados e relatórios agendados
--
-- São duas coisas distintas: o relatório JÁ gerado (com período e autor) e a
-- programação que gera relatórios periodicamente.
-- =============================================================================

CREATE TABLE reports (
    id           VARCHAR(20)  NOT NULL,                   -- ex.: REP-2001
    reservoir_id VARCHAR(40)  NOT NULL,
    name         VARCHAR(150) NOT NULL,
    type         VARCHAR(20)  NOT NULL,                   -- operational | hydrological | quality | planning
    period_label VARCHAR(40)  NOT NULL,                   -- período coberto, como exibido na lista
    generated_at DATETIME     NOT NULL,
    owner        VARCHAR(120) NOT NULL,
    status       VARCHAR(20)  NOT NULL,                   -- done | processing | scheduled
    icon         VARCHAR(30)  NOT NULL DEFAULT 'file-text', -- ícone exibido na lista (includes/icons.php)
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_reports_filtros (reservoir_id, type, status), -- mesma ordem dos filtros da tela de relatórios
    KEY idx_reports_generated (generated_at),
    CONSTRAINT fk_reports_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_reports_type   CHECK (type   IN ('operational', 'hydrological', 'quality', 'planning')),
    CONSTRAINT ck_reports_status CHECK (status IN ('done', 'processing', 'scheduled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheduled_reports (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    frequency  VARCHAR(30)  NOT NULL,                     -- texto exibido na tela (Diária, Mensal)
    next_run   DATETIME     NOT NULL,                     -- próxima execução programada
    enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_scheduled_next (next_run)                     -- "o que roda a seguir" é a consulta natural
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
