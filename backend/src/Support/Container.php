<?php
/**
 * Aquapulse — ponto único de escolha da fonte de dados.
 *
 * Quem precisa de dados pede o repositório aqui, em vez de instanciar uma
 * classe concreta. A decisão fica em um só lugar:
 *   - há DB_HOST no ambiente (backend/.env)  -> repositórios PDO (MySQL);
 *   - não há                                 -> repositórios de demonstração,
 *                                               somente leitura (storage/mock).
 *
 * Gravações (ingestão de leituras, cadastro de equipamentos, alertas,
 * chamados e relatórios) só existem com banco: ver requireDatabase().
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Repositories\Mock\MockMonitoringRepository;
use Aquapulse\Repositories\MockUserRepository;
use Aquapulse\Repositories\PdoMonitoringRepository;
use Aquapulse\Repositories\PdoUserRepository;
use Aquapulse\Repositories\UserRepositoryInterface;
use Aquapulse\Services\StatusRules;
use PDO;

final class Container
{
    private static ?MonitoringRepositoryInterface $monitoring = null;
    private static ?UserRepositoryInterface $users = null;

    /**
     * Repositório de monitoramento (empresas, represas, séries, alertas...).
     *
     * @param array<int,string>|null $companies empresas que o usuário pode ver;
     *        null = todas. Vale o escopo informado na primeira chamada da requisição.
     */
    public static function monitoring(?array $companies = null): MonitoringRepositoryInterface
    {
        if (self::$monitoring === null) {
            self::$monitoring = Database::configurado()
                ? new PdoMonitoringRepository(Database::conexao(), $companies)
                : new MockMonitoringRepository();

            // Os limites cadastrados passam a valer para toda a classificação.
            StatusRules::configure(self::$monitoring->limits());
        }

        return self::$monitoring;
    }

    public static function users(): UserRepositoryInterface
    {
        if (self::$users === null) {
            self::$users = Database::configurado()
                ? new PdoUserRepository(Database::conexao())
                : new MockUserRepository();
        }

        return self::$users;
    }

    /**
     * Conexão para as operações de escrita. Sem banco configurado, responde
     * 503 em JSON: o modo demonstração é somente leitura.
     */
    public static function requireDatabase(): PDO
    {
        if (!Database::configurado()) {
            ApiResponse::error(
                'DATABASE_REQUIRED',
                'Esta ação precisa do banco de dados, que não está configurado neste ambiente.',
                503
            );
        }

        return Database::conexao();
    }
}
