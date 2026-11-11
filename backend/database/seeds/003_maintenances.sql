-- =============================================================================
-- SEED 003 — manutenções programadas (exemplos)
--
-- As datas são relativas ao dia da carga (CURDATE() + N dias), para a lista
-- nunca nascer vencida.
--
-- Reexecutar o seed troca apenas estas linhas de exemplo: os chamados abertos
-- pelo site têm requested_by preenchido e são preservados.
-- =============================================================================

DELETE FROM maintenances WHERE requested_by IS NULL;

INSERT INTO maintenances (reservoir_id, scheduled_date, equipment, type, priority, description) VALUES
('santa-clara', DATE_ADD(CURDATE(), INTERVAL 2 DAY),  'SEN-VAZ-03 — Canal de restituição', 'Corretiva',  'attention', 'Equipamento em manutenção; troca do transdutor.'),
('santa-clara', DATE_ADD(CURDATE(), INTERVAL 9 DAY),  'SEN-PLU-01 — Pluviômetro da barragem', 'Preventiva', 'low',    'Limpeza do coletor.'),
('santa-clara', DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'SEN-NIV-01 — Régua de nível',       'Calibração', 'low',       'Calibração semestral.'),
('rio-verde',   DATE_ADD(CURDATE(), INTERVAL 5 DAY),  'SEN-VAZ-12 — Defluência principal', 'Preventiva', 'low',       'Inspeção do medidor.'),
('rio-verde',   DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'SEN-PH-11 — Sonda de pH',           'Calibração', 'low',       'Calibração com solução padrão.'),
('serra-azul',  DATE_ADD(CURDATE(), INTERVAL 3 DAY),  'SEN-VAZ-22 — Defluência principal', 'Corretiva',  'attention', 'Falhas intermitentes de comunicação.'),
('serra-azul',  DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'SEN-PLU-21 — Pluviômetro da barragem', 'Preventiva', 'low',    'Limpeza do coletor.');
