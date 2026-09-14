-- Aquapulse — dados iniciais de demonstração.
-- Baseado nos valores de referência do documento de repasse ao DBA.
-- Estas coordenadas e nomes são fictícios e precisam ser substituídos
-- pelos dados reais antes de produção.
--
-- Rodar depois de create_monitoring_tables.sql (companies precisa existir antes de
-- reservoirs, por causa da chave estrangeira company_id).

-- Uma empresa de demonstração; todas as represas abaixo pertencem a ela.
INSERT INTO companies (id, code, name, manager, status, status_label) VALUES
('empresa-demo', 'DEMO001', 'Empresa Demonstração', 'A definir', 'active', 'Ativa');

-- Três represas com os mesmos IDs e valores centrais do arquivo simulado
-- (backend/storage/mock/monitoring.php). Os campos com 0 (cota, afluência,
-- defluência) ainda não têm valor real definido.
INSERT INTO reservoirs (
    id, company_id, name,
    level_pct, cota_m, cota_variation_m,
    flow_m3s, inflow_m3s, outflow_m3s,
    ph, volume_hm3, rain_24h_mm, water_temp_c
) VALUES
('santa-clara', 'empresa-demo', 'Represa Santa Clara',
    82.4, 0, 0, 56.2, 0, 0, 7.2, 1234, 18.6, 24.0),      -- nível 82,4% (faixa de atenção)

('rio-verde', 'empresa-demo', 'Represa Rio Verde',
    76.1, 0, 0, 43.8, 0, 0, 7.4, 980, 12.3, 24.0),       -- nível 76,1% (normal)

('serra-azul', 'empresa-demo', 'Represa Serra Azul',
    77.3, 0, 0, 32.5, 0, 0, 7.3, 740, 8.7, 24.0);        -- nível 77,3% (normal)
