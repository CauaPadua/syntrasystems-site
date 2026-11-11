<?php
/**
 * Aquapulse — regras dos equipamentos de medição.
 *
 * Reúne o que o cadastro (CRUD), a API de ingestão e as telas precisam saber
 * sobre um equipamento: quais grandezas existem, em que faixa um valor é
 * fisicamente possível e quando o equipamento é considerado "online".
 *
 * O tipo do equipamento é a grandeza que ele mede e tem o mesmo nome usado em
 * readings.metric (ex.: um equipamento do tipo 'ph' grava leituras 'ph').
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use DateTimeImmutable;

final class EquipmentRules
{
    /**
     * Um equipamento ativo é considerado online enquanto a última leitura dele
     * tiver chegado dentro desta janela (em segundos).
     */
    public const ONLINE_WINDOW = 300;

    /**
     * Grandezas aceitas. min/max delimitam valores fisicamente possíveis: a API
     * recusa o que estiver fora (sensor com defeito ou erro de envio).
     */
    public const TYPES = [
        'level'         => ['label' => 'Nível do reservatório', 'unit' => '%',    'min' => 0.0, 'max' => 100.0,  'decimals' => 1],
        'inflow'        => ['label' => 'Vazão afluente',        'unit' => 'm³/s', 'min' => 0.0, 'max' => 5000.0, 'decimals' => 1],
        'outflow'       => ['label' => 'Vazão defluente',       'unit' => 'm³/s', 'min' => 0.0, 'max' => 5000.0, 'decimals' => 1],
        'ph'            => ['label' => 'pH da água',            'unit' => 'pH',   'min' => 0.0, 'max' => 14.0,   'decimals' => 2],
        'precipitation' => ['label' => 'Precipitação',          'unit' => 'mm',   'min' => 0.0, 'max' => 200.0,  'decimals' => 1],
        'water_temp'    => ['label' => 'Temperatura da água',   'unit' => '°C',   'min' => 0.0, 'max' => 45.0,   'decimals' => 1],
    ];

    /** Situação cadastral (coluna sensors.status). */
    public const STATUS = [
        'active'      => 'Ativo',
        'inactive'    => 'Desativado',
        'maintenance' => 'Em manutenção',
    ];

    public static function isType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }

    public static function unit(string $type): string
    {
        return self::TYPES[$type]['unit'] ?? '';
    }

    /** O valor está dentro da faixa fisicamente possível para a grandeza? */
    public static function inRange(string $type, float $value): bool
    {
        $regra = self::TYPES[$type] ?? null;
        return $regra !== null && $value >= $regra['min'] && $value <= $regra['max'];
    }

    /**
     * Situação de comunicação, calculada a partir do cadastro e da última leitura.
     *
     * @return string 'online' | 'offline' | 'maintenance' | 'inactive'
     */
    public static function communication(string $status, ?DateTimeImmutable $lastReading, DateTimeImmutable $now): string
    {
        if ($status !== 'active') {
            return $status;                                   // desativado ou em manutenção: não se espera leitura
        }
        if ($lastReading === null) {
            return 'offline';                                 // ativo, mas nunca transmitiu
        }

        return ($now->getTimestamp() - $lastReading->getTimestamp()) <= self::ONLINE_WINDOW ? 'online' : 'offline';
    }

    public static function communicationLabel(string $communication): string
    {
        switch ($communication) {
            case 'online':      return 'Online';
            case 'offline':     return 'Sem comunicação';
            case 'maintenance': return 'Em manutenção';
            case 'inactive':    return 'Desativado';
            default:            return $communication;
        }
    }
}
