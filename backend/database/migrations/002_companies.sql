-- 002 — empresas donas das represas
--
-- O id é o próprio identificador usado nas URLs (?company_id=hidrovale).

CREATE TABLE companies (
    id           VARCHAR(40)  NOT NULL,
    code         VARCHAR(20)  NOT NULL,
    name         VARCHAR(150) NOT NULL,
    manager      VARCHAR(150) NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'active',
    status_label VARCHAR(40)  NOT NULL,                   -- texto exibido ("Ativa")
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_code (code),
    CONSTRAINT ck_companies_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
