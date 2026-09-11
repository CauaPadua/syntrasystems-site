<?php
declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Repositories\Mock\MockMonitoringRepository;
use Aquapulse\Repositories\MockUserRepository;
use Aquapulse\Repositories\PdoUserRepository;
use Aquapulse\Repositories\UserRepositoryInterface;

final class Container
{
    private static ?MonitoringRepositoryInterface $monitoring = null;
    private static ?UserRepositoryInterface $users = null;

    public static function monitoring(): MonitoringRepositoryInterface
    {
        if (self::$monitoring === null) {
            self::$monitoring = new MockMonitoringRepository();
        }
        return self::$monitoring;
    }

    public static function users(): UserRepositoryInterface
    {
        if (self::$users === null) {
            if (getenv('DB_HOST')) {
                self::$users = new PdoUserRepository(Database::conexao());
            } else {
                self::$users = new MockUserRepository();
            }
        }
        return self::$users;
    }
}
