-- =============================================================================
-- SEED 004 — limites operacionais (faixas Normal / Atenção / Crítico)
--
-- É a fonte dos números que classificam cada grandeza: o sistema carrega estas
-- linhas e as aplica em todas as telas e na abertura de alertas
-- (backend/src/Services/StatusRules.php).
--
-- Como ler uma linha:
--   attention / critical  valem a partir de min_value (>=);
--   normal com mínimo e máximo é uma faixa ideal — fora dela, atenção.
--
-- Os limites entram como vigentes (valid_to = NULL). Uma mudança futura deve
-- encerrar a linha atual (preencher valid_to) e inserir outra, preservando o
-- histórico do que valia em cada período.
-- =============================================================================

DELETE FROM operational_limits WHERE scope_type = 'global' AND valid_to IS NULL;

INSERT INTO operational_limits (scope_type, scope_id, metric, classification, min_value, max_value, unit, justification) VALUES
('global', NULL, 'level',         'attention', 80.0, 90.0, '%',  'A partir de 80% o nível exige acompanhamento'),
('global', NULL, 'level',         'critical',  90.0, NULL, '%',  'A partir de 90% o nível exige ação imediata'),
('global', NULL, 'ph',            'normal',    6.5,  8.5,  'pH', 'Faixa ideal de pH para a água bruta'),
('global', NULL, 'precipitation', 'attention', 20.0, 60.0, 'mm', 'Chuva acumulada em 24 h que exige acompanhamento'),
('global', NULL, 'precipitation', 'critical',  60.0, NULL, 'mm', 'Chuva acumulada em 24 h considerada crítica');
