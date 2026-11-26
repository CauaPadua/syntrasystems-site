-- 014 — o limite do nível passa a ser o de cada represa
--
-- Antes, 80% e 90% valiam para todas as represas. Só que cada uma verte em um
-- nível diferente (a Rio Verde, em 82,1%): a água já passava pelo vertedouro e o
-- painel ainda mostrava "Atenção".
--
-- Agora o nível usa as cotas do cadastro (tabela reservoirs): cota_alert_m abre
-- Atenção e cota_spill_m abre Crítico — ver Services\StatusRules::limitsFor().
-- Aqui ficam só os limites iguais para todas as represas: pH e chuva.

DELETE FROM operational_limits WHERE metric = 'level';
