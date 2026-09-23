-- =============================================================================
-- 012 — eventos operacionais (histórico da tela "Situação operacional")
-- =============================================================================

CREATE TABLE operation_events (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservoir_id VARCHAR(40)  NOT NULL,
    occurred_at  DATETIME     NOT NULL,
    component    VARCHAR(60)  NOT NULL,                   -- parte do sistema (Comportas, Energia, Telemetria...)
    event        VARCHAR(190) NOT NULL,                   -- descrição do que aconteceu
    priority     VARCHAR(20)  NOT NULL DEFAULT 'info',    -- mesma escala de severidade dos alertas
    status       VARCHAR(20)  NOT NULL DEFAULT 'resolved',

    PRIMARY KEY (id),
    KEY idx_events_reservoir_time (reservoir_id, occurred_at), -- lista por represa, das mais recentes para as mais antigas
    CONSTRAINT fk_events_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_events_priority CHECK (priority IN ('critical', 'attention', 'info', 'low')),
    CONSTRAINT ck_events_status   CHECK (status   IN ('new', 'analysis', 'resolved'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
