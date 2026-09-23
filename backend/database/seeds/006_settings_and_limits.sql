-- =============================================================================
-- SEED 006 — configurações do sistema e limites operacionais
--
-- Origem: backend/storage/mock/monitoring.php (chave settings) e as faixas que
-- hoje estão em backend/src/Services/StatusRules.php.
--
-- Os limites entram como vigentes (valid_to = NULL). Uma mudança futura deve
-- encerrar a linha atual (preencher valid_to) e inserir outra, preservando o
-- histórico do que valia em cada período.
-- =============================================================================

INSERT INTO system_settings (setting_key, setting_value, value_type, description) VALUES
('units.level',      'metros (m)', 'string', 'Unidade exibida para nível/cota'),
('units.volume',     'hm³',        'string', 'Unidade exibida para volume'),
('units.flow',       'm³/s',       'string', 'Unidade exibida para vazão'),
('refresh_interval', '5min',       'string', 'Intervalo de atualização automática das telas'),
('auto_refresh',     '1',          'bool',   'Atualização automática ligada')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value), value_type = VALUES(value_type),
    description = VALUES(description);

INSERT INTO setting_indicators (id, label, enabled, sort_order) VALUES
('level',         'Nível do reservatório', 1, 1),
('flow',          'Vazão',                 1, 2),
('ph',            'pH',                    1, 3),
('storage',       'Volume armazenado',     1, 4),
('precipitation', 'Precipitação',          1, 5),
('duration',      'Duração estimada',      1, 6)
ON DUPLICATE KEY UPDATE
    label = VALUES(label), enabled = VALUES(enabled), sort_order = VALUES(sort_order);

INSERT INTO notification_channels (id, label, target, enabled, sort_order) VALUES
('email', 'E-mail', 'ana.silva@hidrovale.com.br', 1, 1),
('panel', 'Painel', 'Notificações no sistema',    1, 2)
ON DUPLICATE KEY UPDATE
    label = VALUES(label), target = VALUES(target),
    enabled = VALUES(enabled), sort_order = VALUES(sort_order);

-- Faixas de classificação vigentes (equivalentes a StatusRules.php).
DELETE FROM operational_limits WHERE scope_type = 'global' AND valid_to IS NULL;
INSERT INTO operational_limits (scope_type, scope_id, metric, classification, min_value, max_value, unit, justification) VALUES
('global', NULL, 'level',         'normal',    NULL, 80.0, '%',  'Faixa operacional normal do nível'),
('global', NULL, 'level',         'attention', 80.0, 90.0, '%',  'Acima de 80% exige acompanhamento'),
('global', NULL, 'level',         'critical',  90.0, NULL, '%',  'Acima de 90% exige ação imediata'),
('global', NULL, 'ph',            'normal',    6.5,  8.5,  'pH', 'Faixa ideal de pH para a água bruta'),
('global', NULL, 'precipitation', 'critical',  60.0, NULL, 'mm', 'Chuva acumulada em 24h considerada crítica');
