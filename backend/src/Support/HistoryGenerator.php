<?php
/**
 * Aquapulse — gerador do histórico simulado.
 *
 * O painel precisa de um passado para desenhar os gráficos de 7 dias, 30 dias e
 * 12 meses. Como não houve equipamento transmitindo antes da instalação, esse
 * passado é fabricado aqui: curvas suaves que terminam no valor inicial de cada
 * grandeza. Do momento da instalação em diante, os dados passam a vir das
 * leituras enviadas pelos equipamentos (ou pelo simulador).
 *
 * É determinístico: não usa rand() nem time(). A mesma entrada gera sempre as
 * mesmas curvas.
 *
 * Quem usa:
 *   backend/database/migrate.php            carga inicial do banco (seed)
 *   Repositories\Mock\MockMonitoringRepository  gráficos do modo demonstração
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Services\ReservoirCurve;
use DateTimeImmutable;

final class HistoryGenerator
{
    /** Horas com uma leitura por hora, contadas para trás a partir de agora. */
    public const HOURS = 48;

    /** Dias de histórico, com uma leitura por dia antes da janela horária. */
    public const DAYS = 400;

    /**
     * Forma da curva de cada grandeza medida.
     *   amplitude  tamanho da oscilação
     *   drift      quanto a série sobe do primeiro ao último ponto
     *   decimals   casas decimais gravadas
     */
    public const WAVES = [
        'level'      => ['amplitude' => 1.4,  'drift' => 2.2, 'decimals' => 1],
        'inflow'     => ['amplitude' => 5.0,  'drift' => 4.2, 'decimals' => 1],
        'outflow'    => ['amplitude' => 3.6,  'drift' => 2.2, 'decimals' => 1],
        'ph'         => ['amplitude' => 0.14, 'drift' => 0.1, 'decimals' => 2],
        'water_temp' => ['amplitude' => 0.6,  'drift' => 0.4, 'decimals' => 1],
    ];

    /** Número entre 0 e 0,999 derivado de um texto: desloca a onda de cada série. */
    public static function seed(string $key): float
    {
        return (crc32($key) % 1000) / 1000.0;
    }

    /**
     * Série com tendência + oscilação, terminando exatamente em $end.
     *
     * @return array<int,float> do ponto mais antigo para o mais recente
     */
    public static function wave(int $count, float $end, float $amplitude, float $drift, float $seed, int $decimals): array
    {
        $values = [];
        $start = $end - $drift;

        for ($i = 0; $i < $count; $i++) {
            $t = $count > 1 ? $i / ($count - 1) : 1.0;       // 0 no primeiro ponto, 1 no último
            $base = $start + ($end - $start) * $t;           // tendência linear

            $osc = sin(($i * 0.9) + $seed * 6.283) * $amplitude
                 + sin(($i * 0.37) + $seed * 3.14) * ($amplitude * 0.45);

            // A oscilação some só nos últimos pontos, para a série terminar no
            // valor exato sem achatar os dias anteriores.
            $osc *= 1 - pow($t, 60);

            $values[] = round($base + $osc, $decimals);
        }

        $values[$count - 1] = round($end, $decimals);
        return $values;
    }

    /**
     * Totais diários de chuva: valores independentes e nunca negativos.
     *
     * @return array<int,float> do dia mais antigo para o mais recente
     */
    public static function dailyRain(int $count, float $last, float $seed): array
    {
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = round(abs(sin(($i * 1.7) + $seed * 6.283)) * ($last * 0.85) + 2.0, 1);
        }
        $values[$count - 1] = round($last, 1);
        return $values;
    }

    /**
     * Distribui o total de chuva de um dia em 24 parcelas horárias. A soma das
     * parcelas devolve o total, então o acumulado de 24 h calculado pelo banco
     * (SUM) confere com o valor de origem.
     *
     * @return array<int,float>
     */
    public static function spreadRain(float $total, float $seed): array
    {
        $pesos = [];
        $soma = 0.0;
        for ($h = 0; $h < 24; $h++) {
            $p = abs(sin(($h * 0.7) + $seed * 6.283)) + 0.05; // +0,05 evita hora com peso zero
            $pesos[] = $p;
            $soma += $p;
        }

        return array_map(static fn (float $p): float => round($total * ($p / $soma), 2), $pesos);
    }

    /**
     * Instantes do histórico: um por dia (ao meio-dia) até a véspera da janela
     * horária e, dentro dela, um por hora cheia até agora.
     *
     * @return array{days:array<int,DateTimeImmutable>,hours:array<int,DateTimeImmutable>}
     */
    public static function timeline(DateTimeImmutable $now): array
    {
        $horaAtual = $now->setTime((int) $now->format('H'), 0);

        $hours = [];
        for ($i = self::HOURS; $i >= 0; $i--) {
            $hours[] = $horaAtual->modify("-{$i} hours");
        }

        $primeiroDiaHorario = $hours[0]->setTime(0, 0);
        $days = [];
        for ($i = self::DAYS; $i >= 1; $i--) {
            $days[] = $primeiroDiaHorario->modify("-{$i} days")->setTime(12, 0);
        }

        return ['days' => $days, 'hours' => $hours];
    }

    /**
     * Leituras de uma represa prontas para gravar.
     *
     * @param array<string,mixed>  $reservoir cadastro (id, capacity_hm3, cota_min_m, cota_max_m)
     * @param array<string,float>  $start     valor inicial de cada grandeza (level, inflow, outflow, ph, water_temp, rain_24h)
     * @param array<string,string> $sensors   equipamento responsável por cada grandeza: [grandeza => código]
     * @return array<int,array{sensor_id:?string,reservoir_id:string,metric:string,value:float,recorded_at:string}>
     */
    public static function reservoirReadings(array $reservoir, array $start, array $sensors, DateTimeImmutable $now): array
    {
        $id = (string) $reservoir['id'];
        $tempo = self::timeline($now);
        $linhas = [];

        $linha = static function (?string $sensor, string $metric, float $valor, DateTimeImmutable $quando) use ($id): array {
            return ['sensor_id' => $sensor, 'reservoir_id' => $id, 'metric' => $metric, 'value' => $valor, 'recorded_at' => Clock::sql($quando)];
        };

        foreach (self::WAVES as $metric => $cfg) {
            if (!isset($start[$metric])) {
                continue;
            }

            $fim = (float) $start[$metric];
            $semente = self::seed($id . '|' . $metric);
            $sensor = $sensors[$metric] ?? null;

            // Em poucas horas a grandeza varia menos do que ao longo de um ano.
            $diarios = self::wave(count($tempo['days']), $fim, $cfg['amplitude'], $cfg['drift'], $semente, $cfg['decimals']);
            $horarios = self::wave(count($tempo['hours']), $fim, $cfg['amplitude'] * 0.6, $cfg['drift'] * 0.15, $semente, $cfg['decimals']);

            $pontos = array_merge(
                array_map(null, $tempo['days'], $diarios),
                array_map(null, $tempo['hours'], $horarios)
            );

            foreach ($pontos as [$quando, $valor]) {
                $linhas[] = $linha($sensor, $metric, $valor, $quando);

                // Cota e volume acompanham o nível, pela mesma conta usada na ingestão.
                if ($metric === 'level') {
                    $linhas[] = $linha($sensor, 'cota', ReservoirCurve::cota($reservoir, $valor), $quando);
                    $linhas[] = $linha($sensor, 'storage', ReservoirCurve::storage($reservoir, $valor), $quando);
                }
            }
        }

        /* --- chuva: um total por dia no passado e parcelas horárias na janela recente */
        if (isset($start['rain_24h'])) {
            $semente = self::seed($id . '|precipitation');
            $sensor = $sensors['precipitation'] ?? null;
            $totais = self::dailyRain(count($tempo['days']) + 3, (float) $start['rain_24h'], $semente);

            foreach ($tempo['days'] as $i => $quando) {
                $linhas[] = $linha($sensor, 'precipitation', $totais[$i], $quando);
            }

            // As 24 horas mais recentes somam exatamente o valor inicial informado.
            $ultimo = count($totais) - 1;
            $blocos = [];
            for ($b = 0; $b <= 2; $b++) {
                $blocos[$b] = self::spreadRain($totais[$ultimo - $b], $semente);
            }

            $total = count($tempo['hours']);
            foreach ($tempo['hours'] as $i => $quando) {
                $posicao = $total - 1 - $i;                   // 0 = hora atual
                $linhas[] = $linha($sensor, 'precipitation', $blocos[intdiv($posicao, 24)][23 - ($posicao % 24)], $quando);
            }
        }

        return $linhas;
    }

    /**
     * Medições horárias de um ponto de coleta de pH.
     *
     * @return array<int,array{0:float,1:float,2:string}> [pH, temperatura, instante]
     */
    public static function phPointReadings(string $key, float $ph, float $temp, DateTimeImmutable $now): array
    {
        $horas = self::timeline($now)['hours'];
        $semente = self::seed($key);

        $phs = self::wave(count($horas), $ph, 0.12, 0.08, $semente, 2);
        $temps = self::wave(count($horas), $temp, 0.5, 0.3, $semente, 1);

        $linhas = [];
        foreach ($horas as $i => $quando) {
            $linhas[] = [$phs[$i], $temps[$i], Clock::sql($quando)];
        }
        return $linhas;
    }

    /**
     * Medições horárias de uma estação pluviométrica. As últimas 24 h somam o
     * acumulado informado; os blocos anteriores ficam mais secos.
     *
     * @return array<int,array{0:float,1:string}> [chuva em mm, instante]
     */
    public static function rainStationReadings(string $stationId, float $rain24h, DateTimeImmutable $now): array
    {
        $horas = self::timeline($now)['hours'];
        $semente = self::seed('estacao|' . $stationId);

        $blocos = [];
        for ($b = 0; $b <= 2; $b++) {
            $blocos[$b] = self::spreadRain(max(round($rain24h * (1.0 - 0.28 * $b), 1), 0.0), $semente);
        }

        $total = count($horas);
        $linhas = [];
        foreach ($horas as $i => $quando) {
            $posicao = $total - 1 - $i;
            $linhas[] = [$blocos[intdiv($posicao, 24)][23 - ($posicao % 24)], Clock::sql($quando)];
        }
        return $linhas;
    }
}
