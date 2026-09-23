-- =============================================================================
-- 005 — situação operacional atual de cada represa (1 linha por represa)
--
-- Percentuais de disponibilidade e umidade não são séries temporais no sistema:
-- as telas mostram só o valor corrente. Ficam separados de `reservoirs` porque
-- mudam a cada coleta, enquanto o cadastro da represa é estável.
--
-- Relação 1:1 — a própria chave primária é a chave estrangeira.
-- =============================================================================

CREATE TABLE reservoir_operational_status (
    reservoir_id      VARCHAR(40) NOT NULL,
    gates_online      TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- comportas operando agora
    availability_pct  DECIMAL(5,2) NOT NULL,                -- disponibilidade geral do sistema
    telemetry_pct     DECIMAL(5,2) NOT NULL,                -- disponibilidade da telemetria
    communication_pct DECIMAL(5,2) NOT NULL,                -- disponibilidade dos links
    power_pct         DECIMAL(5,2) NOT NULL,                -- disponibilidade de energia
    humidity_pct      DECIMAL(5,2) NOT NULL,                -- umidade relativa do ar
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (reservoir_id),
    CONSTRAINT fk_opstatus_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_opstatus_pct CHECK (
        availability_pct  BETWEEN 0 AND 100 AND telemetry_pct BETWEEN 0 AND 100 AND
        communication_pct BETWEEN 0 AND 100 AND power_pct     BETWEEN 0 AND 100 AND
        humidity_pct      BETWEEN 0 AND 100
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
