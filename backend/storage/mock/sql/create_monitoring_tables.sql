CREATE TABLE companies (
    id VARCHAR(40) PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    manager VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL,
    status_label VARCHAR(40) NOT NULL
);

CREATE TABLE reservoirs (
    id VARCHAR(40) PRIMARY KEY,
    company_id VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    level_pct DECIMAL(5,1) NOT NULL,
    cota_m DECIMAL(6,1) NOT NULL,
    cota_variation_m DECIMAL(6,1) NOT NULL DEFAULT 0,
    flow_m3s DECIMAL(8,1) NOT NULL,
    inflow_m3s DECIMAL(8,1) NOT NULL,
    outflow_m3s DECIMAL(8,1) NOT NULL,
    ph DECIMAL(3,1) NOT NULL,
    volume_hm3 DECIMAL(10,0) NOT NULL,
    rain_24h_mm DECIMAL(6,1) NOT NULL,
    water_temp_c DECIMAL(4,1) NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE sensors (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

CREATE TABLE ph_points (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

CREATE TABLE rain_stations (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

CREATE TABLE readings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    metric VARCHAR(30) NOT NULL,
    value DECIMAL(12,3) NOT NULL,
    recorded_at DATETIME NOT NULL,
    quality VARCHAR(20) NOT NULL DEFAULT 'valid',
    INDEX idx_reservoir_metric_time (reservoir_id, metric, recorded_at)
);

CREATE TABLE alerts (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

CREATE TABLE reports (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    type VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL,
    data JSON NOT NULL
);

CREATE TABLE scheduled_reports (
    id VARCHAR(40) PRIMARY KEY,
    data JSON NOT NULL
);

CREATE TABLE operation_events (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

CREATE TABLE maintenances (
    id VARCHAR(40) PRIMARY KEY,
    reservoir_id VARCHAR(40) NOT NULL,
    data JSON NOT NULL,
    FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id)
);

CREATE TABLE settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    data JSON NOT NULL
);
