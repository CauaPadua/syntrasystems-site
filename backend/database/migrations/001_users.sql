-- 001 — contas de acesso
--
-- role:   admin (vê todas as empresas e cadastra equipamentos)
--         operator (vê só as empresas vinculadas a ele)
-- status: active | suspended (conta suspensa não entra no sistema)

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'operator',
    status        VARCHAR(20)  NOT NULL DEFAULT 'active',
    password_hash VARCHAR(255) NOT NULL,                  -- hash de password_hash(); a senha nunca é gravada
    last_login_at DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT ck_users_role   CHECK (role IN ('admin', 'operator')),
    CONSTRAINT ck_users_status CHECK (status IN ('active', 'suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
