-- Aquapulse — tabela de usuários do sistema (contas que podem fazer login).
-- Lida por backend/src/Repositories/PdoUserRepository.php (findByEmail e findById).
-- Deve ser criada ANTES de user_company_access e operational_limits, que apontam para users(id).

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,                     -- número gerado automaticamente; é o valor guardado na sessão após o login
    name VARCHAR(150) NOT NULL,                            -- nome exibido na topbar do dashboard
    email VARCHAR(190) NOT NULL UNIQUE,                    -- login; UNIQUE impede duas contas com o mesmo e-mail (190 cabe no limite de índice do utf8mb4)
    role VARCHAR(50) NOT NULL,                             -- perfil de acesso (o código atual trata o valor 'admin' como "Operador")
    password_hash VARCHAR(255) NOT NULL,                   -- hash da senha gerado por password_hash(); a senha em texto puro nunca é gravada
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,                              -- preenchido automaticamente na criação
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP   -- atualizado automaticamente a cada alteração da linha
);
