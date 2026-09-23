-- =============================================================================
-- SEED 005 — eventos operacionais e manutenções programadas
-- Origem: backend/storage/mock/monitoring.php (operation_events, maintenances).
--
-- Estas duas tabelas usam id AUTO_INCREMENT e não têm chave natural: o seed
-- apaga e reinsere as linhas das represas conhecidas, para ser reexecutável
-- sem duplicar. Nenhuma outra tabela referencia estes registros.
-- =============================================================================

DELETE FROM operation_events;
INSERT INTO operation_events (reservoir_id, occurred_at, component, event, priority, status) VALUES
('santa-clara', '2024-05-22 09:27:00', 'Nível',        'Nível acima de 80%',                  'attention', 'new'),
('santa-clara', '2024-05-22 08:47:00', 'Comunicação',  'Link de backup ativado',              'info',      'resolved'),
('santa-clara', '2024-05-22 08:32:00', 'Comportas',    'Teste de abertura',                   'info',      'resolved'),
('santa-clara', '2024-05-22 08:15:00', 'Pluviômetros', 'Chuva moderada',                      'info',      'resolved'),
('santa-clara', '2024-05-22 07:58:00', 'Energia',      'Fonte principal ativa',               'info',      'resolved'),
('rio-verde',   '2024-05-22 09:10:00', 'Comportas',    'Operação normal',                     'info',      'resolved'),
('rio-verde',   '2024-05-22 08:05:00', 'Energia',      'Fonte principal ativa',               'info',      'resolved'),
('serra-azul',  '2024-05-22 05:10:00', 'Telemetria',   'Sensor SEN-VAZ-22 sem comunicação',   'attention', 'analysis'),
('serra-azul',  '2024-05-22 05:40:00', 'Comunicação',  'Link de backup ativado',              'info',      'resolved');

DELETE FROM maintenances;
INSERT INTO maintenances (reservoir_id, scheduled_date, equipment, type, priority) VALUES
('santa-clara', '2024-05-24', 'Comporta 02',        'Preventiva', 'attention'),
('santa-clara', '2024-05-27', 'Pluviômetro 01',     'Preventiva', 'low'),
('santa-clara', '2024-05-30', 'Gerador reserva',    'Preventiva', 'attention'),
('santa-clara', '2024-06-04', 'Nível – Sensor 03',  'Calibração', 'low'),
('rio-verde',   '2024-05-26', 'Comporta 01',        'Preventiva', 'low'),
('rio-verde',   '2024-06-02', 'Sensor de vazão',    'Calibração', 'low'),
('serra-azul',  '2024-05-25', 'SEN-VAZ-22',         'Corretiva',  'attention'),
('serra-azul',  '2024-06-06', 'Pluviômetro Vale',   'Corretiva',  'attention');
