-- Aquapulse — limites demonstrativos atuais, migrados do código para o banco.
-- Não são valores definitivos, apenas os mesmos já usados hoje no mock.

INSERT INTO operational_limits (scope_type, scope_id, metric, min_value, max_value, unit, classification) VALUES
('global', NULL, 'level', NULL, 80.0, '%', 'normal'),
('global', NULL, 'level', 80.0, 90.0, '%', 'attention'),
('global', NULL, 'level', 90.0, NULL, '%', 'critical'),
('global', NULL, 'ph', 6.5, 8.5, 'pH', 'normal');
