-- =============================================================================
-- SEED 001 — empresas, represas e situação operacional
--
-- Origem dos valores: backend/storage/mock/monitoring.php (os mesmos dados que
-- o sistema já exibia com os arquivos simulados). Nada foi inventado aqui.
--
-- ON DUPLICATE KEY UPDATE torna o seed reexecutável: rodar de novo atualiza a
-- linha em vez de falhar por chave duplicada (e, ao contrário de REPLACE, não
-- apaga registros filhos por CASCADE).
-- =============================================================================

INSERT INTO companies (id, code, name, manager, status, status_label, sort_order) VALUES
('hidrovale',      'HVE-001', 'Hidrovale Energia', 'Mariana Costa',  'active', 'Ativa', 1),
('aguas-do-norte', 'ADN-002', 'Águas do Norte',    'Rafael Andrade', 'active', 'Ativa', 2)
ON DUPLICATE KEY UPDATE
    code = VALUES(code), name = VALUES(name), manager = VALUES(manager),
    status = VALUES(status), status_label = VALUES(status_label), sort_order = VALUES(sort_order);

-- Represas: só atributos estáveis. Nível, vazão, pH e volume atuais NÃO ficam
-- aqui — vêm das leituras (tabela readings), carregadas pelo gerador de séries.
INSERT INTO reservoirs (
    id, company_id, code, name, city, basin, lat, lng, coordinates_label,
    capacity_hm3, useful_volume_hm3, technical_reserve_hm3, daily_consumption_hm3,
    duration_days, forecast_reliability_pct,
    cota_critical_m, cota_spill_m, cota_alert_m, gates_total, sort_order
) VALUES
('santa-clara', 'hidrovale', 'RSC-001', 'Represa Santa Clara',
    'Rio Claro — SP', 'Bacia do Rio Claro', -22.387500, -47.692200, '22°23''15" S, 47°41''32" W',
    1500.0, 1050.0, 184.0, 12.50, 84, 92, 570.00, 565.00, 558.00, 4, 1),

('rio-verde', 'hidrovale', 'RRV-002', 'Represa Rio Verde',
    'Itirapina — SP', 'Bacia do Rio Verde', -22.241000, -47.836500, '22°14''28" S, 47°50''11" W',
    1288.0, 838.0, 142.0, 8.70, 96, 90, 556.00, 552.00, 545.00, 3, 2),

('serra-azul', 'aguas-do-norte', 'RSA-003', 'Represa Serra Azul',
    'Corumbataí — SP', 'Bacia do Corumbataí', -22.195800, -47.548700, '22°11''45" S, 47°32''55" W',
    957.0, 632.0, 108.0, 6.90, 91, 89, 612.00, 608.00, 601.00, 2, 3)
ON DUPLICATE KEY UPDATE
    company_id = VALUES(company_id), code = VALUES(code), name = VALUES(name),
    city = VALUES(city), basin = VALUES(basin), lat = VALUES(lat), lng = VALUES(lng),
    coordinates_label = VALUES(coordinates_label), capacity_hm3 = VALUES(capacity_hm3),
    useful_volume_hm3 = VALUES(useful_volume_hm3), technical_reserve_hm3 = VALUES(technical_reserve_hm3),
    daily_consumption_hm3 = VALUES(daily_consumption_hm3), duration_days = VALUES(duration_days),
    forecast_reliability_pct = VALUES(forecast_reliability_pct), cota_critical_m = VALUES(cota_critical_m),
    cota_spill_m = VALUES(cota_spill_m), cota_alert_m = VALUES(cota_alert_m), gates_total = VALUES(gates_total),
    sort_order = VALUES(sort_order);

-- Situação operacional atual (uma linha por represa).
-- Serra Azul tem telemetria em 92% porque um sensor está offline (ver seed 002).
INSERT INTO reservoir_operational_status
    (reservoir_id, gates_online, availability_pct, telemetry_pct, communication_pct, power_pct, humidity_pct) VALUES
('santa-clara', 4, 98.70, 100.00,  99.00, 100.00, 78.00),
('rio-verde',   3, 99.20, 100.00, 100.00, 100.00, 71.00),
('serra-azul',  2, 97.40,  92.00, 100.00, 100.00, 68.00)
ON DUPLICATE KEY UPDATE
    gates_online = VALUES(gates_online), availability_pct = VALUES(availability_pct),
    telemetry_pct = VALUES(telemetry_pct), communication_pct = VALUES(communication_pct),
    power_pct = VALUES(power_pct), humidity_pct = VALUES(humidity_pct);
