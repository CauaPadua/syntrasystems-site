-- 004 — represas (cadastro)
--
-- Só o que é atributo fixo da represa. Os valores medidos (nível, vazão, pH...)
-- ficam na tabela readings.
--
-- cota_min_m e cota_max_m são as cotas de 0% e de 100% de ocupação: com elas o
-- sistema converte o nível medido (%) em cota (m).

CREATE TABLE reservoirs (
    id                    VARCHAR(40)   NOT NULL,
    company_id            VARCHAR(40)   NOT NULL,
    code                  VARCHAR(20)   NOT NULL,
    name                  VARCHAR(150)  NOT NULL,
    city                  VARCHAR(120)  NOT NULL,
    basin                 VARCHAR(120)  NOT NULL,
    lat                   DECIMAL(9,6)  NOT NULL,
    lng                   DECIMAL(9,6)  NOT NULL,
    coordinates_label     VARCHAR(60)   NOT NULL,         -- mesma coordenada em graus/minutos/segundos

    capacity_hm3          DECIMAL(10,1) NOT NULL,         -- volume máximo (hm³ = milhões de m³)
    useful_volume_hm3     DECIMAL(10,1) NOT NULL,
    technical_reserve_hm3 DECIMAL(10,1) NOT NULL,         -- reserva mínima de segurança
    daily_consumption_hm3 DECIMAL(8,2)  NOT NULL,

    cota_min_m            DECIMAL(6,2)  NOT NULL,         -- cota com 0% de ocupação
    cota_max_m            DECIMAL(6,2)  NOT NULL,         -- cota com 100% de ocupação
    cota_alert_m          DECIMAL(6,2)  NOT NULL,         -- cota de alerta operacional
    cota_spill_m          DECIMAL(6,2)  NOT NULL,         -- cota de vertimento

    sort_order            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_reservoirs_code (code),
    KEY idx_reservoirs_company (company_id),
    -- RESTRICT: não se apaga uma empresa que ainda tem represas
    CONSTRAINT fk_reservoirs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_reservoirs_capacity CHECK (capacity_hm3 > 0 AND useful_volume_hm3 <= capacity_hm3),
    CONSTRAINT ck_reservoirs_curva    CHECK (cota_max_m > cota_min_m)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
