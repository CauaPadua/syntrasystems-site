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

require __DIR__ . '/../bootstrap.php';                                        // carrega o .env, o autoload e os tratadores de erro

use Aquapulse\Support\Database;

echo "Tentando conectar ao banco...\n";

try {
    $pdo = Database::conexao();                                              // usa as mesmas variáveis DB_* que o site usaria
    $versao = $pdo->query('SELECT VERSION() AS v')->fetch();                 // consulta simples que só funciona com a conexão aberta
    echo "Conexão OK. Versão do MySQL: " . $versao['v'] . "\n";

    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);      // FETCH_COLUMN devolve só os nomes, sem arrays aninhados
    echo "Tabelas encontradas (" . count($tabelas) . "):\n";
    foreach ($tabelas as $tabela) {                                          // lista as tabelas para conferir se os scripts SQL foram executados
        echo "  - {$tabela}\n";
    }
} catch (\Throwable $e) {                                                    // qualquer falha (credencial errada, banco fora do ar...)
    echo "FALHOU: " . $e->getMessage() . "\n";                               // a mensagem de Database é genérica, sem expor host ou usuário
    exit(1);                                                                 // código de saída 1 sinaliza erro para o terminal
}
