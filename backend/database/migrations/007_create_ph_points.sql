-- =============================================================================
-- 007 — pontos de coleta de pH e suas medições
--
-- Duas tabelas, porque são duas coisas diferentes:
--   ph_points          → o ponto físico de coleta (cadastro, muda raramente)
--   ph_point_readings  → cada medição feita naquele ponto (cresce sempre)
--
-- O valor "atual" de cada ponto NÃO é uma coluna do cadastro: o repositório
-- busca a medição mais recente de cada ponto. É o que evita ter o mesmo número
-- guardado em dois lugares e ficando desatualizado em um deles.
--
-- Estas medições são as coletas por ponto (campanha manual). A série de pH que
-- aparece nos gráficos é a leitura do sensor da represa, registrada em
-- `readings` com metric = 'ph' (migration 009) — origens diferentes.
-- =============================================================================

CREATE TABLE ph_points (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservoir_id VARCHAR(40)  NOT NULL,
    name         VARCHAR(120) NOT NULL,                   -- ex.: 'Entrada principal'
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_phpoint_reservoir_name (reservoir_id, name), -- o mesmo ponto não pode ser cadastrado duas vezes
    CONSTRAINT fk_phpoints_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ph_point_readings (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ph_point_id   INT UNSIGNED NOT NULL,
    ph            DECIMAL(4,2) NOT NULL,                  -- 0,00 a 14,00
    water_temp_c  DECIMAL(4,1) NOT NULL,                  -- temperatura no momento da coleta
    recorded_at   DATETIME     NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_phreading_point_time (ph_point_id, recorded_at), -- uma medição por ponto e instante (reexecutar o seed não duplica)
    KEY idx_phreading_time (recorded_at),
    CONSTRAINT fk_phreadings_point FOREIGN KEY (ph_point_id) REFERENCES ph_points(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_phreading_range CHECK (ph BETWEEN 0 AND 14)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
