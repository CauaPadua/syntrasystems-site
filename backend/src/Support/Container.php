<?php
/*
 * Aquapulse — Container de dependências (ponto único de troca da fonte de dados).
 *
 * Todo código que precisa ler usuários ou dados de monitoramento pede o
 * repositório AQUI, em vez de instanciar uma classe concreta. Assim a decisão
 * "banco de dados real ou dados simulados" fica em um só lugar:
 *   - existe a variável de ambiente DB_HOST (definida no .env) -> repositórios PDO (MySQL);
 *   - não existe                                               -> repositórios Mock (arquivos PHP em storage/mock).
 *
 * Os serviços e endpoints dependem só das INTERFACES (MonitoringRepositoryInterface
 * e UserRepositoryInterface), então não percebem qual implementação está em uso.
 */
declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Repositories\Mock\MockMonitoringRepository;
use Aquapulse\Repositories\MockUserRepository;
use Aquapulse\Repositories\PdoMonitoringRepository;
use Aquapulse\Repositories\PdoUserRepository;
use Aquapulse\Repositories\UserRepositoryInterface;

final class Container                                                    // final: não pode ser estendida; só oferece métodos estáticos
{
    private static ?MonitoringRepositoryInterface $monitoring = null;    // instância única do repositório de monitoramento (criada na 1ª chamada)
    private static ?UserRepositoryInterface $users = null;               // instância única do repositório de usuários

    /**
     * Devolve o repositório de dados de monitoramento (empresas, represas, séries, alertas...).
     * Usado por api/v1/_boot.php (todos os endpoints de dados) e por páginas do dashboard.
     */
    public static function monitoring(): MonitoringRepositoryInterface
    {
        if (self::$monitoring === null) {                                // padrão "singleton preguiçoso": só cria quando alguém pede, e uma vez por requisição
            if (getenv('DB_HOST')) {                                     // DB_HOST definido (via servidor ou backend/.env) = banco configurado
                self::$monitoring = new PdoMonitoringRepository(Database::conexao()); // lê as tabelas MySQL pela conexão PDO compartilhada
            } else {
                self::$monitoring = new MockMonitoringRepository();      // sem banco: lê backend/storage/mock/monitoring.php
            }
        }
        return self::$monitoring;
    }

    /**
     * Devolve o repositório de usuários (busca por e-mail e por ID).
     * Usado por Support\Guard para identificar o usuário da sessão.
     */
    public static function users(): UserRepositoryInterface
    {
        if (self::$users === null) {
            if (getenv('DB_HOST')) {                                     // mesma regra do método acima, para as duas fontes ficarem sempre coerentes
                self::$users = new PdoUserRepository(Database::conexao()); // tabela users do MySQL
            } else {
                self::$users = new MockUserRepository();                 // backend/storage/mock/users.php
            }
        }
        return self::$users;
    }
}
