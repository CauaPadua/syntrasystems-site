<?php
/**
 * Aquapulse — instalador do banco de dados (ferramenta de linha de comando).
 *
 * Cria o banco, aplica as migrations em ordem e carrega os dados iniciais.
 * NÃO é acessível pela web: fica dentro de backend/, que o .htaccess bloqueia,
 * e só roda pelo terminal.
 *
 * USO (a partir da raiz do projeto):
 *   php backend/database/migrate.php install     cria o banco, aplica tudo e popula
 *   php backend/database/migrate.php migrate     aplica apenas as migrations pendentes
 *   php backend/database/migrate.php seed        recarrega os dados iniciais
 *   php backend/database/migrate.php status      mostra o que já foi aplicado e quantas linhas há
 *   php backend/database/migrate.php reset --force   APAGA o banco e recria do zero
 *
 * As credenciais vêm de backend/.env (DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS) — nunca de valores escritos aqui. Ver backend/.env.example.
 *
 * Controle de versão do esquema: a tabela `schema_migrations` guarda o nome de
 * cada arquivo já aplicado, então rodar "migrate" de novo não repete nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {                                     // proteção extra: se algum dia o arquivo ficar exposto na web, não executa
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';                  // carrega backend/.env, o autoload e os handlers de erro
require __DIR__ . '/series_generator.php';                    // gerador determinístico das séries históricas

use Aquapulse\Support\Clock;

const PASTA_MIGRATIONS = __DIR__ . '/migrations';
const PASTA_SEEDS      = __DIR__ . '/seeds';

/* =============================================================== utilidades */

/** Escreve uma linha no terminal. */
function saida(string $texto = ''): void
{
    echo $texto, PHP_EOL;
}

/** Lê uma variável de ambiente com valor padrão (mesma regra do Support\Database). */
function env(string $chave, string $padrao = ''): string
{
    $valor = getenv($chave);
    return ($valor === false || $valor === '') ? $padrao : $valor;
}

/**
 * Conexão com o SERVIDOR, sem escolher banco.
 * Necessária para executar CREATE DATABASE / DROP DATABASE.
 */
function conexaoServidor(): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'));

    return new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Conexão com o banco do projeto (já com USE do schema).
 * Usa a mesma classe da aplicação, para testar o caminho real de conexão.
 */
function conexaoBanco(): PDO
{
    return \Aquapulse\Support\Database::conexao();
}

/**
 * Divide um arquivo .sql em comandos individuais.
 *
 * O PDO executa um comando por vez; os arquivos de migration têm vários. A
 * divisão não pode ser um simples explode(";"), porque ";" também aparece
 * dentro de comentários e de textos. Este leitor percorre o arquivo caractere
 * a caractere sabendo em que contexto está (texto entre aspas, identificador
 * entre crases, comentário de linha -- ou de bloco), e só corta nos ";" que
 * estão fora de tudo isso.
 *
 * @return array<int,string> comandos prontos para executar, sem os vazios
 */
function dividirSql(string $conteudo): array
{
    $comandos = [];
    $atual = "";
    $tamanho = strlen($conteudo);
    $emTexto = false;         // dentro de '...'
    $emCrase = false;         // dentro de `...`
    $emComentarioLinha = false;
    $emComentarioBloco = false;

    for ($i = 0; $i < $tamanho; $i++) {
        $c = $conteudo[$i];
        $proximo = $i + 1 < $tamanho ? $conteudo[$i + 1] : "";

        if ($emComentarioLinha) {                      // comentário -- vai até o fim da linha
            if ($c === PHP_EOL || $c === "
") {
                $emComentarioLinha = false;
                $atual .= $c;                         // preserva a quebra de linha
            }
            continue;
        }

        if ($emComentarioBloco) {                      // comentário /* ... */
            if ($c === "*" && $proximo === "/") {
                $emComentarioBloco = false;
                $i++;
            }
            continue;
        }

        if ($emTexto) {                                // dentro de um texto: nada é interpretado
            $atual .= $c;
            if ($c === "'") {
                if ($proximo === "'") {            // '' é uma aspa escapada, não o fim do texto
                    $atual .= $proximo;
                    $i++;
                } else {
                    $emTexto = false;
                }
            }
            continue;
        }

        if ($emCrase) {                                // nome de tabela/coluna entre crases
            $atual .= $c;
            if ($c === "`") {
                $emCrase = false;
            }
            continue;
        }

        // --- fora de textos e comentários: aqui os delimitadores valem
        if ($c === "-" && $proximo === "-") {
            $emComentarioLinha = true;
            $i++;
            continue;
        }
        if ($c === "/" && $proximo === "*") {
            $emComentarioBloco = true;
            $i++;
            continue;
        }
        if ($c === "'") {
            $emTexto = true;
            $atual .= $c;
            continue;
        }
        if ($c === "`") {
            $emCrase = true;
            $atual .= $c;
            continue;
        }
        if ($c === ";") {                              // fim de um comando
            $comandos[] = $atual;
            $atual = "";
            continue;
        }

        $atual .= $c;
    }

    $comandos[] = $atual;                             // resto depois do último ";"

    $resultado = [];
    foreach ($comandos as $comando) {
        $comando = trim($comando);
        if ($comando !== "") {
            $resultado[] = $comando;
        }
    }

    return $resultado;
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

/* ================================================================= comandos */

/** Cria o banco com o charset correto, se ainda não existir. */
function comandoCriarBanco(): void
{
    $banco = env('DB_NAME', 'aquapulse');
    $pdo = conexaoServidor();

    // O nome do banco não pode ir como parâmetro (:nome) em DDL; por isso é
    // validado contra uma lista de caracteres seguros antes de entrar no SQL.
    if (!preg_match('/^[A-Za-z0-9_]+$/', $banco)) {
        throw new RuntimeException('Nome de banco inválido em DB_NAME: use apenas letras, números e _');
    }

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$banco}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    saida("banco `{$banco}` pronto (criado agora ou já existia)");
}

/** Aplica as migrations ainda não aplicadas. */
function comandoMigrate(): void
{
    $pdo = conexaoBanco();
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
 * Carrega o usuário de demonstração a partir do arquivo simulado.
 *
 * O hash da senha é lido de backend/storage/mock/users.php e copiado como está
 * — a senha em texto puro não existe em lugar nenhum do código, e este script
 * não a imprime nem a grava em outro arquivo.
 */
function semearUsuarios(PDO $pdo): int
{
    $arquivo = AQ_BACKEND_PATH . '/storage/mock/users.php';

    if (!is_file($arquivo)) {
        saida('  aviso: storage/mock/users.php não encontrado; nenhum usuário carregado');
        return 0;
    }

    $usuarios = (array) require $arquivo;

    $sql = $pdo->prepare(
        'INSERT INTO users (id, name, email, role, password_hash)
         VALUES (:id, :name, :email, :role, :hash)
         ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), password_hash = VALUES(password_hash)'
    );

    foreach ($usuarios as $u) {
        $sql->execute([
            'id'    => (int) $u['id'],
            'name'  => (string) $u['name'],
            'email' => mb_strtolower(trim((string) $u['email']), 'UTF-8'),   // mesma normalização do login
            'role'  => (string) $u['role'],
            'hash'  => (string) $u['password_hash'],
        ]);
    }

    // Vínculo do usuário com as empresas existentes (a tela ainda não filtra por
    // empresa, mas o cadastro já fica coerente para quando essa regra entrar).
    // INSERT IGNORE (e não ON DUPLICATE KEY UPDATE): o MariaDB 10.4 não aceita
    // ON DUPLICATE KEY em um INSERT ... SELECT. Com a chave única
    // (user_id, company_id), o IGNORE pula os vínculos que já existem.
    $pdo->exec(
        "INSERT IGNORE INTO user_company_access (user_id, company_id, role, permissions)
         SELECT u.id, c.id, u.role, '[\"monitoring.read\", \"reports.read\", \"alerts.read\"]'
           FROM users u CROSS JOIN companies c"
    );

    return count($usuarios);
}

/**
 * Gera e grava as séries históricas (leituras) a partir dos âncoras simulados.
 *
 * As inserções vão em lotes de 200 linhas dentro de uma transação: uma
 * instrução por linha deixaria a carga muito lenta, e a transação garante que
 * ou tudo entra, ou nada entra.
 */
function semearSeries(PDO $pdo): array
{
    $arquivo = AQ_BACKEND_PATH . '/storage/mock/monitoring.php';

    if (!is_file($arquivo)) {
        saida('  aviso: storage/mock/monitoring.php não encontrado; séries não geradas');
        return ['readings' => 0, 'ph' => 0, 'chuva' => 0];
    }

    $mock = (array) require $arquivo;
    $agora = Clock::now();                                     // 22/05/2024 09:30 no modo demonstrativo
    $total = ['readings' => 0, 'ph' => 0, 'chuva' => 0];

    /* ---------------------------------------------- leituras por represa */
    $inserir = $pdo->prepare(
        'INSERT INTO readings (reservoir_id, metric, granularity, value, recorded_at)
         VALUES (:rid, :metric, :gran, :valor, :quando)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );

    $pdo->beginTransaction();
    foreach ($mock['reservoirs'] ?? [] as $represa) {
        foreach (SeriesGenerator::leiturasDaRepresa($represa, $agora) as $linha) {
            $inserir->execute([
                'rid'    => $linha[0],
                'metric' => $linha[1],
                'gran'   => $linha[2],
                'valor'  => $linha[3],
                'quando' => $linha[4],
            ]);
            $total['readings']++;
        }
    }
    $pdo->commit();

    /* ------------------------------------------ medições dos pontos de pH */
    $pontos = $pdo->query('SELECT id, reservoir_id, name FROM ph_points ORDER BY id')->fetchAll();
    $inserirPh = $pdo->prepare(
        'INSERT INTO ph_point_readings (ph_point_id, ph, water_temp_c, recorded_at)
         VALUES (:pid, :ph, :temp, :quando)
         ON DUPLICATE KEY UPDATE ph = VALUES(ph), water_temp_c = VALUES(water_temp_c)'
    );

    $pdo->beginTransaction();
    foreach ($pontos as $ponto) {
        // âncora de cada ponto: o valor que o arquivo simulado trazia para ele
        $ancora = 7.2;
        foreach ($mock['ph_points'][$ponto['reservoir_id']] ?? [] as $p) {
            if ($p['name'] === $ponto['name']) {
                $ancora = (float) $p['ph'];
                break;
            }
        }

        $temp = 22.0;
        foreach ($mock['reservoirs'] ?? [] as $r) {
            if ($r['id'] === $ponto['reservoir_id']) {
                $temp = (float) $r['water_temp_c'];
                break;
            }
        }

        $chave = $ponto['reservoir_id'] . '|ph|' . $ponto['name'];
        foreach (SeriesGenerator::leiturasDoPontoPh((int) $ponto['id'], $chave, $ancora, $temp, $agora) as $linha) {
            $inserirPh->execute([
                'pid'    => $linha[0],
                'ph'     => $linha[1],
                'temp'   => $linha[2],
                'quando' => $linha[3],
            ]);
            $total['ph']++;
        }
    }
    $pdo->commit();

    /* ------------------------------- medições das estações pluviométricas */
    $estacoes = $pdo->query('SELECT id, reservoir_id, name FROM rain_stations ORDER BY id')->fetchAll();
    $inserirChuva = $pdo->prepare(
        'INSERT INTO rain_station_readings (station_id, rain_mm, recorded_at)
         VALUES (:sid, :mm, :quando)
         ON DUPLICATE KEY UPDATE rain_mm = VALUES(rain_mm)'
    );

    $pdo->beginTransaction();
    foreach ($estacoes as $estacao) {
        $ancora = 10.0;
        foreach ($mock['rain_stations'][$estacao['reservoir_id']] ?? [] as $e) {
            if ($e['id'] === $estacao['id']) {
                $ancora = (float) $e['rain_24h'];
                break;
            }
        }

        foreach (SeriesGenerator::leiturasDaEstacao((string) $estacao['id'], $ancora, $agora) as $linha) {
            $inserirChuva->execute([
                'sid'    => $linha[0],
                'mm'     => $linha[1],
                'quando' => $linha[2],
            ]);
            $total['chuva']++;
        }
    }
    $pdo->commit();

    return $total;
}

/** Carrega todos os dados iniciais: arquivos .sql + usuários + séries geradas. */
function comandoSeed(): void
{
    $pdo = conexaoBanco();

    foreach (listarSql(PASTA_SEEDS) as $arquivo) {
        $qtd = executarArquivoSql($pdo, PASTA_SEEDS . '/' . $arquivo);
        saida(sprintf('  carregado %-45s (%d comandos)', $arquivo, $qtd));
    }

    $usuarios = semearUsuarios($pdo);
    saida("  usuários carregados: {$usuarios}");

    saida('  gerando séries históricas (pode levar alguns segundos)...');
    $series = semearSeries($pdo);
    saida(sprintf(
        '  leituras: %d da represa, %d de pontos de pH, %d de pluviômetros',
        $series['readings'],
        $series['ph'],
        $series['chuva']
    ));
}

/** Mostra o estado atual do banco. */
function comandoStatus(): void
{
    $pdo = conexaoBanco();
    garantirControle($pdo);

    $aplicadas = $pdo->query('SELECT migration, applied_at FROM schema_migrations ORDER BY migration')->fetchAll();
    $todas = listarSql(PASTA_MIGRATIONS);

    saida('banco: ' . env('DB_NAME', 'aquapulse') . ' em ' . env('DB_HOST', '127.0.0.1') . ':' . env('DB_PORT', '3306'));
    saida('migrations aplicadas: ' . count($aplicadas) . ' de ' . count($todas));

    foreach ($todas as $arquivo) {
        $marca = in_array($arquivo, array_column($aplicadas, 'migration'), true) ? 'OK  ' : 'FALTA';
        saida("  {$marca} {$arquivo}");
    }

    saida('');
    saida('linhas por tabela:');
    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tabelas as $tabela) {
        $qtd = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn(); // nome vindo do próprio banco, não da requisição
        saida(sprintf('  %-32s %8d', $tabela, $qtd));
    }
}

/** Apaga o banco inteiro e recria vazio (exige --force para não acontecer por engano). */
function comandoReset(array $argumentos): void
{
    if (!in_array('--force', $argumentos, true)) {
        saida('reset APAGA todos os dados. Confirme com: php backend/database/migrate.php reset --force');
        return;
    }

    $banco = env('DB_NAME', 'aquapulse');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $banco)) {
        throw new RuntimeException('Nome de banco inválido em DB_NAME.');
    }

    conexaoServidor()->exec("DROP DATABASE IF EXISTS `{$banco}`");
    saida("banco `{$banco}` apagado");
    comandoCriarBanco();
}

/* =================================================================== início */

$comando = $argv[1] ?? 'help';
$argumentos = array_slice($argv, 2);

try {
    switch ($comando) {
        case 'install':                                        // caminho completo para quem acabou de clonar o projeto
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

        case 'status':
            comandoStatus();
            break;

        case 'reset':
            comandoReset($argumentos);
            break;

        default:
            saida('Aquapulse — instalador do banco de dados');
            saida('');
            saida('  php backend/database/migrate.php install   cria o banco, aplica as migrations e popula');
            saida('  php backend/database/migrate.php migrate   aplica migrations pendentes');
            saida('  php backend/database/migrate.php seed      recarrega os dados iniciais');
            saida('  php backend/database/migrate.php status    mostra migrations aplicadas e linhas por tabela');
            saida('  php backend/database/migrate.php reset --force   apaga e recria o banco');
            saida('');
            saida('Configure antes backend/.env (ver backend/.env.example).');
    }
} catch (Throwable $e) {
    // Mensagem curta no terminal: o handler global do bootstrap responderia em
    // JSON, que não faz sentido na linha de comando.
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, 'em ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
