-- Aquapulse — vínculo de usuário com empresa e suas permissões.
-- Um usuário pode ter acesso a mais de uma empresa, com permissões
-- e função diferentes em cada uma.

CREATE TABLE user_company_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id VARCHAR(40) NOT NULL,
    role VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    permissions JSON NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    granted_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (granted_by) REFERENCES users(id),
    UNIQUE KEY uniq_user_empresa (user_id, company_id)
);
