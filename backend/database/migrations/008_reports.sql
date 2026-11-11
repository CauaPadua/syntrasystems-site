-- 008 — relatórios gerados
--
-- Guarda o pedido (represa, tipo e período). O arquivo CSV é montado a partir
-- das leituras a cada download (Services\ReportService).

CREATE TABLE reports (
    id           VARCHAR(20)  NOT NULL,                   -- ex.: REP-2001
    reservoir_id VARCHAR(40)  NOT NULL,
    type         VARCHAR(20)  NOT NULL,                   -- operational | hydrological | quality
    name         VARCHAR(150) NOT NULL,
    icon         VARCHAR(30)  NOT NULL DEFAULT 'file-text',
    period_label VARCHAR(40)  NOT NULL,                   -- "05/11/2026 – 11/11/2026"
    period_start DATE         NOT NULL,
    period_end   DATE         NOT NULL,
    owner        VARCHAR(120) NOT NULL,                   -- quem gerou
    generated_at DATETIME     NOT NULL,

    PRIMARY KEY (id),
    KEY idx_reports_reservoir (reservoir_id, type),
    CONSTRAINT fk_reports_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_reports_type    CHECK (type IN ('operational', 'hydrological', 'quality')),
    CONSTRAINT ck_reports_periodo CHECK (period_end >= period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
