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
 *
 * Observação: no código atual a chave que controla isso é a constante
 * Clock::DEMO_MODE, logo abaixo — não existe uma constante AQ_DEMO_CLOCK.
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

    private static ?DateTimeZone $tz = null;                              // fuso reaproveitado entre chamadas (criado uma vez)

    /** Fuso horário oficial do sistema (Brasília), independente da configuração do servidor. */
    public static function timezone(): DateTimeZone
    {
        if (self::$tz === null) {
            self::$tz = new DateTimeZone('America/Sao_Paulo');
        }
        return self::$tz;
    }

    /** "Agora" da aplicação. */
    public static function now(): DateTimeImmutable                      // Immutable: operações como modify() devolvem uma data nova, sem alterar a original
    {
        return self::DEMO_MODE
            ? new DateTimeImmutable(self::DEMO_INSTANT, self::timezone())  // modo demonstrativo: sempre 22/05/2024 09:30
            : new DateTimeImmutable('now', self::timezone());             // modo real: horário atual do servidor, no fuso de Brasília
    }

    /** Momento da última coleta simulada (2 minutos antes do "agora"). */
    public static function lastUpdate(): DateTimeImmutable
    {
        return self::now()->modify('-2 minutes');                         // coerente com o rótulo fixo "há 2 min" enviado em ApiResponse::success()
    }

    /** Formato ISO 8601 (ex.: 2024-05-22T09:28:00-03:00), usado em campos de máquina como meta.generated_at. */
    public static function iso(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->format('c');                          // sem data informada, formata o "agora"
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
        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']; // índice 0 vazio para o mês 1 cair em "Jan"
        return $d->format('d') . ' ' . $meses[(int) $d->format('n')];     // "n" = número do mês sem zero à esquerda (1 a 12)
    }

    /** Mai */
    public static function shortMonth(DateTimeImmutable $d): string
    {
        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']; // nomes em português montados à mão: não depende do locale do servidor
        return $meses[(int) $d->format('n')];
    }

    /** 22 de maio de 2024, 09:30 */
    public static function longDateTime(?DateTimeImmutable $d = null): string
    {
        $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $d = $d ?? self::now();
        return $d->format('d') . ' de ' . $meses[(int) $d->format('n')] . ' de ' . $d->format('Y') . ', ' . $d->format('H:i'); // exibido na topbar do dashboard
    }

    /** 14 de agosto de 2024 */
    public static function longDate(DateTimeImmutable $d): string
    {
        $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return (int) $d->format('d') . ' de ' . $meses[(int) $d->format('n')] . ' de ' . $d->format('Y'); // (int) tira o zero à esquerda do dia: "04" vira "4"
    }
}
