-- SEED 004 — limites operacionais
--
-- São os números que classificam as leituras em todas as telas e que decidem
-- quando um alerta é aberto. Para mudar um limite, altere aqui e rode o seed.

DELETE FROM operational_limits;

INSERT INTO operational_limits (metric, classification, min_value, max_value, unit, description) VALUES
('level',         'attention', 80.0, 90.0, '%',  'A partir de 80% o nível exige acompanhamento'),
('level',         'critical',  90.0, NULL, '%',  'A partir de 90% o nível exige ação imediata'),
('ph',            'normal',    6.5,  8.5,  'pH', 'Faixa ideal de pH da água bruta'),
('precipitation', 'attention', 20.0, 60.0, 'mm', 'Chuva acumulada em 24 h que exige acompanhamento'),
('precipitation', 'critical',  60.0, NULL, 'mm', 'Chuva acumulada em 24 h considerada crítica');
