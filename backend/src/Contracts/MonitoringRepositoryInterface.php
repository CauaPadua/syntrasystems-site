<?php
/**
 * Aquapulse — contrato de LEITURA dos dados de monitoramento.
 *
 * Services e endpoints de consulta dependem apenas desta interface. Há duas
 * implementações, escolhidas por Support\Container:
 *   Repositories\PdoMonitoringRepository        lê o MySQL (uso normal);
 *   Repositories\Mock\MockMonitoringRepository  lê storage/mock (sem banco).
 * As duas devolvem as mesmas estruturas, então as telas não mudam.
 *
 * Operações de escrita não fazem parte deste contrato — ficam em classes que
 * exigem banco: EquipmentRepository, IngestService, AlertService,
 * MaintenanceService e ReportService.
 */

declare(strict_types=1);

namespace Aquapulse\Contracts;

use DateTimeImmutable;

interface MonitoringRepositoryInterface
{
    /**
     * Empresas que o usuário pode consultar.
     *
     * @return array<int,array{id:string,code:string,name:string,manager:string,status:string,status_label:string}>
     */
    public function companies(): array;

    /**
     * Represas com o estado atual (última leitura de cada grandeza).
     *
     * Campos de cada represa:
     *   cadastro    id, code, name, company_id, city, basin, lat, lng, coordinates_label,
     *               capacity_hm3, useful_volume_hm3, technical_reserve_hm3,
     *               daily_consumption_hm3, cota_spill_m, cota_alert_m, cota_min_m, cota_max_m
     *   atuais      level_pct, cota_m, volume_hm3, inflow_m3s, outflow_m3s, ph, water_temp_c
     *   calculados  ph_min, ph_max (24 h), rain_24h_mm, rain_7d_mm, rain_month_mm,
     *               cota_variation_m (24 h), inflow_24h_ago_m3s, rain_prev_24h_mm
     *   situação    sensors_online, sensors_total, open_alerts, last_reading_at (ISO ou null)
     *
     * @param string $companyId ID da empresa ou 'all'
     * @return array<int,array<string,mixed>>
     */
    public function reservoirs(string $companyId = 'all'): array;

    /** Uma represa pelo ID; null se não existir ou estiver fora do escopo do usuário. */
    public function reservoir(string $reservoirId): ?array;

    /**
     * Série de uma grandeza para o gráfico, já agrupada conforme o período:
     * 24h por hora, 7d/30d/90d por dia e 12m por mês.
     *
     * @param string $metric level|cota|storage|inflow|outflow|ph|precipitation|water_temp
     * @param string $period 24h|7d|30d|90d|12m
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function series(string $reservoirId, string $metric, string $period): array;

    /**
     * Série diária entre duas datas (inclusive) — comparação entre períodos.
     *
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function dailySeries(string $reservoirId, string $metric, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Últimas leituras para as tabelas das telas. O formato da linha depende da
     * grandeza: 'inflow' (afluência x defluência), 'level', 'ph' ou 'storage'.
     *
     * @return array<int,array<string,mixed>>
     */
    public function readings(string $reservoirId, string $metric, int $limit = 5): array;

    /**
     * Equipamentos da represa com a última leitura e a situação de comunicação.
     *
     * @return array<int,array{id:string,name:string,location:string,type:string,type_label:string,unit:string,
     *         status:string,status_label:string,last_value:?float,last_reading_at:?string,last_reading_label:string}>
     */
    public function sensors(string $reservoirId): array;

    /** Pontos de coleta de pH. @return array<int,array{name:string,ph:float,status:string}> */
    public function phPoints(string $reservoirId): array;

    /** Estações pluviométricas. @return array<int,array{id:string,name:string,rain_24h:float,status:string}> */
    public function rainStations(string $reservoirId): array;

    /**
     * Alertas, dos mais recentes para os mais antigos.
     *
     * @param string $reservoirId ID ou 'all'
     * @param string $severity    all|critical|attention|info
     * @param string $status      all|new|analysis|resolved
     * @return array<int,array<string,mixed>>
     */
    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array;

    /** Quantidade de alertas ainda não resolvidos (contador do menu). */
    public function openAlertCount(): int;

    /**
     * Relatórios gerados.
     *
     * @param string $type all|operational|hydrological|quality
     * @return array<int,array<string,mixed>>
     */
    public function reports(string $reservoirId = 'all', string $type = 'all'): array;

    /** Manutenções e chamados em aberto. @return array<int,array<string,mixed>> */
    public function maintenances(string $reservoirId): array;

    /**
     * Limites operacionais em vigor, no formato usado por StatusRules::configure().
     *
     * @return array<string,array<string,array{min:?float,max:?float}>>
     */
    public function limits(): array;
}
