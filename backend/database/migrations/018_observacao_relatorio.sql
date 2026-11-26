-- 018 — observação do relatório
--
-- Texto livre escrito por quem gera o relatório. Sai na linha "Observação" do
-- arquivo CSV (Services\ReportService::csv()).

ALTER TABLE reports ADD COLUMN note VARCHAR(500) NULL AFTER owner;
