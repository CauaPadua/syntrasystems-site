-- =============================================================================
-- 015 — configurações do sistema (tela "Configurações", somente leitura)
--
-- Três tabelas pequenas em vez de um JSON único, porque são três listas com
-- naturezas diferentes:
--   system_settings      → pares chave/valor (unidades, intervalo de atualização)
--   setting_indicators   → quais indicadores aparecem nos painéis
--   notification_channels→ para onde os alertas são enviados
-- =============================================================================

CREATE TABLE system_settings (
    setting_key   VARCHAR(60)  NOT NULL,                  -- ex.: units.level, refresh_interval
    setting_value VARCHAR(190) NOT NULL,                  -- valor sempre em texto
    value_type    VARCHAR(10)  NOT NULL DEFAULT 'string', -- string | bool | int — diz como converter na leitura
    description   VARCHAR(190) NOT NULL DEFAULT '',
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (setting_key),
    CONSTRAINT ck_settings_type CHECK (value_type IN ('string', 'bool', 'int', 'float'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE setting_indicators (
    id         VARCHAR(30)  NOT NULL,                     -- level, flow, ph, storage, precipitation, duration
    label      VARCHAR(80)  NOT NULL,
    enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_channels (
    id         VARCHAR(30)  NOT NULL,                     -- email, panel, sms...
    label      VARCHAR(80)  NOT NULL,
    target     VARCHAR(190) NOT NULL,                     -- destino (endereço de e-mail, painel...)
    enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
