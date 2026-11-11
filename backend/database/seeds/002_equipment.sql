-- SEED 002 — equipamentos de medição
--
-- Cada represa tem um equipamento por grandeza. SEN-VAZ-03 começa "em
-- manutenção" para mostrar que a API recusa leituras de equipamento parado.

INSERT INTO sensors (id, reservoir_id, name, location, type, status, sort_order) VALUES
('SEN-NIV-01', 'santa-clara', 'Régua de nível',          'Barragem principal',   'level',         'active',      1),
('SEN-VAZ-01', 'santa-clara', 'Afluência principal',     'Entrada principal',    'inflow',        'active',      2),
('SEN-VAZ-02', 'santa-clara', 'Defluência principal',    'Saída da barragem',    'outflow',       'active',      3),
('SEN-VAZ-03', 'santa-clara', 'Canal de restituição',    'Canal de restituição', 'outflow',       'maintenance', 4),
('SEN-PH-01',  'santa-clara', 'Sonda de pH',             'Tomada d''água',       'ph',            'active',      5),
('SEN-TMP-01', 'santa-clara', 'Termômetro da água',      'Tomada d''água',       'water_temp',    'active',      6),
('SEN-PLU-01', 'santa-clara', 'Pluviômetro da barragem', 'Crista da barragem',   'precipitation', 'active',      7),

('SEN-NIV-11', 'rio-verde',   'Régua de nível',          'Barragem principal',   'level',         'active',      1),
('SEN-VAZ-11', 'rio-verde',   'Afluência principal',     'Entrada principal',    'inflow',        'active',      2),
('SEN-VAZ-12', 'rio-verde',   'Defluência principal',    'Saída da barragem',    'outflow',       'active',      3),
('SEN-PH-11',  'rio-verde',   'Sonda de pH',             'Tomada d''água',       'ph',            'active',      4),
('SEN-TMP-11', 'rio-verde',   'Termômetro da água',      'Tomada d''água',       'water_temp',    'active',      5),
('SEN-PLU-11', 'rio-verde',   'Pluviômetro da barragem', 'Crista da barragem',   'precipitation', 'active',      6),

('SEN-NIV-21', 'serra-azul',  'Régua de nível',          'Barragem principal',   'level',         'active',      1),
('SEN-VAZ-21', 'serra-azul',  'Afluência principal',     'Entrada principal',    'inflow',        'active',      2),
('SEN-VAZ-22', 'serra-azul',  'Defluência principal',    'Saída da barragem',    'outflow',       'active',      3),
('SEN-PH-21',  'serra-azul',  'Sonda de pH',             'Tomada d''água',       'ph',            'active',      4),
('SEN-TMP-21', 'serra-azul',  'Termômetro da água',      'Tomada d''água',       'water_temp',    'active',      5),
('SEN-PLU-21', 'serra-azul',  'Pluviômetro da barragem', 'Crista da barragem',   'precipitation', 'active',      6)
ON DUPLICATE KEY UPDATE
    reservoir_id = VALUES(reservoir_id), name = VALUES(name), location = VALUES(location),
    type = VALUES(type), status = VALUES(status), sort_order = VALUES(sort_order);
