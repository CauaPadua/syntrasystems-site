-- 005 — equipamentos de medição (tabela do CRUD)
--
-- type:   a grandeza que o equipamento mede, com o mesmo nome usado em
--         readings.metric.
-- status: situação cadastral (active, inactive, maintenance). Se o equipamento
--         está "online" não é gravado: é calculado pela hora da última leitura.

CREATE TABLE sensors (
    id           VARCHAR(30)  NOT NULL,                   -- código enviado junto com cada leitura (ex.: SEN-NIV-01)
    reservoir_id VARCHAR(40)  NOT NULL,
    name         VARCHAR(120) NOT NULL,
    location     VARCHAR(120) NOT NULL,
    type         VARCHAR(20)  NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'active',
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_sensors_reservoir (reservoir_id),
    CONSTRAINT fk_sensors_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_sensors_type   CHECK (type IN ('level', 'inflow', 'outflow', 'ph', 'precipitation', 'water_temp')),
    CONSTRAINT ck_sensors_status CHECK (status IN ('active', 'inactive', 'maintenance'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
