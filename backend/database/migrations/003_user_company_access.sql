-- 003 — quais empresas cada conta pode consultar
--
-- Relação N:N entre users e companies. Um operador só enxerga as empresas
-- listadas aqui; o administrador enxerga todas.

CREATE TABLE user_company_access (
    user_id    INT UNSIGNED NOT NULL,
    company_id VARCHAR(40)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, company_id),
    CONSTRAINT fk_access_user    FOREIGN KEY (user_id)    REFERENCES users(id)     ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_access_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
