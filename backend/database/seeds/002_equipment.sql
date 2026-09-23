-- =============================================================================
-- SEED 002 — equipamentos: sensores, pontos de coleta de pH e pluviômetros
--
-- Origem: backend/storage/mock/monitoring.php (chaves sensors, ph_points,
-- rain_stations). SEN-VAZ-22 continua offline, como no arquivo simulado — é o
-- que faz a telemetria da Serra Azul aparecer como parcial nas telas.
--
-- As MEDIÇÕES destes equipamentos não estão aqui: são geradas pelo gerador de
-- séries (backend/database/series_generator.php).
-- =============================================================================

INSERT INTO sensors (id, reservoir_id, name, location, type, status, sort_order) VALUES
('SEN-VAZ-01', 'santa-clara', 'Afluência principal',  'Entrada principal',    'flow', 'online',  1),
('SEN-VAZ-02', 'santa-clara', 'Defluência principal', 'Saída da barragem',    'flow', 'online',  2),
('SEN-VAZ-03', 'santa-clara', 'Canal de restituição', 'Canal de restituição', 'flow', 'online',  3),
('SEN-VAZ-11', 'rio-verde',   'Afluência principal',  'Entrada principal',    'flow', 'online',  1),
('SEN-VAZ-12', 'rio-verde',   'Defluência principal', 'Saída da barragem',    'flow', 'online',  2),
('SEN-VAZ-21', 'serra-azul',  'Afluência principal',  'Entrada principal',    'flow', 'online',  1),
('SEN-VAZ-22', 'serra-azul',  'Defluência principal', 'Saída da barragem',    'flow', 'offline', 2)
ON DUPLICATE KEY UPDATE
    reservoir_id = VALUES(reservoir_id), name = VALUES(name), location = VALUES(location),
    type = VALUES(type), status = VALUES(status), sort_order = VALUES(sort_order);

-- Pontos de coleta de pH. O id é numérico (AUTO_INCREMENT); a chave única
-- (reservoir_id, name) é o que impede duplicar o mesmo ponto ao reexecutar.
INSERT INTO ph_points (reservoir_id, name, sort_order) VALUES
('santa-clara', 'Entrada principal',      1),
('santa-clara', 'Centro do reservatório', 2),
('santa-clara', 'Próximo à barragem',     3),
('rio-verde',   'Entrada principal',      1),
('rio-verde',   'Centro do reservatório', 2),
('rio-verde',   'Próximo à barragem',     3),
('serra-azul',  'Entrada principal',      1),
('serra-azul',  'Centro do reservatório', 2),
('serra-azul',  'Próximo à barragem',     3)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO rain_stations (id, reservoir_id, name, status, sort_order) VALUES
('P01', 'santa-clara', 'Pluviômetro Norte',   'online',  1),
('P02', 'santa-clara', 'Pluviômetro Oeste',   'online',  2),
('P03', 'santa-clara', 'Pluviômetro Sul',     'online',  3),
('P11', 'rio-verde',   'Pluviômetro Central', 'online',  1),
('P12', 'rio-verde',   'Pluviômetro Leste',   'online',  2),
('P21', 'serra-azul',  'Pluviômetro Serra',   'online',  1),
('P22', 'serra-azul',  'Pluviômetro Vale',    'offline', 2)
ON DUPLICATE KEY UPDATE
    reservoir_id = VALUES(reservoir_id), name = VALUES(name),
    status = VALUES(status), sort_order = VALUES(sort_order);
