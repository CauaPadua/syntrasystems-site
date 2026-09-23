-- =============================================================================
-- 013 — manutenções programadas por represa
-- =============================================================================

CREATE TABLE maintenances (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservoir_id   VARCHAR(40)  NOT NULL,
    scheduled_date DATE         NOT NULL,                 -- DATE: a tela mostra só o dia, sem horário
    equipment      VARCHAR(120) NOT NULL,
    type           VARCHAR(30)  NOT NULL,                 -- Preventiva | Corretiva | Calibração
    priority       VARCHAR(20)  NOT NULL DEFAULT 'low',
    status         VARCHAR(20)  NOT NULL DEFAULT 'scheduled',

    PRIMARY KEY (id),
    KEY idx_maint_reservoir_date (reservoir_id, scheduled_date), -- "próximas manutenções desta represa"
    CONSTRAINT fk_maint_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_maint_priority CHECK (priority IN ('critical', 'attention', 'info', 'low')),
    CONSTRAINT ck_maint_status   CHECK (status   IN ('scheduled', 'done', 'canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
