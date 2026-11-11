-- =============================================================================
-- Aquapulse — exemplos de SQL para a apresentação
--
-- Todas as consultas rodam no banco do projeto (phpMyAdmin ou terminal):
--   C:\xampp\mysql\bin\mysql.exe -u root aquapulse_dev < backend/database/consultas_exemplo.sql
--
-- A parte de CRUD roda dentro de uma transação desfeita no final (ROLLBACK),
-- então executar o arquivo inteiro não altera os dados.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. SELECT
-- -----------------------------------------------------------------------------

-- 1.1 Equipamentos ativos, em ordem de represa e grandeza
SELECT id, name, type, reservoir_id
  FROM sensors
 WHERE status = 'active'
 ORDER BY reservoir_id, type;

-- 1.2 As 5 leituras mais recentes de nível da Represa Santa Clara
SELECT recorded_at, value AS nivel_pct, sensor_id
  FROM readings
 WHERE reservoir_id = 'santa-clara' AND metric = 'level'
 ORDER BY recorded_at DESC
 LIMIT 5;

-- 1.3 Média, mínimo e máximo de pH por represa nas últimas 24 horas (GROUP BY)
SELECT reservoir_id,
       ROUND(AVG(value), 2) AS ph_medio,
       MIN(value)           AS ph_minimo,
       MAX(value)           AS ph_maximo,
       COUNT(*)             AS leituras
  FROM readings
 WHERE metric = 'ph' AND recorded_at >= NOW() - INTERVAL 24 HOUR
 GROUP BY reservoir_id;


-- -----------------------------------------------------------------------------
-- 2. JOIN
-- -----------------------------------------------------------------------------

-- 2.1 INNER JOIN: cada represa com o nome da empresa dona
SELECT r.code, r.name AS represa, c.name AS empresa, r.capacity_hm3
  FROM reservoirs r
  JOIN companies c ON c.id = r.company_id
 ORDER BY r.sort_order;

-- 2.2 JOIN com três tabelas: alertas com a represa e o equipamento que enviou a leitura
SELECT a.id, a.title, a.severity, a.status, r.name AS represa, s.name AS equipamento, a.detected_at
  FROM alerts a
  JOIN reservoirs r ON r.id = a.reservoir_id
  LEFT JOIN sensors s ON s.id = a.sensor_id          -- LEFT: o alerta aparece mesmo sem equipamento
 ORDER BY a.detected_at DESC;

-- 2.3 LEFT JOIN + COUNT: quantos equipamentos cada represa tem (inclusive zero)
SELECT r.name AS represa, COUNT(s.id) AS equipamentos
  FROM reservoirs r
  LEFT JOIN sensors s ON s.reservoir_id = r.id
 GROUP BY r.id, r.name;

-- 2.4 Quais empresas cada conta pode consultar (tabela N:N user_company_access)
SELECT u.name AS usuario, u.role AS perfil, c.name AS empresa
  FROM users u
  JOIN user_company_access uca ON uca.user_id = u.id
  JOIN companies c ON c.id = uca.company_id
 ORDER BY u.name;


-- -----------------------------------------------------------------------------
-- 3. SUBCONSULTA
-- -----------------------------------------------------------------------------

-- 3.1 Subconsulta no WHERE: represas cuja ocupação atual está acima da média geral
SELECT reservoir_id, name, level_pct
  FROM vw_resumo_represas
 WHERE level_pct > (SELECT AVG(level_pct) FROM vw_resumo_represas);

-- 3.2 Subconsulta correlacionada: a leitura mais recente de cada equipamento
--     (a mesma ideia usada na view vw_ultimas_medicoes)
SELECT s.id, s.type, l.value, l.recorded_at
  FROM sensors s
  JOIN readings l ON l.sensor_id = s.id AND l.metric = s.type
 WHERE l.recorded_at = (SELECT MAX(l2.recorded_at)
                          FROM readings l2
                         WHERE l2.sensor_id = s.id AND l2.metric = s.type);

-- 3.3 Subconsulta com NOT EXISTS: equipamentos ativos sem nenhuma leitura nos
--     últimos 5 minutos (é assim que o painel decide "sem comunicação")
SELECT s.id, s.name, s.reservoir_id
  FROM sensors s
 WHERE s.status = 'active'
   AND NOT EXISTS (SELECT 1
                     FROM readings l
                    WHERE l.sensor_id = s.id
                      AND l.recorded_at >= NOW() - INTERVAL 5 MINUTE);

-- 3.4 Subconsulta no FROM (tabela derivada): chuva acumulada em 24 h por represa,
--     comparada com o limite de atenção cadastrado
SELECT chuva.reservoir_id, chuva.mm_24h, lim.min_value AS limite_atencao,
       IF(chuva.mm_24h >= lim.min_value, 'Atenção', 'Normal') AS situacao
  FROM (SELECT reservoir_id, ROUND(SUM(value), 1) AS mm_24h
          FROM readings
         WHERE metric = 'precipitation' AND recorded_at >= NOW() - INTERVAL 24 HOUR
         GROUP BY reservoir_id) chuva
  JOIN operational_limits lim ON lim.metric = 'precipitation' AND lim.classification = 'attention';


-- -----------------------------------------------------------------------------
-- 4. VIEWS (consultas salvas no banco — ver migrations/011_views.sql)
-- -----------------------------------------------------------------------------

-- 4.1 Valor atual de cada grandeza, uma linha por represa
SELECT reservoir_id, level_pct, cota_m, volume_hm3, inflow_m3s, outflow_m3s, ph, last_reading_at, open_alerts
  FROM vw_resumo_represas;

-- 4.2 Última leitura de cada equipamento
SELECT sensor_id, metric, status, last_value, last_reading_at
  FROM vw_ultimas_medicoes
 ORDER BY reservoir_id, metric;


-- -----------------------------------------------------------------------------
-- 5. CRUD de equipamentos (o mesmo que a tela Equipamentos faz)
--    Rodado dentro de uma transação desfeita no final.
-- -----------------------------------------------------------------------------

START TRANSACTION;

-- CREATE — cadastrar
INSERT INTO sensors (id, reservoir_id, name, location, type, status)
VALUES ('SEN-NIV-99', 'santa-clara', 'Régua reserva', 'Margem norte', 'level', 'active');

-- READ — consultar
SELECT id, name, type, status FROM sensors WHERE id = 'SEN-NIV-99';

-- UPDATE — editar (colocar em manutenção)
UPDATE sensors SET status = 'maintenance', name = 'Régua reserva (calibração)' WHERE id = 'SEN-NIV-99';
SELECT id, name, status FROM sensors WHERE id = 'SEN-NIV-99';

-- DELETE — excluir
DELETE FROM sensors WHERE id = 'SEN-NIV-99';
SELECT COUNT(*) AS ainda_existe FROM sensors WHERE id = 'SEN-NIV-99';

ROLLBACK;
