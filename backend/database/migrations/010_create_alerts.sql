-- =============================================================================
-- 010 — alertas e o histórico de tratamento de cada um
--
-- A linha do tempo do alerta é uma LISTA de etapas: vira tabela própria, com
-- uma linha por etapa, em vez de um texto ou JSON dentro de `alerts`. Assim dá
-- para consultar, contar e ordenar etapas em SQL.
--
-- severity e status ficam em colunas indexadas porque são exatamente os
-- filtros da tela de alertas (?severity=, ?status=, ?reservoir_id=).
-- =============================================================================

CREATE TABLE alerts (
    id               VARCHAR(20)  NOT NULL,               -- código exibido (ex.: ALT-1001)
    reservoir_id     VARCHAR(40)  NOT NULL,
    severity         VARCHAR(20)  NOT NULL,               -- critical | attention | info
    status           VARCHAR(20)  NOT NULL DEFAULT 'new', -- new | analysis | resolved
    title            VARCHAR(150) NOT NULL,               -- o que aconteceu
    metric_label     VARCHAR(80)  NOT NULL,               -- indicador que disparou
    detected_at      DATETIME     NOT NULL,               -- quando foi detectado
    owner            VARCHAR(120) NOT NULL,               -- responsável pelo tratamento
    current_value    VARCHAR(40)  NOT NULL,               -- valor medido, já formatado para exibição
    threshold        VARCHAR(40)  NOT NULL,               -- limite ultrapassado
    detail           VARCHAR(150) NOT NULL DEFAULT '',    -- complemento do valor
    threshold_detail VARCHAR(150) NOT NULL DEFAULT '',    -- complemento do limite
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_alerts_filtros (reservoir_id, severity, status), -- índice composto na ordem dos filtros da tela
    KEY idx_alerts_detected (detected_at),                   -- ordenação "mais recentes primeiro"
    -- RESTRICT: alerta é registro histórico; apagar a represa não pode apagar
    -- silenciosamente o que já foi notificado
    CONSTRAINT fk_alerts_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_alerts_severity CHECK (severity IN ('critical', 'attention', 'info')),
    CONSTRAINT ck_alerts_status   CHECK (status   IN ('new', 'analysis', 'resolved'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alert_timeline (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    alert_id     VARCHAR(20)  NOT NULL,
    step_order   TINYINT UNSIGNED NOT NULL,               -- ordem da etapa dentro do alerta
    happened_at  DATETIME     NULL,                       -- NULL = etapa ainda não realizada
    description  VARCHAR(255) NOT NULL,
    done         TINYINT(1)   NOT NULL DEFAULT 0,         -- 0/1: etapa concluída

    PRIMARY KEY (id),
    UNIQUE KEY uq_timeline_alert_step (alert_id, step_order),
    -- CASCADE: a etapa só existe dentro do alerta
    CONSTRAINT fk_timeline_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    -- coerência: etapa marcada como concluída precisa ter data
    CONSTRAINT ck_timeline_done CHECK (done = 0 OR happened_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
