<?php
/**
 * Aquapulse — relógio da aplicação.
 *
 * Todas as datas do sistema passam por aqui, sempre em America/Sao_Paulo.
 *
 * Com banco de dados configurado, "agora" é o horário real do servidor: as
 * telas mostram as leituras conforme chegam dos equipamentos.
 *
 * Sem banco (modo demonstração), "agora" fica fixo no instante dos dados de
 * exemplo de backend/storage/mock/, para que eles continuem coerentes.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    /** Instante dos dados de exemplo, usado apenas no modo demonstração. */
    public const DEMO_INSTANT = '2024-05-22 09:30:00';

    private const MESES_CURTOS = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    private const MESES = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                           'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    private static ?DateTimeZone $tz = null;

    public static function timezone(): DateTimeZone
    {
        if (self::$tz === null) {
            self::$tz = new DateTimeZone('America/Sao_Paulo');
        }
        return self::$tz;
    }

    /** "Agora" da aplicação. */
    public static function now(): DateTimeImmutable
    {
        return Database::configurado()
            ? new DateTimeImmutable('now', self::timezone())
            : new DateTimeImmutable(self::DEMO_INSTANT, self::timezone());
    }

    /** Converte um texto de data (do banco ou ISO 8601) para o fuso da aplicação. */
    public static function parse(string $texto): DateTimeImmutable
    {
        return (new DateTimeImmutable($texto, self::timezone()))->setTimezone(self::timezone());
    }

    /** 2026-11-10T14:03:00-03:00 */
    public static function iso(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('c');
    }

    /** 2026-11-10 14:03:00 (formato aceito pelo MySQL) */
    public static function sql(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('Y-m-d H:i:s');
    }

    /** 10/11/2026 */
    public static function date(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('d/m/Y');
    }

    /** 10/11/2026 14:03 */
    public static function dateTime(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('d/m/Y H:i');
    }

    /** 10 Nov */
    public static function shortDate(DateTimeImmutable $d): string
    {
        return $d->format('d') . ' ' . self::MESES_CURTOS[(int) $d->format('n')];
    }

    /** Nov */
    public static function shortMonth(DateTimeImmutable $d): string
    {
        return self::MESES_CURTOS[(int) $d->format('n')];
    }

    /** 10 de novembro de 2026, 14:03 */
    public static function longDateTime(?DateTimeImmutable $d = null): string
    {
        $d = $d ?? self::now();
        return $d->format('d') . ' de ' . self::MESES[(int) $d->format('n')] . ' de ' . $d->format('Y') . ', ' . $d->format('H:i');
    }

    /**
     * Tempo decorrido até agora, em texto: "há 12 s", "há 3 min", "há 2 h", "há 4 dias".
     * Usado para mostrar quando chegou a última leitura.
     */
    public static function relative(DateTimeImmutable $d): string
    {
        $segundos = max(0, self::now()->getTimestamp() - $d->getTimestamp());

        if ($segundos < 60) {
            return 'há ' . $segundos . ' s';
        }
        if ($segundos < 3600) {
            return 'há ' . intdiv($segundos, 60) . ' min';
        }
        if ($segundos < 86400) {
            return 'há ' . intdiv($segundos, 3600) . ' h';
        }

        $dias = intdiv($segundos, 86400);
        return 'há ' . $dias . ($dias === 1 ? ' dia' : ' dias');
    }
}
