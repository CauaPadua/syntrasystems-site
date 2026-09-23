-- =============================================================================
-- 008 — estações pluviométricas e suas medições
--
-- Mesmo desenho de ph_points: cadastro separado das medições. O acumulado de
-- 24 h exibido na tela é somado pelo repositório (SUM das medições horárias das
-- últimas 24 h), não guardado em coluna.
-- =============================================================================

CREATE TABLE rain_stations (
    id           VARCHAR(30)  NOT NULL,                   -- código da estação (ex.: P01)
    reservoir_id VARCHAR(40)  NOT NULL,
    name         VARCHAR(120) NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'online',  -- online | offline | maintenance
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_rainstations_reservoir (reservoir_id, sort_order),
    CONSTRAINT fk_rainstations_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_rainstations_status CHECK (status IN ('online', 'offline', 'maintenance'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rain_station_readings (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    station_id  VARCHAR(30)  NOT NULL,
    rain_mm     DECIMAL(6,2) NOT NULL,                    -- chuva acumulada no intervalo da medição
    recorded_at DATETIME     NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_rainreading_station_time (station_id, recorded_at),
    KEY idx_rainreading_time (recorded_at),
    CONSTRAINT fk_rainreadings_station FOREIGN KEY (station_id) REFERENCES rain_stations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_rainreading_positive CHECK (rain_mm >= 0)   -- chuva negativa não existe
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
