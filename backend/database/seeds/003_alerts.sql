-- =============================================================================
-- SEED 003 — alertas e suas linhas do tempo
--
-- Origem: backend/storage/mock/monitoring.php (chave alerts). As datas são as
-- mesmas do relógio demonstrativo do sistema (22/05/2024), para as telas
-- continuarem mostrando "há X minutos" de forma coerente.
--
-- Etapas com done = 0 não têm data (a restrição ck_timeline_done exige isso).
-- =============================================================================

INSERT INTO alerts (id, reservoir_id, severity, status, title, metric_label, detected_at, owner,
                    current_value, threshold, detail, threshold_detail) VALUES
('ALT-1001', 'santa-clara', 'attention', 'new',      'Nível acima de 80%',            'Nível do reservatório', '2024-05-22 08:45:00', 'Ana Silva', '82,4%',      '80%',        'Em relação à cota 562,4 m',      'Cota 560,0 m'),
('ALT-1002', 'santa-clara', 'critical',  'analysis', 'Vazão afluente elevada',        'Vazão afluente',        '2024-05-22 08:30:00', 'Ana Silva', '56,2 m³/s',  '55,0 m³/s',  'Média nas últimas 24h',          'Limite operacional'),
('ALT-1003', 'santa-clara', 'attention', 'new',      'Precipitação intensa prevista', 'Precipitação (24h)',    '2024-05-22 07:30:00', 'Ana Silva', '18,6 mm',    '60 mm',      'Chuva moderada na bacia',        'Limite crítico configurado'),
('ALT-1004', 'rio-verde',   'info',      'resolved', 'pH fora da faixa ideal',        'pH da água',            '2024-05-22 06:20:00', 'Ana Silva', '7,4',        '6,5 – 8,5',  'Leitura pontual acima da média', 'Faixa ideal configurada'),
('ALT-1005', 'serra-azul',  'info',      'resolved', 'Sensor sem comunicação',        'Vazão defluente',       '2024-05-22 05:10:00', 'Ana Silva', 'SEN-VAZ-22', 'Telemetria', 'Sensor de defluência offline',   'Reconexão automática')
ON DUPLICATE KEY UPDATE
    reservoir_id = VALUES(reservoir_id), severity = VALUES(severity), status = VALUES(status),
    title = VALUES(title), metric_label = VALUES(metric_label), detected_at = VALUES(detected_at),
    owner = VALUES(owner), current_value = VALUES(current_value), threshold = VALUES(threshold),
    detail = VALUES(detail), threshold_detail = VALUES(threshold_detail);

-- Linha do tempo de cada alerta: uma linha por etapa, na ordem em que aconteceu.
INSERT INTO alert_timeline (alert_id, step_order, happened_at, description, done) VALUES
('ALT-1001', 1, '2024-05-22 08:45:00', 'Alerta detectado: nível acima de 80% do limite configurado.', 1),
('ALT-1001', 2, '2024-05-22 08:47:00', 'Notificação enviada por e-mail e painel.',                     1),
('ALT-1001', 3, NULL,                  'Aguardando análise e ação.',                                   0),
('ALT-1002', 1, '2024-05-22 08:30:00', 'Alerta detectado: vazão afluente acima do limite.',            1),
('ALT-1002', 2, '2024-05-22 08:35:00', 'Equipe de operação notificada.',                               1),
('ALT-1002', 3, NULL,                  'Em análise pela equipe técnica.',                              0),
('ALT-1003', 1, '2024-05-22 07:30:00', 'Previsão meteorológica indica chuva intensa em 48h.',          1),
('ALT-1003', 2, NULL,                  'Aguardando confirmação da estação.',                           0),
('ALT-1004', 1, '2024-05-22 06:20:00', 'Leitura registrada fora da média histórica.',                  1),
('ALT-1004', 2, NULL,                  'Coleta manual agendada.',                                      0),
('ALT-1005', 1, '2024-05-22 05:10:00', 'Sensor deixou de responder.',                                  1),
('ALT-1005', 2, '2024-05-22 05:40:00', 'Link de backup ativado.',                                      1),
('ALT-1005', 3, '2024-05-22 06:05:00', 'Ocorrência resolvida.',                                        1)
ON DUPLICATE KEY UPDATE
    happened_at = VALUES(happened_at), description = VALUES(description), done = VALUES(done);
