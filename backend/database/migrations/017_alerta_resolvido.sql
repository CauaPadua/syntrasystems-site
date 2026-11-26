-- 017 — alerta resolvido não reabre sozinho
--
-- Antes, ao resolver um alerta com o valor ainda fora da faixa (ex.: nível em
-- atenção), a leitura seguinte abria outro alerta igual na hora: o contador do
-- menu voltava e parecia que "resolver" não tinha funcionado.
--
-- normalized_at guarda quando o valor daquela grandeza voltou ao normal depois do
-- alerta. Enquanto o último alerta resolvido está sem essa data, o problema é o
-- mesmo que alguém já tratou: só um agravamento (atenção -> crítico) abre outro.
-- Regra em Services\AlertService::evaluate().

ALTER TABLE alerts ADD COLUMN normalized_at DATETIME NULL AFTER resolved_at;
