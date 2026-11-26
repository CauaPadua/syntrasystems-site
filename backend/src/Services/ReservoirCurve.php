<?php
/**
 * Aquapulse — contas que dependem do cadastro da represa.
 *
 * O equipamento de nível envia apenas o percentual de ocupação. Cota (m) e
 * volume (hm³) são calculados aqui, sempre pela mesma fórmula, para que os
 * três números nunca se contradigam:
 *
 *   cota   = cota de 0% + (cota de 100% - cota de 0%) x nível / 100
 *   volume = capacidade x nível / 100
 *
 * É uma aproximação linear — a curva cota x volume de uma represa real não é
 * uma reta.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

final class ReservoirCurve
{
    /**
     * @param array<string,mixed> $reservoir precisa de cota_min_m e cota_max_m
     */
    public static function cota(array $reservoir, float $levelPct): float
    {
        $min = (float) $reservoir['cota_min_m'];
        $max = (float) $reservoir['cota_max_m'];

        return round($min + ($max - $min) * $levelPct / 100, 1);
    }

    /**
     * Caminho inverso: o nível (%) que corresponde a uma cota. É como as cotas de
     * alerta e de vertimento do cadastro viram os limites do nível (StatusRules).
     *
     * O resultado é arredondado para baixo, com uma casa (a precisão do sensor de
     * nível): o aviso sai alguns centímetros antes da cota, nunca depois.
     *
     * @param array<string,mixed> $reservoir precisa de cota_min_m e cota_max_m
     */
    public static function level(array $reservoir, float $cota): float
    {
        $min = (float) $reservoir['cota_min_m'];
        $max = (float) $reservoir['cota_max_m'];
        $pct = ($cota - $min) / ($max - $min) * 100;

        return floor(round($pct * 10, 4)) / 10;               // o round interno tira o erro de ponto flutuante (84,99999... é 85)
    }

    /**
     * @param array<string,mixed> $reservoir precisa de capacity_hm3
     */
    public static function storage(array $reservoir, float $levelPct): float
    {
        return round((float) $reservoir['capacity_hm3'] * $levelPct / 100, 1);
    }

    /**
     * Estimativa simples de autonomia: volume acima da reserva técnica dividido
     * pelo consumo médio diário cadastrado. Não considera chuva, evaporação nem
     * variação de consumo — é uma conta de referência, não uma previsão.
     *
     * @param array<string,mixed> $reservoir precisa de volume_hm3, technical_reserve_hm3 e daily_consumption_hm3
     * @return int|null null quando ainda não há leitura do volume
     */
    public static function durationDays(array $reservoir): ?int
    {
        if ($reservoir['volume_hm3'] === null) {
            return null;
        }

        $consumo = (float) $reservoir['daily_consumption_hm3'];
        if ($consumo <= 0) {
            return 0;
        }

        $disponivel = (float) $reservoir['volume_hm3'] - (float) $reservoir['technical_reserve_hm3'];
        return max(0, (int) floor($disponivel / $consumo));
    }
}
