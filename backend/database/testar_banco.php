<?php
/**
 * Aquapulse — testes do banco de dados (linha de comando).
 *
 * Confere, contra o banco configurado em backend/.env:
 *   1. estrutura   todas as tabelas e views existem;
 *   2. dados       a carga inicial entrou;
 *   3. CRUD        INSERT, SELECT, UPDATE e DELETE em sensors;
 *   4. regras      chaves estrangeiras, UNIQUE e CHECK recusam dados inválidos;
 *   5. views       as duas views devolvem uma linha por equipamento / represa;
 *   6. ingestão    uma leitura válida é gravada e gera o alerta esperado;
 *   7. comunicação equipamento parado abre o alerta "Equipamento sem leituras"
 *                  e a volta dele fica registrada;
 *   8. acesso      cargos, represas de cada conta e pedidos de acesso.
 *
 * Tudo que é escrito roda dentro de uma transação desfeita no final (rollback):
 * o banco termina exatamente como estava.
 *
 * USO:  php backend/database/testar_banco.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use Aquapulse\Repositories\AccountRepository;
use Aquapulse\Repositories\PdoMonitoringRepository;
use Aquapulse\Repositories\PdoUserRepository;
use Aquapulse\Repositories\RequestRepository;
use Aquapulse\Services\AlertService;
use Aquapulse\Services\IngestService;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Database;

$total = 0;
$falhas = 0;

/** Executa um teste: a função devolve true/texto (passou) ou false/exceção (falhou). */
function teste(string $nome, callable $teste): void
{
    global $total, $falhas;
    $total++;

    try {
        $resultado = $teste();
        $passou = $resultado !== false;
        $detalhe = is_string($resultado) ? $resultado : '';
    } catch (Throwable $e) {
        $passou = false;
        $detalhe = $e->getMessage();
    }

    if (!$passou) {
        $falhas++;
    }
    printf("%-7s %s%s\n", $passou ? 'PASSOU' : 'FALHOU', $nome, $detalhe !== '' ? "  ({$detalhe})" : '');
}

/** O banco precisa RECUSAR o comando (restrição funcionando). */
function recusado(PDO $pdo, string $sql): bool
{
    try {
        $pdo->exec($sql);
        return false;
    } catch (PDOException $e) {
        return true;
    }
}

function contar(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

echo "Aquapulse — verificação do banco de dados\n", str_repeat('-', 64), "\n";

$pdo = Database::conexao();

/* ================================================================ 1. estrutura */
$objetos = [];
foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$nome, $tipo]) {
    $objetos[$nome] = $tipo;
}

$tabelas = ['users', 'companies', 'user_reservoir_access', 'reservoirs', 'sensors', 'readings',
            'alerts', 'alert_timeline', 'reports', 'maintenances', 'operational_limits', 'requests'];

foreach ($tabelas as $t) {
    teste("tabela {$t}", static fn () => ($objetos[$t] ?? '') === 'BASE TABLE');
}
foreach (['vw_ultimas_medicoes', 'vw_resumo_represas'] as $v) {
    teste("view {$v}", static fn () => ($objetos[$v] ?? '') === 'VIEW');
}

/* ==================================================================== 2. dados */
foreach (['companies' => 2, 'reservoirs' => 3, 'sensors' => 18, 'users' => 4, 'operational_limits' => 5] as $t => $n) {
    teste("{$t}: pelo menos {$n} registros", static function () use ($pdo, $t, $n) {   // o sistema cadastra novos
        $qtd = contar($pdo, "SELECT COUNT(*) FROM `{$t}`");
        return $qtd >= $n ? "{$qtd}" : false;
    });
}
teste('histórico de leituras carregado', static function () use ($pdo) {
    $qtd = contar($pdo, 'SELECT COUNT(*) FROM readings');
    return $qtd > 10000 ? "{$qtd} leituras" : false;
});

/* ===================================================== 3 a 8. escrita (rollback) */
$pdo->beginTransaction();

teste('CRUD — INSERT de equipamento', static fn () => $pdo->exec(
    "INSERT INTO sensors (id, reservoir_id, name, location, type, status)
     VALUES ('SEN-TESTE', 'santa-clara', 'Teste', 'Laboratório', 'ph', 'active')"
) === 1);

teste('CRUD — SELECT do equipamento', static fn () =>
    $pdo->query("SELECT name FROM sensors WHERE id = 'SEN-TESTE'")->fetchColumn() === 'Teste');

teste('CRUD — UPDATE do equipamento', static function () use ($pdo) {
    $pdo->exec("UPDATE sensors SET status = 'maintenance' WHERE id = 'SEN-TESTE'");
    return $pdo->query("SELECT status FROM sensors WHERE id = 'SEN-TESTE'")->fetchColumn() === 'maintenance';
});

teste('CRUD — DELETE do equipamento', static function () use ($pdo) {
    $pdo->exec("DELETE FROM sensors WHERE id = 'SEN-TESTE'");
    return contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-TESTE'") === 0;
});

teste('FK: equipamento de represa inexistente é recusado', static fn () => recusado($pdo,
    "INSERT INTO sensors (id, reservoir_id, name, location, type) VALUES ('SEN-X', 'nao-existe', 'X', 'X', 'ph')"));

teste('CHECK: grandeza fora da lista é recusada', static fn () => recusado($pdo,
    "INSERT INTO sensors (id, reservoir_id, name, location, type) VALUES ('SEN-X', 'santa-clara', 'X', 'X', 'vento')"));

teste('UNIQUE: e-mail repetido é recusado', static fn () => recusado($pdo,
    "INSERT INTO users (name, email, password_hash) SELECT 'Cópia', email, password_hash FROM users LIMIT 1"));

teste('RESTRICT: empresa com represas não pode ser apagada', static fn () => recusado($pdo,
    "DELETE FROM companies WHERE id = 'hidrovale'"));

teste('SET NULL: excluir equipamento mantém as leituras', static function () use ($pdo) {
    $antes = contar($pdo, "SELECT COUNT(*) FROM readings WHERE sensor_id = 'SEN-PH-01'");
    $pdo->exec("DELETE FROM sensors WHERE id = 'SEN-PH-01'");
    $depois = contar($pdo, "SELECT COUNT(*) FROM readings WHERE reservoir_id = 'santa-clara' AND metric = 'ph' AND sensor_id IS NULL");
    return $antes > 0 && $depois === $antes ? "{$antes} leituras preservadas" : false;
});

teste('view vw_ultimas_medicoes: uma linha por equipamento', static fn () =>
    contar($pdo, 'SELECT COUNT(*) FROM vw_ultimas_medicoes') === contar($pdo, 'SELECT COUNT(*) FROM sensors'));

teste('view vw_resumo_represas: uma linha por represa', static fn () =>
    contar($pdo, 'SELECT COUNT(*) FROM vw_resumo_represas') === contar($pdo, 'SELECT COUNT(*) FROM reservoirs'));

teste('view vw_resumo_represas: represas de exemplo com nível', static fn () =>
    contar($pdo, "SELECT COUNT(*) FROM vw_resumo_represas
                   WHERE reservoir_id IN ('santa-clara', 'rio-verde', 'serra-azul') AND level_pct IS NOT NULL") === 3);

StatusRules::configure((new PdoMonitoringRepository($pdo))->limits());
$ingestao = new IngestService($pdo, new AlertService($pdo));

teste('ingestão: nível de 91% é gravado com cota, volume e alerta crítico', static function () use ($pdo, $ingestao) {
    $r = $ingestao->store(['equipment_id' => 'SEN-NIV-11', 'recorded_at' => Clock::now()->format(DATE_ATOM), 'value' => 91.0]);
    $derivadas = contar($pdo, "SELECT COUNT(*) FROM readings WHERE sensor_id = 'SEN-NIV-11'
                                AND metric IN ('cota', 'storage') AND recorded_at = '" . Clock::sql(Clock::parse($r['recorded_at'])) . "'");
    return $r['classification'] === 'critical' && $r['alert_id'] !== null && $derivadas === 2 ? 'alerta ' . $r['alert_id'] : false;
});

teste('ingestão: pH 15 é recusado (fora da faixa física)', static function () use ($ingestao) {
    try {
        $ingestao->store(['equipment_id' => 'SEN-PH-11', 'recorded_at' => Clock::now()->format(DATE_ATOM), 'value' => 15]);
        return false;
    } catch (ApiException $e) {
        return $e->errorCode() === 'VALUE_OUT_OF_RANGE';
    }
});

teste('ingestão: equipamento em manutenção é recusado', static function () use ($ingestao) {
    try {
        $ingestao->store(['equipment_id' => 'SEN-VAZ-03', 'recorded_at' => Clock::now()->format(DATE_ATOM), 'value' => 40]);
        return false;
    } catch (ApiException $e) {
        return $e->errorCode() === 'EQUIPMENT_NOT_ACTIVE';
    }
});

/* --- 7. comunicação: equipamento que transmitia e parou */
$alertas = new AlertService($pdo);
$pdo->exec("INSERT INTO sensors (id, reservoir_id, name, location, type, status)
            VALUES ('SEN-TESTE-COM', 'santa-clara', 'Sonda de teste', 'Laboratório', 'ph', 'active')");
$pdo->exec("INSERT INTO readings (sensor_id, reservoir_id, metric, value, recorded_at)
            VALUES ('SEN-TESTE-COM', 'santa-clara', 'ph', 7.2, '" . Clock::sql(Clock::now()->modify('-10 minutes')) . "')");

$alertaDoTeste = static function () use ($pdo): ?string {
    $id = $pdo->query("SELECT id FROM alerts WHERE sensor_id = 'SEN-TESTE-COM' AND metric = 'communication' AND status <> 'resolved'")->fetchColumn();
    return $id === false ? null : (string) $id;
};

teste('comunicação: estação que acabou de ligar não acusa sensor parado', static fn () =>
    $alertas->checkCommunication(Clock::now(), ['SEN-TESTE-COM'], Clock::now()->modify('-30 seconds')) === []);

$ligadaHa15min = Clock::now()->modify('-15 minutes');

teste('comunicação: equipamento parado há 10 min abre "Equipamento sem leituras"', static function () use ($alertas, $alertaDoTeste, $ligadaHa15min) {
    $abertos = array_column($alertas->checkCommunication(Clock::now(), ['SEN-TESTE-COM'], $ligadaHa15min), 'equipment_id');
    return $abertos === ['SEN-TESTE-COM'] && $alertaDoTeste() !== null ? 'alerta ' . $alertaDoTeste() : false;
});

teste('comunicação: não abre um segundo alerta para o mesmo equipamento', static fn () =>
    $alertas->checkCommunication(Clock::now(), ['SEN-TESTE-COM'], $ligadaHa15min) === []
    && contar($pdo, "SELECT COUNT(*) FROM alerts WHERE sensor_id = 'SEN-TESTE-COM' AND metric = 'communication'") === 1);

teste('comunicação: leitura nova registra que o equipamento voltou', static function () use ($pdo, $ingestao, $alertaDoTeste) {
    $ingestao->store(['equipment_id' => 'SEN-TESTE-COM', 'recorded_at' => Clock::now()->format(DATE_ATOM), 'value' => 7.1]);
    $etapa = $pdo->query("SELECT description FROM alert_timeline WHERE alert_id = '" . $alertaDoTeste() . "' ORDER BY step_order DESC LIMIT 1")->fetchColumn();
    return strpos((string) $etapa, 'voltou a transmitir') !== false ? (string) $etapa : false;
});

/* --- 8. acesso: cargos, represas de cada conta e pedidos */
teste('CHECK: cargo fora da lista é recusado', static fn () => recusado($pdo,
    "INSERT INTO users (name, email, role, password_hash) VALUES ('X', 'x@teste.local', 'root', 'x')"));

teste('FK: conta ligada a represa inexistente é recusada', static fn () => recusado($pdo,
    "INSERT INTO user_reservoir_access (user_id, reservoir_id) VALUES (1, 'nao-existe')"));

$represasDe = static fn (?array $alcance): array => array_column((new PdoMonitoringRepository($pdo, $alcance))->reservoirs(), 'id');

teste('alcance: quem tem uma represa vê só ela e a empresa dela', static function () use ($pdo, $represasDe) {
    $empresas = array_column((new PdoMonitoringRepository($pdo, ['serra-azul']))->companies(), 'id');
    $dona = $pdo->query("SELECT company_id FROM reservoirs WHERE id = 'serra-azul'")->fetchColumn();
    return $represasDe(['serra-azul']) === ['serra-azul'] && $empresas === [$dona] ? 'serra-azul' : false;
});

teste('alcance: conta sem represa não vê nenhuma; administrador vê todas', static fn () =>
    $represasDe([]) === [] && count($represasDe(null)) === contar($pdo, 'SELECT COUNT(*) FROM reservoirs'));

$contaDeTeste = (new AccountRepository($pdo))->insert([
    'name' => 'Conta de teste', 'email' => 'conta@teste.local', 'role' => 'manager', 'status' => 'active',
    'reservoirs' => ['rio-verde', 'santa-clara'],
], password_hash('senha-de-teste', PASSWORD_DEFAULT));

teste('conta: uma pessoa pode ter mais de uma represa', static fn () =>
    (new PdoUserRepository($pdo))->reservoirIds($contaDeTeste) === ['rio-verde', 'santa-clara']);

teste('CASCADE: apagar a conta apaga os vínculos dela', static function () use ($pdo, $contaDeTeste) {
    $pdo->exec("DELETE FROM users WHERE id = {$contaDeTeste}");
    return contar($pdo, "SELECT COUNT(*) FROM user_reservoir_access WHERE user_id = {$contaDeTeste}") === 0;
});

$pedidosAntes = contar($pdo, 'SELECT COUNT(*) FROM requests');
$pdo->exec('DELETE FROM requests');                            // para o limite de pedidos por hora não interferir (o rollback devolve todos)
$pedidos = new RequestRepository($pdo);
$pedido = ['name' => 'Pessoa de teste', 'email' => 'pedido@teste.local', 'registration' => 'T-01', 'company_name' => 'Hidrovale',
           'reservoir_name' => 'Santa Clara', 'manager_name' => 'Gestor de teste', 'message' => 'Pedido criado pelo teste do banco.',
           'password' => 'senha-do-pedido', 'password_confirm' => 'senha-do-pedido'];

teste('pedido: é gravado "em análise", com a senha só em hash', static function () use ($pdo, $pedidos, $pedido) {
    $id = $pedidos->create('access', $pedido);
    $l = $pdo->query("SELECT status, password_hash FROM requests WHERE id = {$id}")->fetch();
    return $l['status'] === 'pending' && password_verify('senha-do-pedido', $l['password_hash']);
});

teste('pedido: o mesmo e-mail não pede duas vezes', static function () use ($pedidos, $pedido) {
    try {
        $pedidos->create('access', $pedido);
        return false;
    } catch (ApiException $e) {
        return $e->errorCode() === 'REQUEST_PENDING';
    }
});

teste('pedido: o login avisa que está em análise, só para quem tem a senha', static fn () =>
    ($pedidos->loginNotice('pedido@teste.local', 'senha-do-pedido')['code'] ?? '') === 'REQUEST_PENDING'
    && $pedidos->loginNotice('pedido@teste.local', 'outra senha') === null);

teste('represa nova, sem leituras: não vira zero nem "normal"', static function () use ($pdo) {
    $pdo->exec("INSERT INTO reservoirs (id, company_id, code, name, city, basin, lat, lng, coordinates_label, capacity_hm3, useful_volume_hm3,
                                        technical_reserve_hm3, daily_consumption_hm3, cota_min_m, cota_max_m, cota_alert_m, cota_spill_m)
                VALUES ('teste', 'hidrovale', 'TST-999', 'Represa de teste', 'X', 'X', 0, 0, 'X', 100, 80, 10, 1, 0, 10, 5, 8)");
    $r = (new PdoMonitoringRepository($pdo))->reservoir('teste');
    return $r['level_pct'] === null && $r['ph'] === null && StatusRules::fromLevel($r['level_pct']) === StatusRules::NO_DATA;
});

$pdo->rollBack();

teste('rollback: o banco voltou ao estado anterior', static fn () =>
    contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-PH-01'") === 1
    && contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-TESTE-COM'") === 0
    && contar($pdo, 'SELECT COUNT(*) FROM requests') === $pedidosAntes
    && contar($pdo, "SELECT COUNT(*) FROM reservoirs WHERE id = 'teste'") === 0);

/* ===================================================================== resumo */
echo str_repeat('-', 64), "\n";
printf("%d testes | %d passaram | %d falharam\n", $total, $total - $falhas, $falhas);

exit($falhas > 0 ? 1 : 0);
