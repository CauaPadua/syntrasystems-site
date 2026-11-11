-- =============================================================================
-- 016 — equipamentos identificados nas leituras
--
-- Prepara o banco para receber medições enviadas por equipamentos (fluxo
-- simulador -> API -> banco -> painel):
--
--   sensors   o tipo passa a ser a GRANDEZA que o equipamento mede, com o mesmo
--             nome usado em readings.metric. O status passa a ser a situação
--             cadastral (ativo, desativado, em manutenção). "Online/offline"
--             deixa de ser gravado: é calculado pela data da última leitura.
--   readings  cada leitura guarda o equipamento que a enviou (sensor_id). A
--             coluna granularity sai: a tabela guarda só leituras, e os
--             gráficos agrupam por hora, dia ou mês com GROUP BY.
--   alerts    guarda a grandeza e o equipamento que originaram o alerta e o
--             momento em que foi resolvido.
--   reservoirs  cotas de 0% e de 100%, usadas para converter nível em cota.
--   maintenances / reports  campos usados pelos cadastros feitos pelo site.
-- =============================================================================

/* ------------------------------------------------------------ equipamentos */

ALTER TABLE sensors DROP CONSTRAINT ck_sensors_type;
ALTER TABLE sensors DROP CONSTRAINT ck_sensors_status;

UPDATE sensors SET type = 'inflow'  WHERE type = 'flow' AND name LIKE 'Aflu%';
UPDATE sensors SET type = 'outflow' WHERE type = 'flow';
UPDATE sensors SET type = 'precipitation' WHERE type = 'rain';
UPDATE sensors SET type = 'water_temp'    WHERE type = 'temperature';
UPDATE sensors SET status = 'active' WHERE status IN ('online', 'offline');

ALTER TABLE sensors
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active',
    ADD CONSTRAINT ck_sensors_type   CHECK (type IN ('level', 'inflow', 'outflow', 'ph', 'precipitation', 'water_temp')),
    ADD CONSTRAINT ck_sensors_status CHECK (status IN ('active', 'inactive', 'maintenance'));

/* ---------------------------------------------------------------- leituras */

-- A vazão exibida é a afluência: a métrica 'flow' repetia a mesma informação.
DELETE FROM readings WHERE metric = 'flow';

-- Bancos já instalados tinham os mesmos dias gravados duas vezes (uma linha
-- 'day' e várias 'hour'). Fica só a leitura mais detalhada.
DELETE FROM readings
 WHERE granularity = 'day'
   AND recorded_at >= (SELECT inicio
                         FROM (SELECT MIN(recorded_at) AS inicio
                                 FROM readings
                                WHERE granularity = 'hour') AS janela);

ALTER TABLE readings
    ADD COLUMN sensor_id VARCHAR(30) NULL AFTER id,
    ADD UNIQUE KEY uq_reading_instante (reservoir_id, metric, recorded_at),
    ADD KEY idx_reading_sensor (sensor_id, recorded_at),
    -- SET NULL: excluir um equipamento não apaga o histórico da represa
    ADD CONSTRAINT fk_readings_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE readings DROP CONSTRAINT ck_readings_granularity;
ALTER TABLE readings DROP CONSTRAINT ck_readings_metric;

ALTER TABLE readings
    DROP INDEX uq_reading_serie,
    DROP INDEX idx_reading_busca,
    DROP COLUMN granularity,
    ADD CONSTRAINT ck_readings_metric CHECK (metric IN (
        'level', 'cota', 'storage', 'inflow', 'outflow', 'ph', 'precipitation', 'water_temp'
    ));

/* ----------------------------------------------------------------- alertas */

ALTER TABLE alerts
    ADD COLUMN sensor_id   VARCHAR(30) NULL AFTER reservoir_id,
    ADD COLUMN metric      VARCHAR(20) NULL AFTER severity,
    ADD COLUMN resolved_at DATETIME    NULL AFTER detected_at,
    ADD KEY idx_alerts_abertos (reservoir_id, metric, status),
    ADD CONSTRAINT fk_alerts_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE SET NULL ON UPDATE CASCADE;

/* ---------------------------------------------------------------- represas */

ALTER TABLE reservoirs
    ADD COLUMN cota_min_m DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER cota_alert_m,
    ADD COLUMN cota_max_m DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER cota_min_m,
    ADD CONSTRAINT ck_reservoirs_curva CHECK (cota_max_m >= cota_min_m);

/* ------------------------------------------------- manutenções e relatórios */

ALTER TABLE maintenances
    ADD COLUMN description  VARCHAR(255) NULL AFTER priority,
    ADD COLUMN requested_by INT UNSIGNED NULL AFTER description,
    ADD CONSTRAINT fk_maint_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE reports
    ADD COLUMN period_start DATE NULL AFTER period_label,
    ADD COLUMN period_end   DATE NULL AFTER period_start;
