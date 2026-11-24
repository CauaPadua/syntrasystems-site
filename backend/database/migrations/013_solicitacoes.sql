-- 013 — pedidos feitos por quem ainda não tem conta
--
-- type:   access  colaborador de uma represa já monitorada, pedindo acesso
--         demo    dono ou responsável por uma represa, pedindo contato
-- status: pending   aguardando o administrador
--         approved  acesso criado (access) ou contato feito (demo)
--         rejected  recusado, com o motivo em decision_note
--
-- Empresa e represa são texto livre: quem pede não vê a lista de clientes. Quem
-- liga a conta à represa certa é o administrador, ao aprovar.

CREATE TABLE IF NOT EXISTS requests (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type           VARCHAR(10)  NOT NULL,
    status         VARCHAR(10)  NOT NULL DEFAULT 'pending',

    name           VARCHAR(120) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    phone          VARCHAR(30)  NULL,                     -- só demonstração
    registration   VARCHAR(40)  NULL,                     -- matrícula na empresa (só acesso)
    company_name   VARCHAR(120) NOT NULL,
    reservoir_name VARCHAR(120) NOT NULL,
    city           VARCHAR(120) NULL,                     -- só demonstração
    manager_name   VARCHAR(120) NULL,                     -- gestor direto (só acesso)
    message        VARCHAR(500) NOT NULL DEFAULT '',      -- justificativa ou mensagem
    password_hash  VARCHAR(255) NULL,                     -- só acesso; apagado quando o pedido é aprovado

    decided_by     INT UNSIGNED NULL,                     -- administrador que respondeu
    decided_at     DATETIME     NULL,
    decision_note  VARCHAR(300) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_requests_status (status, created_at),
    KEY idx_requests_email (email),
    CONSTRAINT fk_requests_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_requests_type   CHECK (type IN ('access', 'demo')),
    CONSTRAINT ck_requests_status CHECK (status IN ('pending', 'approved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
