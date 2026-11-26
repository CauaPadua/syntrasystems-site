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
 *   8. acesso      cargos, represas de cada conta e pedidos de acesso;
 *   9. limites     nível pelas cotas de cada represa; pH e chuva mudados pela tela;
 *  10. segurança   limite de tentativas de login;
 *  11. falhas      erro no meio da gravação desfaz tudo; numeração sem repetição;
 *  12. ajustes     alerta resolvido não reabre sozinho; equipamento só muda a
 *                  situação; observação no relatório; pedido do dono com senha.
 *
 * Tudo que é escrito roda dentro de uma transação desfeita no final (rollback):
 * o banco termina exatamente como estava. O GitHub Actions roda este arquivo antes
 * de publicar (.github/workflows/deploy.yml).
 *
 * USO:  php backend/database/testar_banco.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use Aquapulse\Auth\LoginThrottle;
use Aquapulse\Repositories\AccountRepository;
use Aquapulse\Repositories\EquipmentRepository;
use Aquapulse\Repositories\LimitRepository;
use Aquapulse\Repositories\PdoMonitoringRepository;
use Aquapulse\Repositories\PdoUserRepository;
use Aquapulse\Repositories\RequestRepository;
use Aquapulse\Services\AlertService;
use Aquapulse\Services\IngestService;
use Aquapulse\Services\ReportService;
use Aquapulse\Services\ReservoirCurve;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Database;
use Aquapulse\Support\Sequence;

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

/** Código do ApiException lançado pela função; '' se ela não lançou nenhum. */
function codigoDoErro(callable $acao): string
{
    try {
        $acao();
        return '';
    } catch (ApiException $e) {
        return $e->errorCode();
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
            'alerts', 'alert_timeline', 'reports', 'maintenances', 'operational_limits', 'requests',
            'sequences', 'login_attempts'];

foreach ($tabelas as $t) {
    teste("tabela {$t}", static fn () => ($objetos[$t] ?? '') === 'BASE TABLE');
}
foreach (['vw_ultimas_medicoes', 'vw_resumo_represas'] as $v) {
    teste("view {$v}", static fn () => ($objetos[$v] ?? '') === 'VIEW');
}

/* ==================================================================== 2. dados */
foreach (['companies' => 2, 'reservoirs' => 3, 'sensors' => 18, 'users' => 4, 'operational_limits' => 3] as $t => $n) {
    teste("{$t}: pelo menos {$n} registros", static function () use ($pdo, $t, $n) {   // o sistema cadastra novos
        $qtd = contar($pdo, "SELECT COUNT(*) FROM `{$t}`");
        return $qtd >= $n ? "{$qtd}" : false;
    });
}
teste('histórico de leituras carregado', static function () use ($pdo) {
    $qtd = contar($pdo, 'SELECT COUNT(*) FROM readings');
    return $qtd > 10000 ? "{$qtd} leituras" : false;
});

/* ==================================================== 3 a 11. escrita (rollback) */
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
    return $r['level_pct'] === null && $r['ph'] === null && StatusRules::fromLevel($r['level_pct'], $r) === StatusRules::NO_DATA;
});

/* --- 9. limites: o nível usa as cotas de cada represa */
$pdo->exec("INSERT INTO reservoirs (id, company_id, code, name, city, basin, lat, lng, coordinates_label, capacity_hm3, useful_volume_hm3,
                                    technical_reserve_hm3, daily_consumption_hm3, cota_min_m, cota_max_m, cota_alert_m, cota_spill_m)
            VALUES ('teste-baixa', 'hidrovale', 'TST-998', 'Verte em 80%', 'X', 'X', 0, 0, 'X', 100, 80, 10, 1, 100, 200, 150, 180),
                   ('teste-alta',  'hidrovale', 'TST-997', 'Verte em 95%', 'X', 'X', 0, 0, 'X', 100, 80, 10, 1, 100, 200, 190, 195)");
$baixa = (new PdoMonitoringRepository($pdo))->reservoir('teste-baixa');
$alta = (new PdoMonitoringRepository($pdo))->reservoir('teste-alta');

teste('limites: nível 49,9 / 50 / 79,9 / 80 muda de faixa na cota de alerta e na de vertimento', static fn () =>
    array_map(static fn (float $n): string => StatusRules::fromLevel($n, $baixa), [49.9, 50.0, 79.9, 80.0])
        === ['normal', 'attention', 'attention', 'critical']);

teste('limites: o mesmo nível (85%) é crítico em uma represa e normal em outra', static fn () =>
    StatusRules::fromLevel(85.0, $baixa) === 'critical' && StatusRules::fromLevel(85.0, $alta) === 'normal');

teste('limites: em toda represa, quem chegou à cota de vertimento está em crítico', static function () use ($pdo) {
    foreach ((new PdoMonitoringRepository($pdo))->reservoirs() as $r) {
        $nivel = round(((float) $r['cota_spill_m'] - $r['cota_min_m']) / ($r['cota_max_m'] - $r['cota_min_m']) * 100, 3);
        if (StatusRules::fromLevel($nivel, $r) !== 'critical' || abs(ReservoirCurve::cota($r, StatusRules::levelCritical($r)) - $r['cota_spill_m']) > 0.11) {
            return false;
        }
    }
    return true;
});

teste('alerta: o limite gravado é o da represa', static function () use ($pdo, $baixa) {
    $r = (new AlertService($pdo))->evaluate('teste-baixa', null, 'level', 81.0, Clock::now());
    $l = $pdo->query("SELECT severity, threshold, threshold_detail FROM alerts WHERE id = '{$r['alert_id']}'")->fetch();
    return $l['severity'] === 'critical' && $l['threshold'] === '80%' && $l['threshold_detail'] === 'Cota de vertimento (180 m)' ? $l['threshold_detail'] : false;
});

$limites = new LimitRepository($pdo);
$novos = ['ph_min' => '6,0', 'ph_max' => '9', 'rain_attention' => '25', 'rain_critical' => '70'];

teste('limites: pH e chuva mudados pela tela valem na classificação', static function () use ($pdo, $limites, $novos) {
    $antes = StatusRules::limits();
    $limites->update($novos);
    StatusRules::configure((new PdoMonitoringRepository($pdo))->limits());
    $ok = StatusRules::fromPh(6.2) === 'normal' && StatusRules::fromPh(5.9) === 'attention'
        && StatusRules::classify('precipitation', 24.9) === 'normal' && StatusRules::classify('precipitation', 70.0) === 'critical';
    StatusRules::configure($antes);
    return $ok;
});

teste('limites: faixa invertida é recusada e nada muda', static fn () =>
    codigoDoErro(static fn () => $limites->update(['ph_min' => '9', 'ph_max' => '6'] + $novos)) === 'VALIDATION_ERROR'
    && codigoDoErro(static fn () => $limites->update(['rain_attention' => '80'] + $novos)) === 'VALIDATION_ERROR'
    && contar($pdo, "SELECT COUNT(*) FROM operational_limits WHERE metric = 'ph' AND min_value = 6 AND max_value = 9") === 1);

/* --- 10. segurança: tentativas de login */
$pdo->exec('DELETE FROM login_attempts');                      // o rollback devolve
$login = new LoginThrottle($pdo);
$tentar = static fn (string $email, string $ip): string => codigoDoErro(static fn () => $login->hit($email, $ip));

teste('login: a 6ª tentativa seguida no mesmo e-mail é recusada (429)', static function () use ($tentar) {
    for ($i = 1; $i <= 5; $i++) {
        if ($tentar('alvo@teste.local', '203.0.113.1') !== '') {
            return false;
        }
    }
    return $tentar('alvo@teste.local', '203.0.113.2') === 'TOO_MANY_ATTEMPTS';
});

teste('login: outro e-mail não é afetado; senha certa zera as tentativas', static function () use ($login, $tentar) {
    $livre = $tentar('outro@teste.local', '203.0.113.3') === '';
    $login->clear('alvo@teste.local');
    return $livre && $tentar('alvo@teste.local', '203.0.113.2') === '';
});

teste('login: o mesmo endereço testando vários e-mails também é recusado', static function () use ($tentar) {
    for ($i = 1; $i <= 20; $i++) {
        $tentar("pessoa{$i}@teste.local", '203.0.113.9');
    }
    return $tentar('mais-uma@teste.local', '203.0.113.9') === 'TOO_MANY_ATTEMPTS';
});

teste('login: tentativa com mais de 15 minutos deixa de contar', static function () use ($pdo, $tentar) {
    $pdo->exec("UPDATE login_attempts SET attempted_at = '" . Clock::sql(Clock::now()->modify('-16 minutes')) . "'");
    return $tentar('mais-uma@teste.local', '203.0.113.9') === '' && contar($pdo, 'SELECT COUNT(*) FROM login_attempts') === 1;
});

/* --- 11. falhas no meio da gravação e numeração */
teste('leitura repetida no mesmo instante é recusada como duplicada', static function () use ($ingestao) {
    $leitura = ['equipment_id' => 'SEN-VAZ-11', 'recorded_at' => Clock::now()->modify('-1 minute')->format(DATE_ATOM), 'value' => 41.0];
    $ingestao->store($leitura);
    return codigoDoErro(static fn () => $ingestao->store($leitura)) === 'DUPLICATE_READING';
});

teste('numeração: dois pedidos seguidos nunca recebem o mesmo número', static fn () =>
    Sequence::next($pdo, 'alert') + 1 === Sequence::next($pdo, 'alert'));

teste('número de alerta repetido não é confundido com leitura duplicada', static function () use ($pdo, $ingestao) {
    $atual = contar($pdo, "SELECT value FROM sequences WHERE name = 'alert'");
    $ultimo = contar($pdo, "SELECT MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)) FROM alerts WHERE id LIKE 'ALT-%'");
    $pdo->exec('UPDATE sequences SET value = ' . ($ultimo - 1) . " WHERE name = 'alert'");   // força o próximo a repetir
    $pdo->exec("UPDATE alerts SET status = 'resolved', normalized_at = NOW() WHERE reservoir_id = 'serra-azul' AND metric = 'ph'");
    try {
        $ingestao->store(['equipment_id' => 'SEN-PH-21', 'recorded_at' => Clock::now()->format(DATE_ATOM), 'value' => 5.0]);
        return false;
    } catch (ApiException $e) {
        return false;                                          // a estação descartaria a leitura
    } catch (PDOException $e) {
        return true;                                           // erro do servidor: a estação tenta de novo
    } finally {
        $pdo->exec("UPDATE sequences SET value = {$atual} WHERE name = 'alert'");
    }
});

/* --- 12. alerta resolvido não reabre sozinho */
$pdo->exec("DELETE FROM alerts WHERE reservoir_id = 'rio-verde' AND metric IN ('ph', 'precipitation')");
$servico = new AlertService($pdo);
$quem = ['id' => 0, 'name' => 'Teste do banco'];
$alertasDe = static fn (string $metrica): int => contar($pdo, "SELECT COUNT(*) FROM alerts WHERE reservoir_id = 'rio-verde' AND metric = '{$metrica}'");
$phFora = StatusRules::phMax() + 0.8;
$phNormal = round((StatusRules::phMin() + StatusRules::phMax()) / 2, 1);

teste('alerta: resolvido com o pH ainda fora da faixa não reabre na leitura seguinte', static function () use ($servico, $quem, $alertasDe, $phFora) {
    $id = $servico->evaluate('rio-verde', null, 'ph', $phFora, Clock::now())['alert_id'];
    $servico->resolve((string) $id, $quem);
    $depois = $servico->evaluate('rio-verde', null, 'ph', $phFora + 0.1, Clock::now());
    return $id !== null && $depois['alert_id'] === null && $alertasDe('ph') === 1;
});

teste('alerta: depois de voltar ao normal e sair de novo, abre um alerta novo', static function () use ($servico, $alertasDe, $phFora, $phNormal) {
    $servico->evaluate('rio-verde', null, 'ph', $phNormal, Clock::now());
    return $servico->evaluate('rio-verde', null, 'ph', $phFora, Clock::now())['alert_id'] !== null && $alertasDe('ph') === 2;
});

teste('alerta: resolvido em atenção, mas a chuva piorou para crítico, abre de novo', static function () use ($servico, $quem, $alertasDe) {
    $id = $servico->evaluate('rio-verde', null, 'precipitation', StatusRules::rainAttention() + 0.5, Clock::now())['alert_id'];
    $servico->resolve((string) $id, $quem);
    $igual = $servico->evaluate('rio-verde', null, 'precipitation', StatusRules::rainAttention() + 1, Clock::now())['alert_id'];
    $pior = $servico->evaluate('rio-verde', null, 'precipitation', StatusRules::rainCritical() + 1, Clock::now());
    return $igual === null && $pior['alert_id'] !== null && $pior['classification'] === 'critical' && $alertasDe('precipitation') === 2;
});

$pdo->exec("INSERT INTO sensors (id, reservoir_id, name, location, type, status)
            VALUES ('SEN-TESTE-PAR', 'rio-verde', 'Sonda parada', 'Laboratório', 'ph', 'active')");
$pdo->exec("INSERT INTO readings (sensor_id, reservoir_id, metric, value, recorded_at)
            VALUES ('SEN-TESTE-PAR', 'rio-verde', 'ph', 7.2, '" . Clock::sql(Clock::now()->modify('-10 minutes')) . "')");

teste('comunicação: equipamento ainda parado não ganha alerta novo depois de resolvido', static function () use ($servico, $quem) {
    $ligada = Clock::now()->modify('-15 minutes');
    $aberto = $servico->checkCommunication(Clock::now(), ['SEN-TESTE-PAR'], $ligada);
    $servico->resolve($aberto[0]['alert_id'] ?? '', $quem);
    return count($aberto) === 1 && $servico->checkCommunication(Clock::now(), ['SEN-TESTE-PAR'], $ligada) === [];
});

teste('comunicação: voltou a transmitir e parou de novo, ganha alerta novo', static function () use ($pdo, $servico) {
    $pdo->exec("INSERT INTO readings (sensor_id, reservoir_id, metric, value, recorded_at)
                VALUES ('SEN-TESTE-PAR', 'rio-verde', 'ph', 7.1, '" . Clock::sql(Clock::now()->modify('+1 second')) . "')");
    return count($servico->checkCommunication(Clock::now()->modify('+10 minutes'), ['SEN-TESTE-PAR'], Clock::now()->modify('-15 minutes'))) === 1;
});

/* --- 12. equipamento: depois de cadastrado, só a situação muda */
teste('equipamento: editar muda a situação e mantém nome, grandeza e represa', static function () use ($pdo) {
    $equipamentos = new EquipmentRepository($pdo);
    $todas = array_column((new PdoMonitoringRepository($pdo))->reservoirs(), 'id');
    $antes = $equipamentos->find('SEN-PH-21');
    $depois = $equipamentos->update('SEN-PH-21', ['status' => 'maintenance', 'name' => 'Outro', 'type' => 'level', 'reservoir_id' => 'santa-clara'], $todas);
    return $depois['status'] === 'maintenance'
        && [$depois['name'], $depois['type'], $depois['reservoir_id']] === [$antes['name'], $antes['type'], $antes['reservoir_id']];
});

/* --- 12. relatório: linha de observação no arquivo */
teste('relatório: a observação sai no CSV, sem virar fórmula no Excel', static function () use ($pdo) {
    $relatorios = new ReportService($pdo);
    $hoje = Clock::now()->format('Y-m-d');
    $semana = Clock::now()->modify('-6 days')->format('Y-m-d');
    $com = $relatorios->generate('santa-clara', 'quality', $semana, $hoje, ['id' => 0, 'name' => 'Teste'], '=SOMA(A1) comporta 2 parada');
    $sem = $relatorios->generate('santa-clara', 'quality', $semana, $hoje, ['id' => 0, 'name' => 'Teste']);
    $csv = static function (string $id) use ($pdo, $relatorios): string {
        foreach ((new PdoMonitoringRepository($pdo))->reports('santa-clara') as $r) {
            if ($r['id'] === $id) {
                return $relatorios->csv($r, 'Represa Santa Clara');
            }
        }
        return '';
    };
    return strpos($csv($com), "Observação;\"'=SOMA(A1) comporta 2 parada\"") !== false
        && strpos($csv($sem), 'Observação;Nenhuma') !== false;
});

/* --- 12. pedido do dono de represa: chega com senha e aprovar cria a conta */
$admin = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
$dono = ['name' => 'Dono de teste', 'email' => 'dono@teste.local', 'phone' => '(11) 91234-5678', 'company_name' => 'Fazenda',
         'reservoir_name' => 'Serra Azul', 'city' => 'Brotas', 'message' => '', 'password' => 'senha-do-dono', 'password_confirm' => 'senha-do-dono'];

teste('pedido do dono: sem senha é recusado', static fn () =>
    codigoDoErro(static fn () => $pedidos->create('demo', array_diff_key($dono, ['password' => 1, 'password_confirm' => 1]))) === 'VALIDATION_ERROR');

teste('pedido do dono: aprovar cria a conta de gestor com a senha que ele escolheu', static function () use ($pdo, $pedidos, $dono, $admin) {
    $pedidos->approve($pedidos->create('demo', $dono), ['role' => 'manager', 'reservoirs' => ['serra-azul']], $admin);
    $conta = (new PdoUserRepository($pdo))->findByEmail('dono@teste.local');
    return $conta !== null && $conta['role'] === 'manager' && password_verify('senha-do-dono', $conta['password_hash'])
        && (new PdoUserRepository($pdo))->reservoirIds($conta['id']) === ['serra-azul'];
});

teste('pedido do dono anterior à senha: não aprova, só marca como atendido', static function () use ($pdo, $pedidos, $admin) {
    $pdo->exec("INSERT INTO requests (type, name, email, company_name, reservoir_name, created_at)
                VALUES ('demo', 'Pedido antigo', 'antigo@teste.local', 'X', 'Y', NOW())");
    $id = (int) $pdo->lastInsertId();
    $erro = codigoDoErro(static fn () => $pedidos->approve($id, ['role' => 'manager', 'reservoirs' => ['serra-azul']], $admin));
    $pedidos->conclude($id, [], $admin);
    return $erro === 'NO_PASSWORD' && contar($pdo, "SELECT COUNT(*) FROM requests WHERE id = {$id} AND status = 'approved'") === 1;
});

$pdo->rollBack();

/** Conexão própria que falha, de propósito, com um erro que não é do banco, ao procurar alertas. */
$quebrada = new class(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_NAME') ?: 'aquapulse'),
    getenv('DB_USER') ?: '', getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
) extends PDO {
    #[ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if (strpos($query, 'FROM alerts') !== false) {
            throw new RuntimeException('falha simulada');
        }
        return parent::prepare($query, $options);
    }
};

teste('falha no meio da gravação: a leitura é desfeita e a transação fechada', static function () use ($pdo, $quebrada) {
    $quando = Clock::now()->modify('-3 minutes')->modify('+' . random_int(0, 50) . ' seconds');
    $antes = contar($pdo, 'SELECT COUNT(*) FROM readings');
    try {
        (new IngestService($quebrada, new AlertService($quebrada)))
            ->store(['equipment_id' => 'SEN-NIV-11', 'recorded_at' => $quando->format(DATE_ATOM), 'value' => 99.0]);
        return false;
    } catch (RuntimeException $e) {
        return $e->getMessage() === 'falha simulada' && !$quebrada->inTransaction() && contar($pdo, 'SELECT COUNT(*) FROM readings') === $antes;
    }
});

teste('rollback: o banco voltou ao estado anterior', static fn () =>
    contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-PH-01'") === 1
    && contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-TESTE-COM'") === 0
    && contar($pdo, 'SELECT COUNT(*) FROM requests') === $pedidosAntes
    && contar($pdo, "SELECT COUNT(*) FROM reservoirs WHERE id LIKE 'teste%'") === 0
    && contar($pdo, "SELECT COUNT(*) FROM login_attempts WHERE ip LIKE '203.0.113.%'") === 0
    && contar($pdo, "SELECT COUNT(*) FROM users WHERE email = 'dono@teste.local'") === 0
    && contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-TESTE-PAR'") === 0);

/* ===================================================================== resumo */
echo str_repeat('-', 64), "\n";
printf("%d testes | %d passaram | %d falharam\n", $total, $total - $falhas, $falhas);

exit($falhas > 0 ? 1 : 0);
