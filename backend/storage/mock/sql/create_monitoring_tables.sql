-- Aquapulse — estrutura das tabelas de monitoramento.
-- Lidas por backend/src/Repositories/PdoMonitoringRepository.php quando o banco está configurado.
--
-- Ordem: companies antes de reservoirs, e reservoirs antes das tabelas que apontam para ela.
--
-- Dois estilos de tabela:
--  * colunas normais (companies, reservoirs, readings, users);
--  * "documento": colunas de filtro (reservoir_id, severity, status...) + o item
--    completo em JSON na coluna data. O repositório decodifica esse JSON e devolve a
--    mesma estrutura do arquivo simulado, sem mapear coluna por coluna.

-- Empresas donas das represas.
CREATE TABLE companies (
    id VARCHAR(40) PRIMARY KEY,                -- identificador em texto (ex.: 'hidrovale'), usado nas URLs da API
    code VARCHAR(40) NOT NULL,                 -- código interno exibido na tela
    name VARCHAR(150) NOT NULL,
    manager VARCHAR(150) NOT NULL,             -- responsável pela empresa
    status VARCHAR(20) NOT NULL,               -- chave do status (ex.: 'active')
    status_label VARCHAR(40) NOT NULL          -- texto do status para exibição
);

-- Represas monitoradas. DECIMAL(p,e): p dígitos no total, e deles depois da vírgula.
CREATE TABLE reservoirs (
    id VARCHAR(40) PRIMARY KEY,                -- ex.: 'santa-clara'
    company_id VARCHAR(40) NOT NULL,           -- empresa dona
    name VARCHAR(150) NOT NULL,
    level_pct DECIMAL(5,1) NOT NULL,           -- ocupação em % (ex.: 82.4)
    cota_m DECIMAL(6,1) NOT NULL,              -- cota da água em metros
    cota_variation_m DECIMAL(6,1) NOT NULL DEFAULT 0, -- variação da cota em 24 h
    flow_m3s DECIMAL(8,1) NOT NULL,            -- vazão em m³/s
    inflow_m3s DECIMAL(8,1) NOT NULL,          -- afluência (entrada)
    outflow_m3s DECIMAL(8,1) NOT NULL,         -- defluência (saída)
    ph DECIMAL(3,1) NOT NULL,                  -- pH (ex.: 7.2)
    volume_hm3 DECIMAL(10,0) NOT NULL,         -- volume armazenado em hm³, sem casas decimais
    rain_24h_mm DECIMAL(6,1) NOT NULL,         -- chuva em 24 h
    water_temp_c DECIMAL(4,1) NOT NULL,        -- temperatura da água
    FOREIGN KEY (company_id) REFERENCES companies(id) -- a represa só pode apontar para uma empresa existente
);

-- Sensores de cada represa (tabela documento).
CREATE TABLE sensors (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,         -- filtro usado em WHERE reservoir_id = :rid
    data JSON NOT NULL,                        -- sensor completo: nome, local, tipo, status
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

-- Pontos de coleta de pH (tabela documento).
CREATE TABLE ph_points (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

-- Estações pluviométricas (tabela documento).
CREATE TABLE rain_stations (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

-- Leituras dos sensores ao longo do tempo: fonte das séries dos gráficos e das
-- tabelas de "últimas leituras". Uma linha por medição de uma métrica.
CREATE TABLE readings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,      -- BIGINT: a tabela cresce muito (uma linha por leitura)
    reservoir_id VARCHAR(40) NOT NULL,
    metric VARCHAR(30) NOT NULL,               -- 'level', 'flow', 'inflow', 'outflow', 'ph', 'storage', 'precipitation'...
    value DECIMAL(12,3) NOT NULL,              -- valor medido
    recorded_at DATETIME NOT NULL,             -- momento da medição
    quality VARCHAR(20) NOT NULL DEFAULT 'valid', -- permite marcar leituras suspeitas sem apagá-las
    INDEX idx_reservoir_metric_time (reservoir_id, metric, recorded_at) -- acelera exatamente o filtro das consultas: represa + métrica + intervalo de tempo
);

-- Alertas (tabela documento). severity e status ficam em colunas próprias
-- porque são usados como filtro; o restante do alerta fica no JSON.
CREATE TABLE alerts (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    severity VARCHAR(20) NOT NULL,             -- critical | attention | info
    status VARCHAR(20) NOT NULL,               -- new | analysis | resolved
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

-- Relatórios gerados (tabela documento), filtráveis por represa, tipo e status.
CREATE TABLE reports (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    type VARCHAR(30) NOT NULL,                 -- operational | hydrological | quality | planning
    status VARCHAR(20) NOT NULL,               -- done | processing | scheduled
    data JSON NOT NULL
);

-- Relatórios com geração automática (sem vínculo com represa).
CREATE TABLE scheduled_reports (
    id VARCHAR(40) PRIMARY KEY,
    data JSON NOT NULL
);

-- Eventos operacionais (tabela documento).
CREATE TABLE operation_events (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

-- Manutenções programadas (tabela documento).
CREATE TABLE maintenances (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

-- Configurações gerais: cada linha é um grupo (ex.: 'units', 'thresholds') com seus valores em JSON.
CREATE TABLE settings (
    setting_key VARCHAR(60) PRIMARY KEY,       -- nome do grupo de configuração
    data JSON NOT NULL
);
