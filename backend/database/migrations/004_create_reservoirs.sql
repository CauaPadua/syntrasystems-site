-- =============================================================================
-- 004 — represas (cadastro físico)
--
-- Guarda apenas o que é ATRIBUTO da represa: identificação, localização,
-- capacidades e cotas de referência. Valores medidos (nível, vazão, pH...)
-- NÃO ficam aqui: eles vêm da tabela readings (migration 009), e o repositório
-- monta o "estado atual" juntando as duas coisas. Assim uma medição existe em
-- um lugar só e não há risco de o card e o gráfico divergirem.
--
-- DECIMAL(p,e) = p dígitos no total, e deles depois da vírgula.
-- =============================================================================

CREATE TABLE reservoirs (
    id                       VARCHAR(40)  NOT NULL,      -- ex.: 'santa-clara' (slug usado em ?reservoir_id=)
    company_id               VARCHAR(40)  NOT NULL,      -- empresa dona
    code                     VARCHAR(20)  NOT NULL,      -- código exibido na barra de contexto (ex.: RSC-001)
    name                     VARCHAR(150) NOT NULL,
    city                     VARCHAR(120) NOT NULL,      -- município — UF
    basin                    VARCHAR(120) NOT NULL,      -- bacia hidrográfica

    -- localização para o mapa (Leaflet)
    lat                      DECIMAL(9,6)  NOT NULL,     -- 6 casas ≈ precisão de 0,11 m
    lng                      DECIMAL(9,6)  NOT NULL,
    coordinates_label        VARCHAR(60)   NOT NULL,     -- mesma coordenada em graus/minutos/segundos, só para exibição

    -- capacidades (hm³ = milhões de m³)
    capacity_hm3             DECIMAL(10,1) NOT NULL,     -- volume máximo
    useful_volume_hm3        DECIMAL(10,1) NOT NULL,     -- parte utilizável
    technical_reserve_hm3    DECIMAL(10,1) NOT NULL,     -- reserva mínima de segurança
    daily_consumption_hm3    DECIMAL(8,2)  NOT NULL,     -- consumo médio diário
    duration_days            SMALLINT UNSIGNED NOT NULL, -- autonomia estimada no consumo atual
    forecast_reliability_pct TINYINT UNSIGNED NOT NULL,  -- confiabilidade da previsão (%)

    -- cotas de referência (metros acima do nível do mar)
    cota_critical_m          DECIMAL(6,2) NOT NULL,      -- cota do limite crítico
    cota_spill_m             DECIMAL(6,2) NOT NULL,      -- cota de vertimento
    cota_alert_m             DECIMAL(6,2) NOT NULL,      -- cota de alerta operacional

    gates_total              TINYINT UNSIGNED NOT NULL DEFAULT 0, -- comportas instaladas
    sort_order               TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- ordem de exibição; a primeira represa é a aberta por padrão
    status                   VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_reservoirs_code (code),
    KEY idx_reservoirs_company (company_id),             -- acelera "WHERE company_id = ?" (filtro por empresa)
    -- RESTRICT: não se apaga uma empresa que ainda tem represas cadastradas
    CONSTRAINT fk_reservoirs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_reservoirs_capacity CHECK (capacity_hm3 > 0 AND useful_volume_hm3 <= capacity_hm3),
    CONSTRAINT ck_reservoirs_reliab   CHECK (forecast_reliability_pct BETWEEN 0 AND 100),
    CONSTRAINT ck_reservoirs_cotas    CHECK (cota_alert_m < cota_critical_m)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
