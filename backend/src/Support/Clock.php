<?php
/**
 * Aquapulse — relógio da aplicação.
 *
 * Todas as datas do sistema passam por aqui, sempre em America/Sao_Paulo.
 *
 * MODO DEMONSTRATIVO: por padrão o "agora" é fixado em 22/05/2024 09:30 -03:00,
 * o mesmo instante das referências visuais. Isso mantém os dados simulados
 * determinísticos — a mesma requisição sempre devolve a mesma resposta, sem
 * números mudando a cada atualização.
 *
 * Ao conectar o banco, basta AQ_DEMO_CLOCK = false para o sistema passar a usar
 * o horário real.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    /** Instante de referência das capturas usadas como modelo visual. */
    public const DEMO_INSTANT = '2024-05-22 09:30:00';

    /** true = horário demonstrativo fixo; false = horário real do servidor. */
    public const DEMO_MODE = true;

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
        return self::DEMO_MODE
            ? new DateTimeImmutable(self::DEMO_INSTANT, self::timezone())
            : new DateTimeImmutable('now', self::timezone());
    }

    /** Momento da última coleta simulada (2 minutos antes do "agora"). */
    public static function lastUpdate(): DateTimeImmutable
    {
        return self::now()->modify('-2 minutes');
    }

    public static function iso(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('c');
    }

    /** 22/05/2024 */
    public static function date(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('d/m/Y');
    }

    /** 22/05/2024 09:30 */
    public static function dateTime(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('d/m/Y H:i');
    }

    /** 16 Mai */
    public static function shortDate(DateTimeImmutable $d): string
    {
        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        return $d->format('d') . ' ' . $meses[(int) $d->format('n')];
    }

    /** Mai */
    public static function shortMonth(DateTimeImmutable $d): string
    {
        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        return $meses[(int) $d->format('n')];
    }

    /** 22 de maio de 2024, 09:30 */
    public static function longDateTime(?DateTimeImmutable $d = null): string
    {
        $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $d = $d ?? self::now();
        return $d->format('d') . ' de ' . $meses[(int) $d->format('n')] . ' de ' . $d->format('Y') . ', ' . $d->format('H:i');
    }

    /** 14 de agosto de 2024 */
    public static function longDate(DateTimeImmutable $d): string
    {
        $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return (int) $d->format('d') . ' de ' . $meses[(int) $d->format('n')] . ' de ' . $d->format('Y');
    }
}
