-- =============================================================================
-- 006 — sensores instalados em cada represa
--
-- Consumido por PdoMonitoringRepository::sensors() (tela de vazão) e pelo
-- cálculo de sensores online/total do cabeçalho das telas: o repositório conta
-- as linhas desta tabela em vez de guardar o total em uma coluna da represa.
-- =============================================================================

CREATE TABLE sensors (
    id           VARCHAR(30)  NOT NULL,                   -- código do equipamento (ex.: SEN-VAZ-01)
    reservoir_id VARCHAR(40)  NOT NULL,
    name         VARCHAR(120) NOT NULL,                   -- ex.: 'Afluência principal'
    location     VARCHAR(120) NOT NULL,                   -- onde está instalado
    type         VARCHAR(20)  NOT NULL,                   -- grandeza medida
    status       VARCHAR(20)  NOT NULL DEFAULT 'online',  -- online | offline | maintenance
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,     -- ordem fixa de exibição na tabela
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_sensors_reservoir (reservoir_id, sort_order),
    -- CASCADE: o sensor é parte da represa; sem ela o registro não faz sentido
    CONSTRAINT fk_sensors_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_sensors_status CHECK (status IN ('online', 'offline', 'maintenance')),
    CONSTRAINT ck_sensors_type   CHECK (type IN ('flow', 'level', 'ph', 'rain', 'temperature'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
