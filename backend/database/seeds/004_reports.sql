-- =============================================================================
-- SEED 004 — relatórios gerados e agendamentos
-- Origem: backend/storage/mock/monitoring.php (chaves reports, scheduled_reports).
-- =============================================================================

INSERT INTO reports (id, reservoir_id, name, type, period_label, generated_at, owner, status, icon) VALUES
('REP-2001', 'santa-clara', 'Resumo diário',                       'operational',  '21/05/2024',          '2024-05-22 07:00:00', 'Ana Silva', 'done',       'file-text'),
('REP-2002', 'santa-clara', 'Boletim hidrológico',                 'hydrological', 'Maio/2024',           '2024-05-22 06:30:00', 'Ana Silva', 'done',       'droplet'),
('REP-2003', 'santa-clara', 'Comparativo de vazão',                'hydrological', '15/05 – 22/05/2024',  '2024-05-22 06:15:00', 'Ana Silva', 'done',       'waves'),
('REP-2004', 'santa-clara', 'Qualidade da água',                   'quality',      '21/05/2024',          '2024-05-21 14:50:00', 'Ana Silva', 'done',       'droplet'),
('REP-2005', 'santa-clara', 'Previsão de disponibilidade hídrica', 'planning',     'Junho/2024',          '2024-05-21 10:20:00', 'Ana Silva', 'processing', 'chart-up'),
('REP-2006', 'santa-clara', 'Relatório mensal',                    'operational',  'Abril/2024',          '2024-05-02 09:10:00', 'Ana Silva', 'scheduled',  'calendar'),
('REP-2007', 'rio-verde',   'Resumo diário',                       'operational',  '21/05/2024',          '2024-05-22 07:05:00', 'Ana Silva', 'done',       'file-text'),
('REP-2008', 'serra-azul',  'Boletim hidrológico',                 'hydrological', 'Maio/2024',           '2024-05-22 06:40:00', 'Ana Silva', 'done',       'droplet')
ON DUPLICATE KEY UPDATE
    reservoir_id = VALUES(reservoir_id), name = VALUES(name), type = VALUES(type),
    period_label = VALUES(period_label), generated_at = VALUES(generated_at),
    owner = VALUES(owner), status = VALUES(status), icon = VALUES(icon);

-- Agendamentos: não têm chave natural, então o seed limpa e reinsere.
-- É seguro porque nenhuma outra tabela aponta para scheduled_reports.
DELETE FROM scheduled_reports;
INSERT INTO scheduled_reports (name, frequency, next_run) VALUES
('Resumo diário',                       'Diária', '2024-05-23 07:00:00'),
('Boletim hidrológico',                 'Diária', '2024-05-23 06:30:00'),
('Relatório mensal',                    'Mensal', '2024-06-01 09:00:00'),
('Previsão de disponibilidade hídrica', 'Mensal', '2024-06-01 08:00:00');
