<?php
/**
 * Aquapulse — montagem das dependências do sistema.
 *
 * ============================================================================
 * PONTO ÚNICO DE TROCA PARA O BANCO DE DADOS
 * ============================================================================
 *
 * Hoje devolve MockMonitoringRepository. Quando o banco existir, troque APENAS
 * o corpo de monitoring() por:
 *
 *     return self::$monitoring ??= new PdoMonitoringRepository($pdo);
 *
 * Nenhum endpoint, service, tela ou JavaScript precisa mudar: todos dependem
 * de MonitoringRepositoryInterface.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Repositories\Mock\MockMonitoringRepository;
use Aquapulse\Repositories\MockUserRepository;
use Aquapulse\Repositories\UserRepositoryInterface;

final class Container
{
    private static ?MonitoringRepositoryInterface $monitoring = null;
    private static ?UserRepositoryInterface $users = null;

    /** Repositório de monitoramento (hoje simulado). */
    public static function monitoring(): MonitoringRepositoryInterface
    {
        if (self::$monitoring === null) {
            // TROCA FUTURA: new PdoMonitoringRepository($pdo)
            self::$monitoring = new MockMonitoringRepository();
        }
        return self::$monitoring;
    }

    /** Repositório de usuários (etapa de login, já existente). */
    public static function users(): UserRepositoryInterface
    {
        if (self::$users === null) {
            // TROCA FUTURA: new PdoUserRepository($pdo)
            self::$users = new MockUserRepository();
        }
        return self::$users;
    }
}
