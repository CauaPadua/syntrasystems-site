-- SEED 001 — empresas e represas (dados de exemplo; coordenadas fictícias)
--
-- ON DUPLICATE KEY UPDATE torna o seed reexecutável: rodar de novo atualiza a
-- linha em vez de falhar por chave duplicada.

INSERT INTO companies (id, code, name, manager, status, status_label, sort_order) VALUES
('hidrovale',      'HVE-001', 'Hidrovale Energia', 'Mariana Costa',  'active', 'Ativa', 1),
('aguas-do-norte', 'ADN-002', 'Águas do Norte',    'Rafael Andrade', 'active', 'Ativa', 2)
ON DUPLICATE KEY UPDATE
    code = VALUES(code), name = VALUES(name), manager = VALUES(manager),
    status = VALUES(status), status_label = VALUES(status_label), sort_order = VALUES(sort_order);

INSERT INTO reservoirs (
    id, company_id, code, name, city, basin, lat, lng, coordinates_label,
    capacity_hm3, useful_volume_hm3, technical_reserve_hm3, daily_consumption_hm3,
    cota_min_m, cota_max_m, cota_alert_m, cota_spill_m, sort_order
) VALUES
('santa-clara', 'hidrovale', 'RSC-001', 'Represa Santa Clara',
    'Rio Claro — SP', 'Bacia do Rio Claro', -22.387500, -47.692200, '22°23''15" S, 47°41''32" W',
    1500.0, 1050.0, 184.0, 12.50, 480.00, 580.00, 558.00, 565.00, 1),

('rio-verde', 'hidrovale', 'RRV-002', 'Represa Rio Verde',
    'Itirapina — SP', 'Bacia do Rio Verde', -22.241000, -47.836500, '22°14''28" S, 47°50''11" W',
    1288.0, 838.0, 142.0, 8.70, 510.00, 561.10, 545.00, 552.00, 2),

('serra-azul', 'aguas-do-norte', 'RSA-003', 'Represa Serra Azul',
    'Corumbataí — SP', 'Bacia do Corumbataí', -22.195800, -47.548700, '22°11''45" S, 47°32''55" W',
    957.0, 632.0, 108.0, 6.90, 556.00, 618.20, 601.00, 608.00, 3)
ON DUPLICATE KEY UPDATE
    company_id = VALUES(company_id), code = VALUES(code), name = VALUES(name),
    city = VALUES(city), basin = VALUES(basin), lat = VALUES(lat), lng = VALUES(lng),
    coordinates_label = VALUES(coordinates_label), capacity_hm3 = VALUES(capacity_hm3),
    useful_volume_hm3 = VALUES(useful_volume_hm3), technical_reserve_hm3 = VALUES(technical_reserve_hm3),
    daily_consumption_hm3 = VALUES(daily_consumption_hm3), cota_min_m = VALUES(cota_min_m),
    cota_max_m = VALUES(cota_max_m), cota_alert_m = VALUES(cota_alert_m), cota_spill_m = VALUES(cota_spill_m),
    sort_order = VALUES(sort_order);
