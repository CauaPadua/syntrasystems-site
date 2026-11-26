-- 015 — numeração de alertas e relatórios
--
-- Antes, o número novo era "o maior já usado + 1", lido com MAX(): duas gravações
-- ao mesmo tempo liam o mesmo maior e a segunda falhava. Agora cada sequência é uma
-- linha: o UPDATE soma 1 e trava a linha até o fim da transação (Support\Sequence).

CREATE TABLE IF NOT EXISTS sequences (
    name  VARCHAR(20)  NOT NULL,                          -- alert | report
    value INT UNSIGNED NOT NULL,                          -- último número já usado

    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Continua de onde a numeração parou (ALT-1000 e REP-2000 em banco vazio).
INSERT IGNORE INTO sequences (name, value)
SELECT 'alert', COALESCE(MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)), 1000) FROM alerts WHERE id LIKE 'ALT-%';

INSERT IGNORE INTO sequences (name, value)
SELECT 'report', COALESCE(MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)), 2000) FROM reports WHERE id LIKE 'REP-%';
