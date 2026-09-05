<?php
/**
 * Aquapulse — implementação SIMULADA do repositório de monitoramento.
 *
 * TEMPORÁRIA: lê backend/storage/mock/monitoring.php. Não há banco de dados,
 * escrita nem persistência.
 *
 * DETERMINISMO: as séries são geradas por uma função senoidal com semente fixa
 * derivada do ID da represa e da métrica. Nunca usa rand()/time(). O ÚLTIMO
 * ponto de toda série é forçado ao valor atual do KPI, garantindo que gráfico
 * e card nunca se contradigam.
 *
 * SUBSTITUIÇÃO: criar PdoMonitoringRepository implementando
 * MonitoringRepositoryInterface. Ver docs/database-handoff.md.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories\Mock;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;

final class MockMonitoringRepository implements MonitoringRepositoryInterface
{
    /** @var array<string,mixed>|null */
    private ?array $data = null;

    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? AQ_BACKEND_PATH . '/storage/mock/monitoring.php';
    }

    /** Carrega o arquivo simulado uma única vez por requisição. */
    private function db(): array
    {
        if ($this->data === null) {
            $this->data = is_file($this->file) ? (array) require $this->file : [];
        }
        return $this->data;
    }

    /* ------------------------------------------------------------ empresas */

    public function companies(): array
    {
        return $this->db()['companies'] ?? [];
    }

    /* ------------------------------------------------------------ represas */

    public function reservoirs(string $companyId = 'all'): array
    {
        $all = $this->db()['reservoirs'] ?? [];

        if ($companyId === 'all') {
            return $all;
        }

        return array_values(array_filter(
            $all,
            static fn (array $r): bool => $r['company_id'] === $companyId
        ));
    }

    public function reservoir(string $reservoirId): ?array
    {
        foreach ($this->db()['reservoirs'] ?? [] as $r) {
            if ($r['id'] === $reservoirId) {
                return $r;
            }
        }
        return null;
    }

    /* -------------------------------------------------------------- séries */

    /**
     * Semente determinística: mesma represa + métrica sempre produz a mesma
     * variação. Deriva de crc32 para não depender de rand().
     */
    private function seed(string $reservoirId, string $metric): float
    {
        return (crc32($reservoirId . '|' . $metric) % 1000) / 1000.0;
    }

    /**
     * Gera pontos oscilando em torno de uma base e termina exatamente no
     * valor final informado.
     *
     * @return array<int,float>
     */
    private function wave(int $count, float $end, float $amplitude, float $drift, float $seed, int $decimals = 1): array
    {
        $values = [];
        $start = $end - $drift;

        for ($i = 0; $i < $count; $i++) {
            $t = $count > 1 ? $i / ($count - 1) : 1.0;

            // tendência linear do início até o fim
            $base = $start + ($end - $start) * $t;

            // oscilação suave e reprodutível
            $osc = sin(($i * 0.9) + $seed * 6.283) * $amplitude
                 + sin(($i * 0.37) + $seed * 3.14) * ($amplitude * 0.45);

            // a oscilação desaparece no último ponto para cravar o valor atual
            $osc *= (1 - $t * $t);

            $values[] = round($base + $osc, $decimals);
        }

        // garante que o último ponto é exatamente o KPI atual
        $values[$count - 1] = round($end, $decimals);

        return $values;
    }

    /**
     * Rótulos do eixo X conforme o período.
     *
     * @return array<int,string>
     */
    private function labels(string $period, int &$count): array
    {
        $tz = Clock::timezone();
        $now = Clock::now();
        $labels = [];

        switch ($period) {
            case '24h':
                $count = 24;
                $cursor = (clone $now)->modify('-23 hours');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = $cursor->format('H:i');
                    $cursor = $cursor->modify('+1 hour');
                }
                break;

            case '30d':
                $count = 30;
                $cursor = (clone $now)->modify('-29 days');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortDate($cursor);
                    $cursor = $cursor->modify('+1 day');
                }
                break;

            case '90d':
                $count = 91;
                $cursor = clone $now;
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortDate($cursor);
                    $cursor = $cursor->modify('+1 day');
                }
                break;

            case '12m':
                $count = 12;
                $cursor = (clone $now)->modify('first day of -11 months');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortMonth($cursor);
                    $cursor = $cursor->modify('+1 month');
                }
                break;

            case '7d':
            default:
                $count = 7;
                $cursor = (clone $now)->modify('-6 days');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortDate($cursor);
                    $cursor = $cursor->modify('+1 day');
                }
                break;
        }

        unset($tz);
        return $labels;
    }

    public function series(string $reservoirId, string $metric, string $period): array
    {
        $r = $this->reservoir($reservoirId);
        if ($r === null) {
            return ['labels' => [], 'values' => []];
        }

        $count = 7;
        $labels = $this->labels($period, $count);
        $seed = $this->seed($reservoirId, $metric);

        // âncora final + amplitude + deriva por métrica
        switch ($metric) {
            case 'level':
                $values = $this->wave($count, (float) $r['level_pct'], 1.4, 2.2, $seed, 1);
                break;
            case 'cota':
                $values = $this->wave($count, (float) $r['cota_m'], 0.9, 2.4, $seed, 1);
                break;
            case 'flow':
                $values = $this->wave($count, (float) $r['flow_m3s'], 3.2, 9.5, $seed, 1);
                break;
            case 'inflow':
                $values = $this->wave($count, (float) $r['inflow_m3s'], 5.0, 4.2, $seed, 1);
                break;
            case 'outflow':
                $values = $this->wave($count, (float) $r['outflow_m3s'], 3.6, 2.2, $seed, 1);
                break;
            case 'ph':
                $values = $this->wave($count, (float) $r['ph'], 0.14, 0.1, $seed, 2);
                break;
            case 'storage':
                $values = $this->wave($count, (float) $r['volume_hm3'], 18.0, 260.0, $seed, 0);
                break;
            case 'precipitation':
                // chuva não tem tendência: valores independentes e não negativos
                $values = [];
                for ($i = 0; $i < $count; $i++) {
                    $v = abs(sin(($i * 1.7) + $seed * 6.283)) * ((float) $r['rain_24h_mm'] * 0.85) + 2.0;
                    $values[] = round($v, 1);
                }
                $values[$count - 1] = (float) $r['rain_24h_mm'];
                break;
            default:
                $values = array_fill(0, $count, 0.0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /* ------------------------------------------------------------ leituras */

    public function readings(string $reservoirId, string $metric, int $limit = 5): array
    {
        $r = $this->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $rows = [];
        $cursor = Clock::now();

        for ($i = 0; $i < $limit; $i++) {
            $when = (clone $cursor)->modify('-' . ($i * 15) . ' minutes');
            $step = $i * 0.1;

            switch ($metric) {
                case 'flow':
                    $rows[] = [
                        'at'      => Clock::iso($when),
                        'time'    => Clock::dateTime($when),
                        'inflow'  => round((float) $r['inflow_m3s'] - $step * 4, 1),
                        'outflow' => round((float) $r['outflow_m3s'] - $step * 3, 1),
                        'balance' => round(((float) $r['inflow_m3s'] - $step * 4) - ((float) $r['outflow_m3s'] - $step * 3), 1),
                        'status'  => 'online',
                    ];
                    break;

                case 'level':
                    $rows[] = [
                        'at'        => Clock::iso($when),
                        'time'      => Clock::dateTime($when),
                        'cota'      => round((float) $r['cota_m'] - $step * 3, 1),
                        'level'     => round((float) $r['level_pct'] - $step * 3, 1),
                        'variation' => round((float) $r['cota_variation_m'] - $step, 1),
                        // a classificação fica no MonitoringService, não no repositório
                    ];
                    break;

                case 'ph':
                    $rows[] = [
                        'at'     => Clock::iso($when),
                        'time'   => Clock::dateTime($when),
                        'ph'     => round((float) $r['ph'] - $step * 0.5, 1),
                        'temp'   => round((float) $r['water_temp_c'] + $step * 0.5, 1),
                        'point'  => ['Entrada principal', 'Centro do reservatório', 'Próximo à barragem'][$i % 3],
                        'status' => 'normal',
                    ];
                    break;

                case 'storage':
                    $day = (clone Clock::now())->modify('-' . $i . ' days');
                    $rows[] = [
                        'at'        => Clock::iso($day),
                        'date'      => Clock::date($day),
                        'volume'    => round((float) $r['volume_hm3'] - $i * 12, 0),
                        'occupancy' => round((float) $r['level_pct'] - $i * 0.9, 1),
                        'variation' => 12 + $i,
                        'status'    => 'normal',
                    ];
                    break;

                default:
                    $rows[] = [
                        'at'     => Clock::iso($when),
                        'time'   => Clock::dateTime($when),
                        'value'  => 0,
                        'status' => 'normal',
                    ];
            }
        }

        return $rows;
    }

    /* ---------------------------------------------------- listas auxiliares */

    public function sensors(string $reservoirId): array
    {
        return $this->db()['sensors'][$reservoirId] ?? [];
    }

    public function phPoints(string $reservoirId): array
    {
        return $this->db()['ph_points'][$reservoirId] ?? [];
    }

    public function rainStations(string $reservoirId): array
    {
        return $this->db()['rain_stations'][$reservoirId] ?? [];
    }

    public function operationEvents(string $reservoirId): array
    {
        return $this->db()['operation_events'][$reservoirId] ?? [];
    }

    public function maintenances(string $reservoirId): array
    {
        return $this->db()['maintenances'][$reservoirId] ?? [];
    }

    public function scheduledReports(): array
    {
        return $this->db()['scheduled_reports'] ?? [];
    }

    public function settings(): array
    {
        return $this->db()['settings'] ?? [];
    }

    /* ------------------------------------------------------------ filtros */

    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        $rows = $this->db()['alerts'] ?? [];

        return array_values(array_filter($rows, static function (array $a) use ($reservoirId, $severity, $status): bool {
            if ($reservoirId !== 'all' && $a['reservoir_id'] !== $reservoirId) {
                return false;
            }
            if ($severity !== 'all' && $a['severity'] !== $severity) {
                return false;
            }
            if ($status !== 'all' && $a['status'] !== $status) {
                return false;
            }
            return true;
        }));
    }

    public function reports(string $reservoirId = 'all', string $type = 'all', string $status = 'all'): array
    {
        $rows = $this->db()['reports'] ?? [];

        return array_values(array_filter($rows, static function (array $r) use ($reservoirId, $type, $status): bool {
            if ($reservoirId !== 'all' && $r['reservoir_id'] !== $reservoirId) {
                return false;
            }
            if ($type !== 'all' && $r['type'] !== $type) {
                return false;
            }
            if ($status !== 'all' && $r['status'] !== $status) {
                return false;
            }
            return true;
        }));
    }
}
