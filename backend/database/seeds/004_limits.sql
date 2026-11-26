-- SEED 004 — limites operacionais de pH e chuva
--
-- São os números que classificam as leituras em todas as telas e que decidem
-- quando um alerta é aberto. Valem para todas as represas e o administrador muda
-- em Configurações › Limites; rodar o seed volta aos valores daqui.
--
-- O nível não está nesta tabela: cada represa usa as próprias cotas de alerta e de
-- vertimento (colunas cota_alert_m e cota_spill_m de reservoirs).

DELETE FROM operational_limits;

INSERT INTO operational_limits (metric, classification, min_value, max_value, unit, description) VALUES
('ph',            'normal',    6.5,  8.5,  'pH', 'Faixa ideal de pH da água bruta'),
('precipitation', 'attention', 20.0, 60.0, 'mm', 'Chuva acumulada em 24 h que exige acompanhamento'),
('precipitation', 'critical',  60.0, NULL, 'mm', 'Chuva acumulada em 24 h considerada crítica');
