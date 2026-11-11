-- =============================================================================
-- 017 — views (consultas salvas no banco)
--
-- Uma view não guarda dados: guarda um SELECT com nome. Quem consulta a view
-- recebe sempre o resultado atual das tabelas de origem.
--
--   vw_ultimas_medicoes  uma linha por equipamento, com a leitura mais recente.
--                        Usada pela tela "Equipamentos" e pela contagem de
--                        equipamentos que estão transmitindo.
--   vw_resumo_represas   uma linha por represa, com o valor atual de cada
--                        grandeza. Usada para montar os cards do painel.
--
-- CREATE OR REPLACE permite reaplicar o arquivo sem apagar a view antes.
-- =============================================================================

-- Última medição de cada equipamento.
-- LEFT JOIN: o equipamento aparece mesmo que ainda não tenha enviado nada.
-- A subconsulta encontra o instante da leitura mais recente daquele equipamento.
CREATE OR REPLACE VIEW vw_ultimas_medicoes AS
SELECT s.id           AS sensor_id,
       s.name         AS sensor_name,
       s.type         AS metric,
       s.location,
       s.status,
       s.reservoir_id,
       r.name         AS reservoir_name,
       r.company_id,
       l.value        AS last_value,
       l.recorded_at  AS last_reading_at
  FROM sensors s
  JOIN reservoirs r ON r.id = s.reservoir_id
  LEFT JOIN readings l
         ON l.sensor_id = s.id
        AND l.metric = s.type
        AND l.recorded_at = (SELECT MAX(l2.recorded_at)
                               FROM readings l2
                              WHERE l2.sensor_id = s.id
                                AND l2.metric = s.type);

-- Resumo de cada represa: cadastro + valor atual de cada grandeza.
-- A tabela derivada "u" traz o instante da última leitura de cada grandeza; o
-- JOIN com readings busca o valor daquele instante; o MAX(CASE ...) transforma
-- as linhas (uma por grandeza) em colunas.
CREATE OR REPLACE VIEW vw_resumo_represas AS
SELECT r.id                                                  AS reservoir_id,
       r.code,
       r.name,
       r.company_id,
       c.name                                                AS company_name,
       r.capacity_hm3,
       MAX(CASE WHEN l.metric = 'level'      THEN l.value END) AS level_pct,
       MAX(CASE WHEN l.metric = 'cota'       THEN l.value END) AS cota_m,
       MAX(CASE WHEN l.metric = 'storage'    THEN l.value END) AS volume_hm3,
       MAX(CASE WHEN l.metric = 'inflow'     THEN l.value END) AS inflow_m3s,
       MAX(CASE WHEN l.metric = 'outflow'    THEN l.value END) AS outflow_m3s,
       MAX(CASE WHEN l.metric = 'ph'         THEN l.value END) AS ph,
       MAX(CASE WHEN l.metric = 'water_temp' THEN l.value END) AS water_temp_c,
       MAX(l.recorded_at)                                    AS last_reading_at,
       (SELECT COUNT(*) FROM alerts a
         WHERE a.reservoir_id = r.id AND a.status <> 'resolved') AS open_alerts
  FROM reservoirs r
  JOIN companies c ON c.id = r.company_id
  LEFT JOIN (SELECT reservoir_id, metric, MAX(recorded_at) AS ultimo
               FROM readings
              GROUP BY reservoir_id, metric) u
         ON u.reservoir_id = r.id
  LEFT JOIN readings l
         ON l.reservoir_id = u.reservoir_id
        AND l.metric = u.metric
        AND l.recorded_at = u.ultimo
 GROUP BY r.id, r.code, r.name, r.company_id, c.name, r.capacity_hm3;
