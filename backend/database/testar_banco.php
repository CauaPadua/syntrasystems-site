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
 *   6. ingestão    uma leitura válida é gravada e gera o alerta esperado.
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

use Aquapulse\Repositories\PdoMonitoringRepository;
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

$tabelas = ['users', 'companies', 'user_company_access', 'reservoirs', 'sensors', 'readings',
            'alerts', 'alert_timeline', 'reports', 'maintenances', 'operational_limits'];

foreach ($tabelas as $t) {
    teste("tabela {$t}", static fn () => ($objetos[$t] ?? '') === 'BASE TABLE');
}
foreach (['vw_ultimas_medicoes', 'vw_resumo_represas'] as $v) {
    teste("view {$v}", static fn () => ($objetos[$v] ?? '') === 'VIEW');
}

/* ==================================================================== 2. dados */
foreach (['companies' => 2, 'reservoirs' => 3, 'sensors' => 19, 'users' => 3, 'operational_limits' => 5] as $t => $n) {
    teste("{$t} com {$n} registros", static function () use ($pdo, $t, $n) {
        $qtd = contar($pdo, "SELECT COUNT(*) FROM `{$t}`");
        return $qtd === $n ? true : "encontrado {$qtd}";
    });
}
teste('histórico de leituras carregado', static function () use ($pdo) {
    $qtd = contar($pdo, 'SELECT COUNT(*) FROM readings');
    return $qtd > 10000 ? "{$qtd} leituras" : false;
});

/* ===================================================== 3 a 6. escrita (rollback) */
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

teste('view vw_resumo_represas: uma linha por represa, com nível', static fn () =>
    contar($pdo, 'SELECT COUNT(*) FROM vw_resumo_represas WHERE level_pct IS NOT NULL') === 3);

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

$pdo->rollBack();

teste('rollback: o banco voltou ao estado anterior', static fn () =>
    contar($pdo, "SELECT COUNT(*) FROM sensors WHERE id = 'SEN-PH-01'") === 1);

/* ===================================================================== resumo */
echo str_repeat('-', 64), "\n";
printf("%d testes | %d passaram | %d falharam\n", $total, $total - $falhas, $falhas);

exit($falhas > 0 ? 1 : 0);
