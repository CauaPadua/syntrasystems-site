-- 006 — leituras enviadas pelos equipamentos
--
-- Uma linha = um valor, de uma grandeza, em um instante, enviado por um
-- equipamento. É a origem de todos os gráficos, tabelas e valores atuais.
--
-- Cota e volume não têm equipamento próprio: são calculados a partir do nível
-- e gravados com o mesmo equipamento e o mesmo instante da leitura de nível.

CREATE TABLE readings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sensor_id    VARCHAR(30)  NULL,                       -- NULL se o equipamento for excluído depois
    reservoir_id VARCHAR(40)  NOT NULL,
    metric       VARCHAR(20)  NOT NULL,
    value        DECIMAL(12,3) NOT NULL,
    recorded_at  DATETIME     NOT NULL,                   -- instante da medição (horário de Brasília)

    PRIMARY KEY (id),
    -- o mesmo equipamento não envia duas leituras da mesma grandeza no mesmo instante
    UNIQUE KEY uq_reading (sensor_id, metric, recorded_at),
    -- todas as consultas filtram por represa + grandeza + período
    KEY idx_reading_busca (reservoir_id, metric, recorded_at),
    CONSTRAINT fk_readings_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    -- SET NULL: excluir um equipamento não apaga o histórico da represa
    CONSTRAINT fk_readings_sensor    FOREIGN KEY (sensor_id)    REFERENCES sensors(id)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_readings_metric CHECK (metric IN (
        'level',         -- ocupação (%)
        'cota',          -- altura da água (m)
        'storage',       -- volume (hm³)
        'inflow',        -- vazão que entra (m³/s)
        'outflow',       -- vazão que sai (m³/s)
        'ph',            -- pH
        'precipitation', -- chuva desde a leitura anterior (mm)
        'water_temp'     -- temperatura da água (°C)
    ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
