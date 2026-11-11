-- 009 — manutenções programadas e chamados abertos pelo site

CREATE TABLE maintenances (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservoir_id   VARCHAR(40)  NOT NULL,
    scheduled_date DATE         NOT NULL,
    equipment      VARCHAR(160) NOT NULL,
    type           VARCHAR(30)  NOT NULL,                 -- Preventiva | Corretiva | Calibração | Chamado
    priority       VARCHAR(20)  NOT NULL DEFAULT 'low',   -- low | attention | critical
    status         VARCHAR(20)  NOT NULL DEFAULT 'scheduled',
    description    VARCHAR(255) NOT NULL DEFAULT '',
    requested_by   INT UNSIGNED NULL,                     -- conta que abriu o chamado (NULL = carga inicial)

    PRIMARY KEY (id),
    KEY idx_maint_reservoir (reservoir_id, scheduled_date),
    CONSTRAINT fk_maint_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_maint_user      FOREIGN KEY (requested_by) REFERENCES users(id)     ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_maint_priority CHECK (priority IN ('low', 'attention', 'critical')),
    CONSTRAINT ck_maint_status   CHECK (status IN ('scheduled', 'done', 'canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
