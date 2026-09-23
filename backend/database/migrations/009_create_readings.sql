-- =============================================================================
-- 009 — leituras dos sensores da represa (a tabela central do monitoramento)
--
-- É a origem de TODOS os gráficos, das tabelas de "últimas leituras" e dos
-- valores atuais mostrados nos cards. Uma linha = uma métrica, em um instante,
-- de uma represa.
--
-- `granularity` guarda duas resoluções na mesma tabela:
--   'hour' → leituras horárias, mantidas para as últimas 48 h (gráfico de 24 h
--            e tabelas de últimas leituras);
--   'day'  → um valor consolidado por dia, mantido por ~13 meses (gráficos de
--            7, 30 e 90 dias e o de 12 meses, que agrupa por mês).
-- Sem essa separação seria preciso guardar 13 meses de dados horários (~9.500
-- linhas por métrica e represa) para desenhar gráficos que mostram médias.
--
-- O índice cobre exatamente o filtro das consultas (represa + métrica +
-- resolução + intervalo de tempo), que é como o repositório sempre busca.
-- =============================================================================

CREATE TABLE readings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, -- BIGINT porque a tabela cresce indefinidamente
    reservoir_id VARCHAR(40)  NOT NULL,
    metric       VARCHAR(20)  NOT NULL,                   -- grandeza medida (ver CHECK abaixo)
    granularity  VARCHAR(5)   NOT NULL DEFAULT 'hour',    -- 'hour' ou 'day'
    value        DECIMAL(12,3) NOT NULL,                  -- valor medido, na unidade da métrica
    recorded_at  DATETIME     NOT NULL,                   -- instante da medição (horário de Brasília)
    quality      VARCHAR(10)  NOT NULL DEFAULT 'valid',   -- permite marcar leitura suspeita sem apagá-la

    PRIMARY KEY (id),
    -- a mesma métrica não pode ter dois valores para o mesmo instante e resolução;
    -- também torna o seed reexecutável (INSERT ... ON DUPLICATE KEY UPDATE)
    UNIQUE KEY uq_reading_serie (reservoir_id, metric, granularity, recorded_at),
    KEY idx_reading_busca (reservoir_id, metric, granularity, recorded_at),
    CONSTRAINT fk_readings_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_readings_metric CHECK (metric IN (
        'level',          -- ocupação do reservatório (%)
        'cota',           -- altura da lâmina d'água (m)
        'flow',           -- vazão (m³/s)
        'inflow',         -- afluência (m³/s)
        'outflow',        -- defluência (m³/s)
        'ph',             -- pH da água
        'storage',        -- volume armazenado (hm³)
        'precipitation',  -- chuva acumulada no intervalo (mm)
        'water_temp'      -- temperatura da água (°C)
    )),
    CONSTRAINT ck_readings_granularity CHECK (granularity IN ('hour', 'day')),
    CONSTRAINT ck_readings_quality     CHECK (quality IN ('valid', 'suspect', 'invalid'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
