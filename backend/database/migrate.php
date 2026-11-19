<?php
/**
 * Aquapulse — instalador do banco de dados (ferramenta de linha de comando).
 *
 * Cria o banco, aplica as migrations em ordem e carrega os dados iniciais.
 * Não é acessível pela web: fica dentro de backend/, que o .htaccess bloqueia,
 * e só roda pelo terminal.
 *
 * USO (a partir da raiz do projeto):
 *   php backend/database/migrate.php install        cria o banco, aplica tudo e carrega os dados
 *   php backend/database/migrate.php migrate        aplica apenas as migrations pendentes
 *   php backend/database/migrate.php seed           recarrega cadastros de exemplo e o histórico simulado
 *   php backend/database/migrate.php completar      completa o histórico até agora (projeto ficou dias desligado)
 *   php backend/database/migrate.php status         mostra o que já foi aplicado e quantas linhas há
 *   php backend/database/migrate.php reset --force  APAGA o banco e recria vazio
 *
 * As credenciais vêm de backend/.env (ver backend/.env.example).
 *
 * Controle de versão do esquema: a tabela schema_migrations guarda o nome de
 * cada arquivo já aplicado, então rodar "migrate" de novo não repete nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use Aquapulse\Repositories\PdoMonitoringRepository;
use Aquapulse\Services\AlertService;
use Aquapulse\Services\ReservoirCurve;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Database;
use Aquapulse\Support\HistoryGenerator;

const PASTA_MIGRATIONS = __DIR__ . '/migrations';
const PASTA_SEEDS      = __DIR__ . '/seeds';

/* =============================================================== utilidades */

function saida(string $texto = ''): void
{
    echo $texto, PHP_EOL;
}

/** Lê uma variável de ambiente com valor padrão. */
function env(string $chave, string $padrao = ''): string
{
    $valor = getenv($chave);
    return ($valor === false || $valor === '') ? $padrao : $valor;
}

/** Nome do banco, conferido contra caracteres seguros (ele entra no texto do SQL). */
function nomeDoBanco(): string
{
    $banco = env('DB_NAME', 'aquapulse');

    if (!preg_match('/^[A-Za-z0-9_]+$/', $banco)) {
        throw new RuntimeException('Nome de banco inválido em DB_NAME: use apenas letras, números e _');
    }
    return $banco;
}

/** Conexão com o SERVIDOR, sem escolher banco (necessária para CREATE/DROP DATABASE). */
function conexaoServidor(): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'));

    return new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Divide um arquivo .sql em comandos individuais.
 *
 * O PDO executa um comando por vez; os arquivos têm vários. A divisão não pode
 * ser um simples explode(";"), porque ";" também aparece dentro de comentários
 * e de textos. Este leitor percorre o arquivo caractere a caractere sabendo em
 * que contexto está (texto entre aspas, nome entre crases, comentário de linha
 * ou de bloco) e só corta nos ";" que estão fora de tudo isso.
 *
 * @return array<int,string> comandos prontos para executar, sem os vazios
 */
function dividirSql(string $conteudo): array
{
    $comandos = [];
    $atual = '';
    $tamanho = strlen($conteudo);
    $emTexto = false;
    $emCrase = false;
    $emComentarioLinha = false;
    $emComentarioBloco = false;

    for ($i = 0; $i < $tamanho; $i++) {
        $c = $conteudo[$i];
        $proximo = $i + 1 < $tamanho ? $conteudo[$i + 1] : '';

        if ($emComentarioLinha) {                      // comentário -- vai até o fim da linha
            if ($c === "\n") {
                $emComentarioLinha = false;
                $atual .= $c;
            }
            continue;
        }

        if ($emComentarioBloco) {                      // comentário /* ... */
            if ($c === '*' && $proximo === '/') {
                $emComentarioBloco = false;
                $i++;
            }
            continue;
        }

        if ($emTexto) {                                // dentro de um texto nada é interpretado
            $atual .= $c;
            if ($c === "'") {
                if ($proximo === "'") {                // '' é uma aspa escapada, não o fim do texto
                    $atual .= $proximo;
                    $i++;
                } else {
                    $emTexto = false;
                }
            }
            continue;
        }

        if ($emCrase) {
            $atual .= $c;
            if ($c === '`') {
                $emCrase = false;
            }
            continue;
        }

        if ($c === '-' && $proximo === '-') {
            $emComentarioLinha = true;
            $i++;
            continue;
        }
        if ($c === '/' && $proximo === '*') {
            $emComentarioBloco = true;
            $i++;
            continue;
        }
        if ($c === "'") {
            $emTexto = true;
            $atual .= $c;
            continue;
        }
        if ($c === '`') {
            $emCrase = true;
            $atual .= $c;
            continue;
        }
        if ($c === ';') {
            $comandos[] = $atual;
            $atual = '';
            continue;
        }

        $atual .= $c;
    }

    $comandos[] = $atual;

    return array_values(array_filter(array_map('trim', $comandos), static fn (string $c): bool => $c !== ''));
}

/** Executa todos os comandos de um arquivo .sql e devolve quantos rodaram. */
function executarArquivoSql(PDO $pdo, string $caminho): int
{
    $comandos = dividirSql((string) file_get_contents($caminho));

    foreach ($comandos as $comando) {
        $pdo->exec($comando);
    }

    return count($comandos);
}

/** Cria a tabela de controle das migrations, se ainda não existir. */
function garantirControle(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration  VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array<int,string> nomes dos arquivos .sql de uma pasta, em ordem */
function listarSql(string $pasta): array
{
    $arquivos = glob($pasta . '/*.sql') ?: [];
    sort($arquivos);                                          // a ordem alfabética é a ordem de execução (001, 002, ...)
    return array_map('basename', $arquivos);
}

/** Valores de partida do histórico e contas de acesso (seeds/valores_iniciais.php). */
function valoresIniciais(): array
{
    return (array) require PASTA_SEEDS . '/valores_iniciais.php';
}

/* ================================================================= comandos */

function comandoCriarBanco(): void
{
    $banco = nomeDoBanco();

    conexaoServidor()->exec("CREATE DATABASE IF NOT EXISTS `{$banco}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    saida("banco `{$banco}` pronto (criado agora ou já existia)");
}

/** Aplica as migrations ainda não aplicadas. */
function comandoMigrate(): void
{
    $pdo = Database::conexao();
    garantirControle($pdo);

    $aplicadas = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $pendentes = array_diff(listarSql(PASTA_MIGRATIONS), $aplicadas);

    if ($pendentes === []) {
        saida('migrations: nenhuma pendente');
        return;
    }

    $registro = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:m)');

    foreach ($pendentes as $arquivo) {
        $qtd = executarArquivoSql($pdo, PASTA_MIGRATIONS . '/' . $arquivo);
        $registro->execute(['m' => $arquivo]);
        saida(sprintf('  aplicada %-45s (%d comandos)', $arquivo, $qtd));
    }

    saida('migrations: ' . count($pendentes) . ' aplicada(s)');
}

/**
 * Cria as contas de acesso e os vínculos com as empresas.
 *
 * O hash da senha é copiado de backend/storage/mock/users.php — a senha em
 * texto puro não existe em lugar nenhum do código, e este script não a imprime.
 */
function semearUsuarios(PDO $pdo): int
{
    $arquivo = AQ_BACKEND_PATH . '/storage/mock/users.php';

    if (!is_file($arquivo)) {
        saida('  aviso: storage/mock/users.php não encontrado; nenhuma conta carregada');
        return 0;
    }

    $hash = (string) (((array) require $arquivo)[0]['password_hash'] ?? '');
    $usuarios = valoresIniciais()['users'];

    $conta = $pdo->prepare(
        'INSERT INTO users (id, name, email, role, status, password_hash)
         VALUES (:id, :name, :email, :role, :status, :hash)
         ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), role = VALUES(role),
                                 status = VALUES(status), password_hash = VALUES(password_hash)'
    );
    $limpar = $pdo->prepare('DELETE FROM user_company_access WHERE user_id = :user');
    $vinculo = $pdo->prepare('INSERT INTO user_company_access (user_id, company_id) VALUES (:user, :company)');

    foreach ($usuarios as $u) {
        $conta->execute([
            'id'     => $u['id'],
            'name'   => $u['name'],
            'email'  => mb_strtolower(trim($u['email']), 'UTF-8'),   // mesma normalização do login
            'role'   => $u['role'],
            'status' => $u['status'],
            'hash'   => $hash,
        ]);

        // A carga define exatamente os vínculos listados para a conta.
        $limpar->execute(['user' => $u['id']]);
        foreach ($u['companies'] as $empresa) {
            $vinculo->execute(['user' => $u['id'], 'company' => $empresa]);
        }
    }

    return count($usuarios);
}

/**
 * Gera e grava o histórico simulado das leituras de cada represa, terminando no
 * momento da carga. Tudo entra em uma transação: ou entra tudo, ou nada entra.
 */
function semearHistorico(PDO $pdo): int
{
    $inicial = valoresIniciais();
    $agora = Clock::now();
    $total = 0;

    $inserir = $pdo->prepare(
        'INSERT INTO readings (sensor_id, reservoir_id, metric, value, recorded_at)
         VALUES (:sensor_id, :reservoir_id, :metric, :value, :recorded_at)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );

    $represas = $pdo->query('SELECT id, capacity_hm3, cota_min_m, cota_max_m FROM reservoirs ORDER BY sort_order')->fetchAll();

    // Equipamento ativo responsável por cada grandeza: [represa][grandeza] => código.
    // A ordem decrescente faz o primeiro equipamento cadastrado prevalecer.
    $equipamentos = [];
    foreach ($pdo->query("SELECT id, reservoir_id, type FROM sensors WHERE status = 'active' ORDER BY sort_order DESC, id DESC")->fetchAll() as $s) {
        $equipamentos[$s['reservoir_id']][$s['type']] = $s['id'];
    }

    $pdo->beginTransaction();
    foreach ($represas as $represa) {
        $partida = $inicial['reservoirs'][$represa['id']] ?? null;
        if ($partida === null) {
            continue;                                         // represa sem valores de exemplo fica sem histórico
        }

        foreach (HistoryGenerator::reservoirReadings($represa, $partida, $equipamentos[$represa['id']] ?? [], $agora) as $linha) {
            $inserir->execute($linha);
            $total++;
        }
    }
    $pdo->commit();

    return $total;
}

/**
 * Abre os alertas correspondentes ao estado atual de cada represa, usando a
 * mesma regra aplicada às leituras que chegam pela API (AlertService).
 * Grandeza que já tem alerta aberto na represa não recebe outro.
 */
function semearAlertas(PDO $pdo): int
{
    StatusRules::configure((new PdoMonitoringRepository($pdo))->limits());

    $alertas = new AlertService($pdo);
    $agora = Clock::now();
    $antes = (int) $pdo->query('SELECT COUNT(*) FROM alerts')->fetchColumn();

    $chuva = $pdo->prepare(
        "SELECT COALESCE(SUM(value), 0)
           FROM readings
          WHERE reservoir_id = :rid AND metric = 'precipitation' AND recorded_at > :ontem"
    );

    foreach ($pdo->query('SELECT reservoir_id, level_pct, ph FROM vw_resumo_represas')->fetchAll() as $r) {
        $id = (string) $r['reservoir_id'];

        if ($r['level_pct'] !== null) {
            $alertas->evaluate($id, null, 'level', (float) $r['level_pct'], $agora);
        }
        if ($r['ph'] !== null) {
            $alertas->evaluate($id, null, 'ph', (float) $r['ph'], $agora);
        }

        $chuva->execute(['rid' => $id, 'ontem' => Clock::sql($agora->modify('-24 hours'))]);
        $alertas->evaluate($id, null, 'precipitation', (float) $chuva->fetchColumn(), $agora);
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM alerts')->fetchColumn() - $antes;
}

/** Carrega os dados iniciais: cadastros (.sql), contas, histórico simulado e alertas. */
function comandoSeed(): void
{
    $pdo = Database::conexao();

    foreach (listarSql(PASTA_SEEDS) as $arquivo) {
        $qtd = executarArquivoSql($pdo, PASTA_SEEDS . '/' . $arquivo);
        saida(sprintf('  carregado %-45s (%d comandos)', $arquivo, $qtd));
    }

    saida('  contas de acesso carregadas: ' . semearUsuarios($pdo));

    saida('  gerando histórico simulado (pode levar alguns segundos)...');
    saida('  leituras geradas: ' . semearHistorico($pdo));

    saida('  alertas abertos pelo estado atual: ' . semearAlertas($pdo));
}

/**
 * Completa o histórico até a hora atual, sem apagar nem alterar nada.
 *
 * Se o projeto ficou dias desligado, as leituras param no último dia em que foi
 * usado e o painel mostra "Última leitura há 7 dias" com os gráficos vazios.
 * Este comando gera, para cada grandeza de cada represa, uma leitura por hora
 * entre a última gravada e agora, continuando do último valor (para o que for
 * mais antigo que 31 dias, uma por dia). O iniciar.bat roda este comando sempre
 * que liga o projeto; se o histórico já está em dia, não faz nada.
 */
function comandoCompletar(): void
{
    $pdo = Database::conexao();
    $agora = Clock::now();
    $limiteHorario = $agora->modify('-31 days');

    $represas = $pdo->query('SELECT id, name, capacity_hm3, cota_min_m, cota_max_m FROM reservoirs ORDER BY sort_order')->fetchAll();

    $equipamentos = [];
    foreach ($pdo->query("SELECT id, reservoir_id, type FROM sensors WHERE status = 'active' ORDER BY sort_order DESC, id DESC")->fetchAll() as $s) {
        $equipamentos[$s['reservoir_id']][$s['type']] = $s['id'];
    }

    $ultima = $pdo->prepare('SELECT value, recorded_at FROM readings WHERE reservoir_id = :r AND metric = :m ORDER BY recorded_at DESC LIMIT 1');
    $inserir = $pdo->prepare(
        'INSERT IGNORE INTO readings (sensor_id, reservoir_id, metric, value, recorded_at)
         VALUES (:sensor_id, :reservoir_id, :metric, :value, :recorded_at)'
    );
    $gravar = static function (?string $sensor, string $represa, string $metric, float $valor, DateTimeImmutable $quando) use ($inserir): void {
        $inserir->execute(['sensor_id' => $sensor, 'reservoir_id' => $represa, 'metric' => $metric, 'value' => $valor, 'recorded_at' => Clock::sql($quando)]);
    };

    $total = 0;
    $maisAntiga = null;

    $pdo->beginTransaction();
    foreach ($represas as $represa) {
        $rid = (string) $represa['id'];

        foreach (['level', 'inflow', 'outflow', 'ph', 'water_temp', 'precipitation'] as $metric) {
            $ultima->execute(['r' => $rid, 'm' => $metric]);
            $linha = $ultima->fetch();
            if ($linha === false) {
                continue;                                     // grandeza sem histórico: nada a continuar
            }

            $de = new DateTimeImmutable((string) $linha['recorded_at'], Clock::timezone());
            $instantes = instantesDaLacuna($de, $agora, $limiteHorario);
            if ($instantes === []) {
                continue;                                     // já está em dia
            }
            $maisAntiga = ($maisAntiga === null || $de < $maisAntiga) ? $de : $maisAntiga;

            $semente = HistoryGenerator::seed($rid . '|' . $metric . '|completar');
            $sensor = $equipamentos[$rid][$metric] ?? null;
            $valores = $metric === 'precipitation'
                ? chuvaDaLacuna($instantes, $limiteHorario, $semente)
                : continuarSerie($metric, (float) $linha['value'], count($instantes), $semente);

            foreach ($instantes as $i => $quando) {
                $gravar($sensor, $rid, $metric, $valores[$i], $quando);
                $total++;

                if ($metric === 'level') {                    // cota e volume acompanham o nível, como na ingestão
                    $gravar($sensor, $rid, 'cota', ReservoirCurve::cota($represa, $valores[$i]), $quando);
                    $gravar($sensor, $rid, 'storage', ReservoirCurve::storage($represa, $valores[$i]), $quando);
                    $total += 2;
                }
            }
        }
    }
    $pdo->commit();

    saida($total === 0
        ? 'histórico: já está em dia'
        : sprintf('histórico: %d leituras geradas de %s até agora', $total, $maisAntiga->format('d/m/Y H:i')));
}

/**
 * Instantes a preencher depois de $de: uma vez por hora cheia até $ate; o que
 * for mais antigo que $limiteHorario recebe só um ponto por dia (meio-dia).
 *
 * @return list<DateTimeImmutable>
 */
function instantesDaLacuna(DateTimeImmutable $de, DateTimeImmutable $ate, DateTimeImmutable $limiteHorario): array
{
    $lista = [];
    $t = $de->setTime((int) $de->format('H'), 0)->modify('+1 hour');

    while ($t <= $ate) {
        if ($t < $limiteHorario) {
            $meioDia = $t->setTime(12, 0);
            if ($meioDia > $de && $meioDia < $limiteHorario) {
                $lista[] = $meioDia;
            }
            $t = $t->setTime(0, 0)->modify('+1 day');
            continue;
        }
        $lista[] = $t;
        $t = $t->modify('+1 hour');
    }
    return $lista;
}

/**
 * Continua uma série a partir do último valor gravado: começa nele e oscila de
 * leve, com a mesma forma do histórico gerado na instalação.
 *
 * @return list<float>
 */
function continuarSerie(string $metric, float $ultimo, int $quantos, float $semente): array
{
    $cfg = HistoryGenerator::WAVES[$metric];
    $serie = array_reverse(HistoryGenerator::wave($quantos + 1, $ultimo, $cfg['amplitude'] * 0.3, 0.0, $semente, $cfg['decimals']));
    $serie = array_slice($serie, 1);                          // o primeiro é o próprio último valor

    return $metric === 'level' ? array_map(static fn (float $v): float => max(0.0, min(100.0, $v)), $serie) : $serie;
}

/**
 * Chuva fraca e espaçada para a lacuna: de 0 a 4 mm por dia (muitos dias secos),
 * abaixo do limite de atenção de 24 h, para não inventar alertas.
 *
 * @param list<DateTimeImmutable> $instantes
 * @return list<float>
 */
function chuvaDaLacuna(array $instantes, DateTimeImmutable $limiteHorario, float $semente): array
{
    $valores = [];
    foreach ($instantes as $quando) {
        $dia = (int) $quando->format('z') + 366 * (int) $quando->format('Y');
        $totalDoDia = round(max(0.0, sin($dia * 1.7 + $semente * 6.283) * 6.0 - 2.0), 1);

        $valores[] = $quando < $limiteHorario
            ? $totalDoDia                                                    // ponto diário
            : HistoryGenerator::spreadRain($totalDoDia, $semente)[(int) $quando->format('G')];   // parcela da hora
    }
    return $valores;
}

/** Mostra o estado atual do banco. */
function comandoStatus(): void
{
    $pdo = Database::conexao();
    garantirControle($pdo);

    $aplicadas = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    $todas = listarSql(PASTA_MIGRATIONS);

    saida('banco: ' . nomeDoBanco() . ' em ' . env('DB_HOST', '127.0.0.1') . ':' . env('DB_PORT', '3306'));
    saida('migrations aplicadas: ' . count($aplicadas) . ' de ' . count($todas));

    foreach ($todas as $arquivo) {
        saida('  ' . (in_array($arquivo, $aplicadas, true) ? 'OK   ' : 'FALTA') . ' ' . $arquivo);
    }

    saida('');
    saida('linhas por tabela (BASE TABLE) e views (VIEW):');
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$nome, $tipo]) {
        $qtd = (int) $pdo->query("SELECT COUNT(*) FROM `{$nome}`")->fetchColumn();   // nome vindo do próprio banco, não da requisição
        saida(sprintf('  %-32s %-11s %8d', $nome, $tipo, $qtd));
    }
}

/** Apaga o banco inteiro e recria vazio (exige --force para não acontecer por engano). */
function comandoReset(array $argumentos): void
{
    if (!in_array('--force', $argumentos, true)) {
        saida('reset APAGA todos os dados. Confirme com: php backend/database/migrate.php reset --force');
        return;
    }

    $banco = nomeDoBanco();

    conexaoServidor()->exec("DROP DATABASE IF EXISTS `{$banco}`");
    saida("banco `{$banco}` apagado");
    comandoCriarBanco();
}

/* =================================================================== início */

$comando = $argv[1] ?? 'help';
$argumentos = array_slice($argv, 2);

try {
    switch ($comando) {
        case 'install':
            comandoCriarBanco();
            comandoMigrate();
            comandoSeed();
            saida('');
            saida('instalação concluída.');
            break;

        case 'migrate':
            comandoMigrate();
            break;

        case 'seed':
            comandoSeed();
            break;

        case 'completar':
            comandoCompletar();
            break;

        case 'status':
            comandoStatus();
            break;

        case 'reset':
            comandoReset($argumentos);
            break;

        default:
            saida('Aquapulse — instalador do banco de dados');
            saida('');
            saida('  php backend/database/migrate.php install        cria o banco, aplica as migrations e carrega os dados');
            saida('  php backend/database/migrate.php migrate        aplica migrations pendentes');
            saida('  php backend/database/migrate.php seed           recarrega cadastros de exemplo e o histórico simulado');
            saida('  php backend/database/migrate.php completar      completa o histórico até agora, sem apagar nada');
            saida('  php backend/database/migrate.php status        mostra migrations aplicadas e linhas por tabela');
            saida('  php backend/database/migrate.php reset --force  apaga e recria o banco');
            saida('');
            saida('Configure antes backend/.env (ver backend/.env.example).');
    }
} catch (Throwable $e) {
    // Mensagem curta no terminal: o handler global do bootstrap responderia em JSON.
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, 'em ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
