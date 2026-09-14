-- Aquapulse — limites demonstrativos atuais, migrados do código para o banco.
-- Não são valores definitivos, apenas os mesmos já usados hoje no mock.
--
-- Rodar depois de create_limits_table.sql. Cada linha é uma faixa de classificação
-- válida para todo o sistema (scope_type 'global'), equivalente a StatusRules.php.

INSERT INTO operational_limits (scope_type, scope_id, metric, min_value, max_value, unit, classification) VALUES
('global', NULL, 'level', NULL, 80.0, '%', 'normal'),       -- nível abaixo de 80%: normal
('global', NULL, 'level', 80.0, 90.0, '%', 'attention'),    -- de 80% a 90%: atenção
('global', NULL, 'level', 90.0, NULL, '%', 'critical'),     -- acima de 90%: crítico (sem limite superior)
('global', NULL, 'ph', 6.5, 8.5, 'pH', 'normal');           -- pH entre 6,5 e 8,5: normal
