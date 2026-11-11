-- 010 — limites que classificam as leituras (Normal / Atenção / Crítico)
--
-- Lidos uma vez por requisição e aplicados em todas as telas e na abertura de
-- alertas (Services\StatusRules). Como ler uma linha:
--   attention / critical  valem a partir de min_value;
--   normal com mínimo e máximo é uma faixa ideal — fora dela, atenção.

CREATE TABLE operational_limits (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric         VARCHAR(20)  NOT NULL,                 -- level | ph | precipitation
    classification VARCHAR(20)  NOT NULL,                 -- normal | attention | critical
    min_value      DECIMAL(10,3) NULL,
    max_value      DECIMAL(10,3) NULL,
    unit           VARCHAR(10)  NOT NULL,
    description    VARCHAR(190) NOT NULL DEFAULT '',

    PRIMARY KEY (id),
    UNIQUE KEY uq_limit (metric, classification),
    CONSTRAINT ck_limits_class   CHECK (classification IN ('normal', 'attention', 'critical')),
    CONSTRAINT ck_limits_valores CHECK (min_value IS NOT NULL OR max_value IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
