-- 007 — alertas e a linha do tempo de cada um
--
-- Os alertas são abertos pelas leituras (Services\AlertService): quando um
-- valor sai da faixa normal e não há alerta em aberto daquela grandeza na
-- represa. Assumir e resolver acrescentam etapas em alert_timeline.

CREATE TABLE alerts (
    id               VARCHAR(20)  NOT NULL,               -- ex.: ALT-1001
    reservoir_id     VARCHAR(40)  NOT NULL,
    sensor_id        VARCHAR(30)  NULL,                   -- equipamento que enviou a leitura
    metric           VARCHAR(20)  NOT NULL,               -- grandeza (level, ph, precipitation...)
    severity         VARCHAR(20)  NOT NULL,               -- attention | critical
    status           VARCHAR(20)  NOT NULL DEFAULT 'new', -- new | analysis | resolved
    title            VARCHAR(150) NOT NULL,
    metric_label     VARCHAR(80)  NOT NULL,
    current_value    VARCHAR(40)  NOT NULL,               -- já formatado para exibição ("82,4%")
    threshold        VARCHAR(40)  NOT NULL,
    detail           VARCHAR(150) NOT NULL DEFAULT '',
    threshold_detail VARCHAR(150) NOT NULL DEFAULT '',
    owner            VARCHAR(120) NOT NULL,               -- responsável pelo tratamento
    detected_at      DATETIME     NOT NULL,
    resolved_at      DATETIME     NULL,

    PRIMARY KEY (id),
    KEY idx_alerts_abertos (reservoir_id, metric, status),
    KEY idx_alerts_detected (detected_at),
    -- RESTRICT: alerta é registro histórico; não some ao apagar a represa
    CONSTRAINT fk_alerts_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_alerts_sensor    FOREIGN KEY (sensor_id)    REFERENCES sensors(id)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_alerts_severity CHECK (severity IN ('attention', 'critical')),
    CONSTRAINT ck_alerts_status   CHECK (status IN ('new', 'analysis', 'resolved'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alert_timeline (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    alert_id    VARCHAR(20)  NOT NULL,
    step_order  TINYINT UNSIGNED NOT NULL,
    happened_at DATETIME     NOT NULL,
    description VARCHAR(255) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_timeline_step (alert_id, step_order),
    CONSTRAINT fk_timeline_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
