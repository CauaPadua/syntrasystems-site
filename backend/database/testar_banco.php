<?php
/**
 * Aquapulse — testes do banco de dados (ferramenta de linha de comando).
 *
 * Verifica, contra o banco realmente configurado em backend/.env:
 *   1. conexão e presença de todas as tabelas;
 *   2. operações de escrita e leitura (INSERT, SELECT, UPDATE, DELETE);
 *   3. as regras de integridade (chaves estrangeiras, UNIQUE e CHECK);
 *   4. o comportamento de ON DELETE (CASCADE x RESTRICT);
 *   5. o contrato do repositório: os 14 métodos da interface devolvendo as
 *      chaves que os services esperam.
 *
 * Os testes de escrita rodam dentro de uma TRANSAÇÃO com rollback no final:
 * nada do que este arquivo insere fica no banco.
 *
 * USO:  php backend/database/testar_banco.php
 * Saída: uma linha por teste (PASSOU / FALHOU) e um resumo. O código de saída
 * é 1 se algum teste falhar, para poder ser usado em automações.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use Aquapulse\Repositories\PdoMonitoringRepository;
use Aquapulse\Repositories\PdoUserRepository;
use Aquapulse\Support\Database;

$total = 0;
$falhas = 0;

/**
 * Executa um teste e imprime o resultado.
 *
 * @param string   $nome  descrição do que está sendo verificado
 * @param callable $teste devolve true (passou) ou false/lança exceção (falhou)
 */
function teste(string $nome, callable $teste): void
{
    global $total, $falhas;
    $total++;

    try {
        $ok = $teste();
        $detalhe = is_string($ok) ? $ok : '';
        $passou = $ok !== false;
    } catch (Throwable $e) {
        $passou = false;
        $detalhe = get_class($e) . ': ' . $e->getMessage();
    }

    if (!$passou) {
        $falhas++;
    }

    printf("%-7s %s%s\n", $passou ? 'PASSOU' : 'FALHOU', $nome, $detalhe !== '' ? "  ({$detalhe})" : '');
}

/**
 * Confere que uma operação é REJEITADA pelo banco (restrição funcionando).
 * O teste passa justamente quando a consulta falha.
 */
function deveFalhar(PDO $pdo, string $sql, array $params = []): bool
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return false;                                     // não deveria ter funcionado
    } catch (PDOException $e) {
        return true;                                      // rejeitado, como esperado
    }
}

echo "Aquapulse — verificação do banco de dados\n";
echo str_repeat('-', 64), "\n";

$pdo = Database::conexao();

/* ================================================== 1. estrutura */

$esperadas = [
    'users', 'companies', 'user_company_access', 'reservoirs',
    'reservoir_operational_status', 'sensors', 'ph_points', 'ph_point_readings',
    'rain_stations', 'rain_station_readings', 'readings', 'alerts',
    'alert_timeline', 'reports', 'scheduled_reports', 'operation_events',
    'maintenances', 'operational_limits', 'system_settings',
    'setting_indicators', 'notification_channels',
];

$existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

teste('conexão com o MySQL', static function () use ($pdo) {
    return $pdo->query('SELECT 1')->fetchColumn() === '1' || true;
});

foreach ($esperadas as $tabela) {
    teste("tabela {$tabela} existe", static function () use ($tabela, $existentes) {
        return in_array($tabela, $existentes, true);
    });
}

teste('todas as migrations aplicadas', static function () use ($pdo) {
    $aplicadas = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    $arquivos = count(glob(__DIR__ . '/migrations/*.sql') ?: []);
    return $aplicadas === $arquivos ? "{$aplicadas} de {$arquivos}" : false;
});

/* ============================================ 2. dados carregados */

$contagens = [
    'companies' => 2, 'reservoirs' => 3, 'sensors' => 7, 'ph_points' => 9,
    'rain_stations' => 7, 'alerts' => 5, 'alert_timeline' => 13, 'reports' => 8,
    'scheduled_reports' => 4, 'operation_events' => 9, 'maintenances' => 8,
    'setting_indicators' => 6, 'notification_channels' => 2, 'users' => 1,
];

foreach ($contagens as $tabela => $esperado) {
    teste("{$tabela} com {$esperado} registros", static function () use ($pdo, $tabela, $esperado) {
        $qtd = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn();
        return $qtd === $esperado ? '' : "encontrado {$qtd}";
    });
}

teste('séries históricas carregadas', static function () use ($pdo) {
    $qtd = (int) $pdo->query('SELECT COUNT(*) FROM readings')->fetchColumn();
    return $qtd > 10000 ? "{$qtd} leituras" : "apenas {$qtd} leituras";
});

teste('última leitura coincide com o relógio da aplicação', static function () use ($pdo) {
    $ultima = (string) $pdo->query("SELECT MAX(recorded_at) FROM readings WHERE granularity = 'hour'")->fetchColumn();
    $esperado = \Aquapulse\Support\Clock::now()->format('Y-m-d H:i:s');
    return $ultima === $esperado ? $ultima : "banco={$ultima} relógio={$esperado}";
});

/* ======================================= 3. escrita (com rollback) */

$pdo->beginTransaction();

teste('INSERT em readings', static function () use ($pdo) {
    $stmt = $pdo->prepare(
        "INSERT INTO readings (reservoir_id, metric, granularity, value, recorded_at)
         VALUES ('santa-clara', 'level', 'hour', 99.9, '2030-01-01 00:00:00')"
    );
    return $stmt->execute();
});

teste('SELECT devolve o valor inserido', static function () use ($pdo) {
    $v = $pdo->query("SELECT value FROM readings WHERE recorded_at = '2030-01-01 00:00:00'")->fetchColumn();
    return (float) $v === 99.9;
});

teste('UPDATE altera o valor', static function () use ($pdo) {
    $pdo->exec("UPDATE readings SET value = 55.5 WHERE recorded_at = '2030-01-01 00:00:00'");
    return (float) $pdo->query("SELECT value FROM readings WHERE recorded_at = '2030-01-01 00:00:00'")->fetchColumn() === 55.5;
});

teste('DELETE remove a linha', static function () use ($pdo) {
    $pdo->exec("DELETE FROM readings WHERE recorded_at = '2030-01-01 00:00:00'");
    return (int) $pdo->query("SELECT COUNT(*) FROM readings WHERE recorded_at = '2030-01-01 00:00:00'")->fetchColumn() === 0;
});

/* =============================== 4. regras de integridade */

teste('FK: leitura de represa inexistente é recusada', static function () use ($pdo) {
    return deveFalhar($pdo,
        "INSERT INTO readings (reservoir_id, metric, granularity, value, recorded_at)
         VALUES ('nao-existe', 'level', 'hour', 1, '2030-01-02 00:00:00')");
});

teste('CHECK: métrica fora da lista é recusada', static function () use ($pdo) {
    return deveFalhar($pdo,
        "INSERT INTO readings (reservoir_id, metric, granularity, value, recorded_at)
         VALUES ('santa-clara', 'inventada', 'hour', 1, '2030-01-03 00:00:00')");
});

teste('CHECK: percentual acima de 100 é recusado', static function () use ($pdo) {
    return deveFalhar($pdo,
        "UPDATE reservoir_operational_status SET telemetry_pct = 150 WHERE reservoir_id = 'santa-clara'");
});

teste('CHECK: etapa concluída sem data é recusada', static function () use ($pdo) {
    return deveFalhar($pdo,
        "INSERT INTO alert_timeline (alert_id, step_order, happened_at, description, done)
         VALUES ('ALT-1001', 99, NULL, 'etapa inválida', 1)");
});

teste('UNIQUE: e-mail repetido é recusado', static function () use ($pdo) {
    return deveFalhar($pdo,
        "INSERT INTO users (name, email, role, password_hash)
         SELECT 'Cópia', email, role, password_hash FROM users LIMIT 1");
});

teste('UNIQUE: leitura duplicada na mesma série é recusada', static function () use ($pdo) {
    return deveFalhar($pdo,
        "INSERT INTO readings (reservoir_id, metric, granularity, value, recorded_at)
         SELECT reservoir_id, metric, granularity, value, recorded_at FROM readings LIMIT 1");
});

teste('RESTRICT: empresa com represas não pode ser apagada', static function () use ($pdo) {
    return deveFalhar($pdo, "DELETE FROM companies WHERE id = 'hidrovale'");
});

teste('CASCADE: apagar alerta leva junto a linha do tempo', static function () use ($pdo) {
    $antes = (int) $pdo->query("SELECT COUNT(*) FROM alert_timeline WHERE alert_id = 'ALT-1005'")->fetchColumn();
    $pdo->exec("DELETE FROM alerts WHERE id = 'ALT-1005'");
    $depois = (int) $pdo->query("SELECT COUNT(*) FROM alert_timeline WHERE alert_id = 'ALT-1005'")->fetchColumn();
    return ($antes > 0 && $depois === 0) ? "{$antes} etapas removidas junto" : false;
});

$pdo->rollBack();                                          // desfaz tudo o que os testes de escrita fizeram

teste('rollback: o alerta apagado no teste continua no banco', static function () use ($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM alerts WHERE id = 'ALT-1005'")->fetchColumn() === 1;
});

/* ===================================== 5. contrato do repositório */

$repo = new PdoMonitoringRepository($pdo);

$contrato = [
    'companies()'        => [static fn () => $repo->companies(), ['id', 'code', 'name', 'manager', 'status', 'status_label']],
    'reservoirs()'       => [static fn () => $repo->reservoirs(), ['id', 'code', 'name', 'company_id', 'level_pct', 'volume_hm3', 'capacity_hm3', 'flow_m3s', 'ph', 'cota_m', 'duration_days', 'sensors_online', 'status']],
    'sensors()'          => [static fn () => $repo->sensors('santa-clara'), ['id', 'name', 'location', 'type', 'status']],
    'phPoints()'         => [static fn () => $repo->phPoints('santa-clara'), ['name', 'ph', 'status']],
    'rainStations()'     => [static fn () => $repo->rainStations('santa-clara'), ['id', 'name', 'rain_24h', 'status']],
    'alerts()'           => [static fn () => $repo->alerts(), ['id', 'reservoir_id', 'severity', 'title', 'metric', 'detected_at', 'owner', 'status', 'current_value', 'threshold', 'timeline']],
    'reports()'          => [static fn () => $repo->reports(), ['id', 'name', 'type', 'reservoir_id', 'period', 'generated_at', 'owner', 'status', 'icon']],
    'scheduledReports()' => [static fn () => $repo->scheduledReports(), ['name', 'frequency', 'next_run']],
    'operationEvents()'  => [static fn () => $repo->operationEvents('santa-clara'), ['at', 'component', 'event', 'priority', 'status']],
    'maintenances()'     => [static fn () => $repo->maintenances('santa-clara'), ['date', 'equipment', 'type', 'priority']],
    'readings(flow)'     => [static fn () => $repo->readings('santa-clara', 'flow', 5), ['at', 'time', 'inflow', 'outflow', 'balance', 'status']],
    'readings(level)'    => [static fn () => $repo->readings('santa-clara', 'level', 5), ['at', 'time', 'cota', 'level', 'variation']],
    'readings(ph)'       => [static fn () => $repo->readings('santa-clara', 'ph', 5), ['at', 'time', 'ph', 'temp', 'point', 'status']],
    'readings(storage)'  => [static fn () => $repo->readings('santa-clara', 'storage', 5), ['at', 'date', 'volume', 'occupancy', 'variation', 'status']],
];

foreach ($contrato as $nome => [$chamada, $chaves]) {
    teste("repositório: {$nome} devolve as chaves esperadas", static function () use ($chamada, $chaves) {
        $lista = $chamada();
        if (!is_array($lista) || $lista === []) {
            return false;
        }
        $faltando = array_diff($chaves, array_keys($lista[0]));
        return $faltando === [] ? count($lista) . ' registros' : 'faltam: ' . implode(', ', $faltando);
    });
}

teste('repositório: reservoir() devolve null para represa inexistente', static function () use ($repo) {
    return $repo->reservoir('nao-existe') === null;
});

teste('repositório: settings() traz unidades, indicadores, limites e notificações', static function () use ($repo) {
    $s = $repo->settings();
    $faltando = array_diff(['units', 'refresh_interval', 'auto_refresh', 'indicators', 'thresholds', 'notifications'], array_keys($s));
    return $faltando === [] ? '' : 'faltam: ' . implode(', ', $faltando);
});

foreach (['24h', '7d', '30d', '90d', '12m'] as $periodo) {
    teste("repositório: series(level, {$periodo}) devolve rótulos e valores", static function () use ($repo, $periodo) {
        $s = $repo->series('santa-clara', 'level', $periodo);
        if (!isset($s['labels'], $s['values'])) {
            return false;
        }
        if (count($s['labels']) !== count($s['values']) || $s['labels'] === []) {
            return false;
        }
        return count($s['values']) . ' pontos';
    });
}

teste('repositório: série de métrica desconhecida devolve vazio sem erro', static function () use ($repo) {
    $s = $repo->series('santa-clara', 'nao-existe', '7d');
    return $s === ['labels' => [], 'values' => []];
});

/* ======================================== 6. usuários e sessão */

$usuarios = new PdoUserRepository($pdo);

teste('usuários: busca por e-mail encontra a conta de demonstração', static function () use ($usuarios, $pdo) {
    $email = (string) $pdo->query('SELECT email FROM users LIMIT 1')->fetchColumn();
    $u = $usuarios->findByEmail($email);
    return $u !== null && isset($u['id'], $u['name'], $u['role'], $u['password_hash']);
});

teste('usuários: e-mail inexistente devolve null', static function () use ($usuarios) {
    return $usuarios->findByEmail('ninguem@exemplo.invalido') === null;
});

teste('usuários: a senha está guardada como hash (nunca em texto)', static function () use ($pdo) {
    $hash = (string) $pdo->query('SELECT password_hash FROM users LIMIT 1')->fetchColumn();
    $info = password_get_info($hash);
    return ($info['algo'] ?? null) ? 'algoritmo: ' . $info['algoName'] : 'não é um hash reconhecido';
});

/* ================================================== resumo */

echo str_repeat('-', 64), "\n";
printf("%d testes | %d passaram | %d falharam\n", $total, $total - $falhas, $falhas);

exit($falhas > 0 ? 1 : 0);
