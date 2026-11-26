-- 016 — tentativas de login (limite contra quem fica adivinhando senhas)
--
-- Uma linha por tentativa que ainda não deu certo. Acima do limite, o login
-- responde 429 até as tentativas saírem da janela de 15 minutos; entrar com a
-- senha certa apaga as tentativas do e-mail — ver Auth\LoginThrottle.
--
-- O e-mail fica só como hash (SHA-256): a tabela serve para contar, e ninguém
-- precisa ler dela o que foi digitado. Linhas fora da janela são apagadas.

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_hash   CHAR(64)     NOT NULL,
    ip           VARCHAR(45)  NOT NULL,                   -- 45 = maior endereço IPv6
    attempted_at DATETIME     NOT NULL,

    PRIMARY KEY (id),
    KEY idx_attempts_email (email_hash, attempted_at),
    KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
