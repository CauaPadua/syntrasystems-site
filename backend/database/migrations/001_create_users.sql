-- =============================================================================
-- 001 — usuários do sistema (contas que fazem login)
--
-- Lida por backend/src/Repositories/PdoUserRepository.php (findByEmail/findById).
-- Precisa existir ANTES de user_company_access e operational_limits, que
-- apontam para users(id).
-- =============================================================================

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,                 -- nome exibido na topbar do dashboard
    email         VARCHAR(190) NOT NULL,                 -- login; 190 é o máximo indexável em utf8mb4 (191 × 4 bytes)
    role          VARCHAR(30)  NOT NULL DEFAULT 'operator', -- perfil de acesso ('admin', 'operator'...)
    password_hash VARCHAR(255) NOT NULL,                 -- hash bcrypt de password_hash(); a senha em texto nunca é gravada
    status        VARCHAR(20)  NOT NULL DEFAULT 'active',-- permite desativar a conta sem apagar o histórico
    last_login_at DATETIME     NULL,                     -- preenchido pela aplicação a cada login bem-sucedido
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),                   -- impede duas contas com o mesmo e-mail
    CONSTRAINT ck_users_status CHECK (status IN ('active', 'suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
