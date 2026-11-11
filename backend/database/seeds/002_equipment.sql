-- =============================================================================
-- SEED 002 — equipamentos, pontos de coleta de pH e pluviômetros
--
-- Cada equipamento mede UMA grandeza (coluna type) e é identificado pelo código
-- que envia junto com cada leitura. O status é a situação cadastral; se o
-- equipamento está "online" depende de ele estar transmitindo.
--
-- As leituras não estão aqui: o histórico inicial é gerado pelo instalador e,
-- daí em diante, chega pela API de ingestão.
-- =============================================================================

INSERT INTO sensors (id, reservoir_id, name, location, type, status, sort_order) VALUES
('SEN-NIV-01', 'santa-clara', 'Régua de nível',          'Barragem principal',   'level',         'active',      1),
('SEN-VAZ-01', 'santa-clara', 'Afluência principal',     'Entrada principal',    'inflow',        'active',      2),
('SEN-VAZ-02', 'santa-clara', 'Defluência principal',    'Saída da barragem',    'outflow',       'active',      3),
('SEN-VAZ-03', 'santa-clara', 'Canal de restituição',    'Canal de restituição', 'outflow',       'maintenance', 4),
('SEN-PH-01',  'santa-clara', 'Sonda de pH',             'Tomada d''água',       'ph',            'active',      5),
('SEN-PLU-01', 'santa-clara', 'Pluviômetro da barragem', 'Crista da barragem',   'precipitation', 'active',      6),

('SEN-NIV-11', 'rio-verde',   'Régua de nível',          'Barragem principal',   'level',         'active',      1),
('SEN-VAZ-11', 'rio-verde',   'Afluência principal',     'Entrada principal',    'inflow',        'active',      2),
('SEN-VAZ-12', 'rio-verde',   'Defluência principal',    'Saída da barragem',    'outflow',       'active',      3),
('SEN-PH-11',  'rio-verde',   'Sonda de pH',             'Tomada d''água',       'ph',            'active',      4),
('SEN-PLU-11', 'rio-verde',   'Pluviômetro da barragem', 'Crista da barragem',   'precipitation', 'active',      5),

('SEN-NIV-21', 'serra-azul',  'Régua de nível',          'Barragem principal',   'level',         'active',      1),
('SEN-VAZ-21', 'serra-azul',  'Afluência principal',     'Entrada principal',    'inflow',        'active',      2),
('SEN-VAZ-22', 'serra-azul',  'Defluência principal',    'Saída da barragem',    'outflow',       'active',      3),
('SEN-PH-21',  'serra-azul',  'Sonda de pH',             'Tomada d''água',       'ph',            'active',      4),
('SEN-PLU-21', 'serra-azul',  'Pluviômetro da barragem', 'Crista da barragem',   'precipitation', 'active',      5)
ON DUPLICATE KEY UPDATE
    reservoir_id = VALUES(reservoir_id), name = VALUES(name), location = VALUES(location),
    type = VALUES(type), status = VALUES(status), sort_order = VALUES(sort_order);

-- Pontos de coleta de pH (amostras de laboratório, separadas da sonda acima).
-- A chave única (reservoir_id, name) impede duplicar o ponto ao reexecutar.
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

-- Estações pluviométricas da bacia.
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
