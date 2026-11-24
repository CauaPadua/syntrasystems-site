-- 012 — hierarquia de cargos e acesso por represa
--
-- CARGO   (users.role)             o que a pessoa pode FAZER:
--           admin     administrador: todas as represas; aprova pedidos e cadastra tudo
--           manager   gestor da represa: equipamentos e relatórios das represas dele
--           operator  operador: acompanha, trata alertas e abre chamados
-- ALCANCE (user_reservoir_access)  quais represas ela pode VER (uma ou várias).
--
-- Antes o acesso era por empresa (user_company_access): quem via a empresa via
-- todas as represas dela. Os vínculos existentes são convertidos antes de a
-- tabela sair, então ninguém perde acesso nesta mudança.

ALTER TABLE users DROP CONSTRAINT ck_users_role;
ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('admin', 'manager', 'operator'));

-- Relação N:N entre contas e represas.
CREATE TABLE IF NOT EXISTS user_reservoir_access (
    user_id      INT UNSIGNED NOT NULL,
    reservoir_id VARCHAR(40)  NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, reservoir_id),
    CONSTRAINT fk_uraccess_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_uraccess_reservoir FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada vínculo com uma empresa vira vínculo com as represas dela. O administrador
-- vê tudo pelo cargo e não precisa de vínculo.
INSERT IGNORE INTO user_reservoir_access (user_id, reservoir_id)
SELECT a.user_id, r.id
  FROM user_company_access a
  JOIN users u      ON u.id = a.user_id AND u.role <> 'admin'
  JOIN reservoirs r ON r.company_id = a.company_id;

DROP TABLE user_company_access;

-- Conta de exemplo do cargo novo (gestor da Serra Azul), com a senha da conta de
-- demonstração. Só onde os dados de exemplo já existem: em instalação nova, a
-- tabela users ainda está vazia aqui e a conta vem dos seeds.
INSERT IGNORE INTO users (name, email, role, status, password_hash)
SELECT 'Diego Ramos', 'gestor@aquapulse.local', 'manager', 'active', d.password_hash
  FROM users d
 WHERE d.email = 'demo@aquapulse.local';

INSERT IGNORE INTO user_reservoir_access (user_id, reservoir_id)
SELECT u.id, r.id
  FROM users u
  JOIN reservoirs r ON r.id = 'serra-azul'
 WHERE u.email = 'gestor@aquapulse.local';
