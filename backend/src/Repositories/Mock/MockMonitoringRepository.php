<?php
/**
 * Aquapulse — repositório do MODO DEMONSTRAÇÃO (sem banco de dados).
 *
 * Lê backend/storage/mock/monitoring.php e devolve as mesmas estruturas do
 * PdoMonitoringRepository, para as telas funcionarem sem MySQL. É somente
 * leitura: não há ingestão, cadastro nem tratamento de alertas neste modo.
 *
 * O arquivo guarda só o valor atual de cada grandeza; o histórico dos gráficos
 * é gerado por Support\HistoryGenerator (o mesmo usado na carga do banco).
 */

declare(strict_types=1);

namespace Aquapulse\Repositories\Mock;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Services\EquipmentRules;
use Aquapulse\Services\ReservoirCurve;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\Clock;
use Aquapulse\Support\HistoryGenerator;
use DateTimeImmutable;

final class MockMonitoringRepository implements MonitoringRepositoryInterface
{
    /** Dias de série diária gerados para cada grandeza (cobre o gráfico de 90 dias). */
    private const DIAS = 120;

    /** @var array<string,mixed>|null */
    private ?array $data = null;

    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? AQ_BACKEND_PATH . '/storage/mock/monitoring.php';
    }

    private function db(): array
    {
        if ($this->data === null) {
            $this->data = is_file($this->file) ? (array) require $this->file : [];
        }
        return $this->data;
    }

    /* ------------------------------------------------- empresas e represas */

    public function companies(): array
    {
        return $this->db()['companies'] ?? [];
    }

    public function reservoirs(string $companyId = 'all'): array
    {
        $lista = [];
        foreach ($this->db()['reservoirs'] ?? [] as $r) {
            if ($companyId === 'all' || $r['company_id'] === $companyId) {
                $lista[] = $this->completar($r);
            }
        }
        return $lista;
    }

    public function reservoir(string $reservoirId): ?array
    {
        foreach ($this->db()['reservoirs'] ?? [] as $r) {
            if ($r['id'] === $reservoirId) {
                return $this->completar($r);
            }
        }
        return null;
    }

    /** Acrescenta ao cadastro os campos calculados que o repositório do banco devolve. */
    private function completar(array $r): array
    {
        $id = (string) $r['id'];
        $sensores = $this->sensors($id);
        $chuva = $this->serieDiaria($r, 'precipitation');
        $cota = ReservoirCurve::cota($r, (float) $r['level_pct']);
        $nivelOntem = $this->serieHoraria($r, 'level')[0];   // primeiro dos 24 pontos horários

        return $r + [
            'cota_m'             => $cota,
            'volume_hm3'         => ReservoirCurve::storage($r, (float) $r['level_pct']),
            'ph_min'             => round((float) $r['ph'] - 0.3, 2),
            'ph_max'             => round((float) $r['ph'] + 0.3, 2),
            'rain_prev_24h_mm'   => $chuva[self::DIAS - 2],
            'rain_7d_mm'         => round(array_sum(array_slice($chuva, -7)), 1),
            'rain_month_mm'      => round(array_sum(array_slice($chuva, -(int) Clock::now()->format('j'))), 1),
            'cota_variation_m'   => round($cota - ReservoirCurve::cota($r, $nivelOntem), 1),
            'inflow_24h_ago_m3s' => $this->serieHoraria($r, 'inflow')[0],
            'sensors_online'     => count(array_filter($sensores, static fn (array $s): bool => $s['status'] === 'online')),
            'sensors_total'      => count($sensores),
            'open_alerts'        => count(array_filter($this->alerts($id), static fn (array $a): bool => $a['status'] !== 'resolved')),
            'last_reading_at'    => Clock::iso(Clock::now()->modify('-2 minutes')),
        ];
    }

    /* -------------------------------------------------------------- séries */

    /** Valor atual da grandeza no arquivo (cota e volume saem do nível). */
    private function atual(array $r, string $metric): float
    {
        $campos = ['level' => 'level_pct', 'inflow' => 'inflow_m3s', 'outflow' => 'outflow_m3s',
                   'ph' => 'ph', 'water_temp' => 'water_temp_c', 'precipitation' => 'rain_24h_mm'];

        return (float) ($r[$campos[$metric] ?? ''] ?? 0);
    }

    /**
     * Série diária dos últimos DIAS dias, terminando no valor atual.
     *
     * @return array<int,float>
     */
    private function serieDiaria(array $r, string $metric): array
    {
        if ($metric === 'cota' || $metric === 'storage') {
            // derivadas do nível, ponto a ponto
            return array_map(
                static fn (float $nivel): float => $metric === 'cota' ? ReservoirCurve::cota($r, $nivel) : ReservoirCurve::storage($r, $nivel),
                $this->serieDiaria($r, 'level')
            );
        }

        $semente = HistoryGenerator::seed($r['id'] . '|' . $metric);

        if ($metric === 'precipitation') {
            return HistoryGenerator::dailyRain(self::DIAS, $this->atual($r, $metric), $semente);
        }

        $cfg = HistoryGenerator::WAVES[$metric] ?? null;
        if ($cfg === null) {
            return array_fill(0, self::DIAS, 0.0);
        }

        return HistoryGenerator::wave(self::DIAS, $this->atual($r, $metric), $cfg['amplitude'], $cfg['drift'], $semente, $cfg['decimals']);
    }

    /**
     * Série das últimas 24 horas (um ponto por hora), terminando no valor atual.
     *
     * @return array<int,float>
     */
    private function serieHoraria(array $r, string $metric): array
    {
        if ($metric === 'cota' || $metric === 'storage') {
            return array_map(
                static fn (float $nivel): float => $metric === 'cota' ? ReservoirCurve::cota($r, $nivel) : ReservoirCurve::storage($r, $nivel),
                $this->serieHoraria($r, 'level')
            );
        }

        $semente = HistoryGenerator::seed($r['id'] . '|' . $metric);

        if ($metric === 'precipitation') {
            return HistoryGenerator::spreadRain($this->atual($r, $metric), $semente);
        }

        $cfg = HistoryGenerator::WAVES[$metric] ?? null;
        if ($cfg === null) {
            return array_fill(0, 24, 0.0);
        }

        return HistoryGenerator::wave(24, $this->atual($r, $metric), $cfg['amplitude'] * 0.6, $cfg['drift'] * 0.15, $semente, $cfg['decimals']);
    }

    public function series(string $reservoirId, string $metric, string $period): array
    {
        $r = $this->reservoir($reservoirId);
        if ($r === null) {
            return ['labels' => [], 'values' => []];
        }

        $agora = Clock::now();

        if ($period === '24h') {
            $labels = [];
            for ($i = 23; $i >= 0; $i--) {
                $labels[] = $agora->modify("-{$i} hours")->format('H:00');
            }
            return ['labels' => $labels, 'values' => $this->serieHoraria($r, $metric)];
        }

        if ($period === '12m') {
            // um ponto por mês: média (ou soma, para chuva) de blocos de 30 dias
            $diaria = $this->serieDiaria($r, $metric);
            $labels = [];
            $values = [];
            for ($i = 3; $i >= 0; $i--) {
                $bloco = array_slice($diaria, self::DIAS - ($i + 1) * 30, 30);
                $labels[] = Clock::shortMonth($agora->modify("first day of -{$i} months"));
                $values[] = round($metric === 'precipitation' ? array_sum($bloco) : array_sum($bloco) / count($bloco), 1);
            }
            return ['labels' => $labels, 'values' => $values];
        }

        $dias = ['7d' => 7, '30d' => 30, '90d' => 90][$period] ?? 7;
        return $this->dailySeries($reservoirId, $metric, $agora->modify('-' . ($dias - 1) . ' days'), $agora);
    }

    public function dailySeries(string $reservoirId, string $metric, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $r = $this->reservoir($reservoirId);
        if ($r === null) {
            return ['labels' => [], 'values' => []];
        }

        $diaria = $this->serieDiaria($r, $metric);
        $hoje = Clock::now()->setTime(0, 0);
        $labels = [];
        $values = [];

        for ($dia = $from->setTime(0, 0); $dia <= $to->setTime(0, 0); $dia = $dia->modify('+1 day')) {
            $atras = (int) $dia->diff($hoje)->format('%r%a');   // há quantos dias foi; negativo = futuro
            if ($atras < 0 || $atras >= self::DIAS) {
                continue;
            }
            $labels[] = Clock::shortDate($dia);
            $values[] = $diaria[self::DIAS - 1 - $atras];
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

        $agora = Clock::now();
        $linhas = [];

        if ($metric === 'storage') {
            $volume = array_reverse($this->serieDiaria($r, 'storage'));   // do dia mais recente para trás
            $nivel = array_reverse($this->serieDiaria($r, 'level'));

            for ($i = 0; $i < $limit; $i++) {
                $dia = $agora->modify("-{$i} days");
                $linhas[] = [
                    'at'        => Clock::iso($dia),
                    'date'      => Clock::date($dia),
                    'volume'    => round($volume[$i], 0),
                    'occupancy' => $nivel[$i],
                    'variation' => round($volume[$i] - $volume[$i + 1], 0),
                    'status'    => StatusRules::fromLevel($nivel[$i]),
                ];
            }
            return $linhas;
        }

        // demais tabelas: uma linha por hora, da mais recente para trás
        $serie = fn (string $m): array => array_reverse($this->serieHoraria($r, $m));
        $entrada = $serie('inflow');
        $saida = $serie('outflow');
        $nivel = $serie('level');
        $ph = $serie('ph');
        $temperatura = $serie('water_temp');

        for ($i = 0; $i < $limit; $i++) {
            $quando = $agora->modify("-{$i} hours");
            $base = ['at' => Clock::iso($quando), 'time' => Clock::dateTime($quando)];

            switch ($metric) {
                case 'inflow':
                    $linhas[] = $base + ['inflow' => $entrada[$i], 'outflow' => $saida[$i], 'balance' => round($entrada[$i] - $saida[$i], 1)];
                    break;

                case 'level':
                    $cota = ReservoirCurve::cota($r, $nivel[$i]);
                    $linhas[] = $base + ['cota' => $cota, 'level' => $nivel[$i], 'variation' => round($cota - ReservoirCurve::cota($r, $nivel[23]), 1)];
                    break;

                case 'ph':
                    $linhas[] = $base + ['ph' => $ph[$i], 'temp' => $temperatura[$i], 'point' => 'Sonda de pH', 'status' => StatusRules::fromPh($ph[$i])];
                    break;
            }
        }
        return $linhas;
    }

    /* ---------------------------------------------------- listas auxiliares */

    public function sensors(string $reservoirId): array
    {
        $ultima = Clock::now()->modify('-2 minutes');
        $lista = [];

        foreach ($this->db()['sensors'][$reservoirId] ?? [] as $s) {
            $transmitindo = $s['status'] === 'active' && $s['online'];
            $comunicacao = EquipmentRules::communication($s['status'], $transmitindo ? $ultima : null, Clock::now());

            $lista[] = [
                'id'                 => $s['id'],
                'name'               => $s['name'],
                'location'           => $s['location'],
                'type'               => $s['type'],
                'type_label'         => EquipmentRules::label($s['type']),
                'unit'               => EquipmentRules::unit($s['type']),
                'status'             => $comunicacao,
                'status_label'       => EquipmentRules::communicationLabel($comunicacao),
                'last_value'         => null,
                'last_reading_at'    => $transmitindo ? Clock::iso($ultima) : null,
                'last_reading_label' => $transmitindo ? Clock::relative($ultima) : 'Nenhuma leitura',
            ];
        }
        return $lista;
    }

    public function maintenances(string $reservoirId): array
    {
        return $this->db()['maintenances'][$reservoirId] ?? [];
    }

    /* ------------------------------------------------- alertas e relatórios */

    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        return array_values(array_filter(
            $this->db()['alerts'] ?? [],
            static function (array $a) use ($reservoirId, $severity, $status): bool {
                return ($reservoirId === 'all' || $a['reservoir_id'] === $reservoirId)
                    && ($severity === 'all' || $a['severity'] === $severity)
                    && ($status === 'all' || $a['status'] === $status);
            }
        ));
    }

    public function openAlertCount(): int
    {
        return count(array_filter($this->alerts(), static fn (array $a): bool => $a['status'] !== 'resolved'));
    }

    public function reports(string $reservoirId = 'all', string $type = 'all'): array
    {
        return array_values(array_filter(
            $this->db()['reports'] ?? [],
            static function (array $r) use ($reservoirId, $type): bool {
                return ($reservoirId === 'all' || $r['reservoir_id'] === $reservoirId)
                    && ($type === 'all' || $r['type'] === $type);
            }
        ));
    }

    /** Sem banco não há tabela de limites: valem os padrões de StatusRules. */
    public function limits(): array
    {
        return StatusRules::limits();
    }
}
