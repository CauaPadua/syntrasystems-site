<?php
/**
 * Aquapulse — teste manual de conexão com o banco.
 *
 * Rode pelo terminal com:
 *   php backend/tools/test_connection.php
 *
 * Não é chamado pelo site. Serve só para conferir se o .env e o
 * Database.php estão configurados corretamente, sem precisar testar
 * o site inteiro.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Aquapulse\Support\Database;

echo "Tentando conectar ao banco...\n";

try {
    $pdo = Database::conexao();
    $versao = $pdo->query('SELECT VERSION() AS v')->fetch();
    echo "Conexão OK. Versão do MySQL: " . $versao['v'] . "\n";

    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas encontradas (" . count($tabelas) . "):\n";
    foreach ($tabelas as $tabela) {
        echo "  - {$tabela}\n";
    }
} catch (\Throwable $e) {
    echo "FALHOU: " . $e->getMessage() . "\n";
    exit(1);
}
