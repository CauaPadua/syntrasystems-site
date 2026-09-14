<?php
/*
 * Aquapulse — conexão com o banco de dados MySQL via PDO.
 *
 * Só é usada quando existe a variável DB_HOST (ver Support\Container). A
 * conexão é aberta uma única vez por requisição e reaproveitada por todos os
 * repositórios PDO (usuários e monitoramento).
 *
 * As credenciais vêm das variáveis de ambiente (backend/.env ou configuração do
 * servidor) — nunca ficam escritas no código.
 */
declare(strict_types=1);

namespace Aquapulse\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instancia = null;                                // conexão compartilhada; null até a primeira chamada

    /**
     * Devolve a conexão PDO, criando-a na primeira chamada.
     *
     * @return PDO conexão configurada para lançar exceções e devolver arrays associativos
     * @throws PDOException com mensagem genérica quando não consegue conectar
     */
    public static function conexao(): PDO
    {
        if (self::$instancia !== null) {                                  // já conectou nesta requisição: reutiliza (evita abrir várias conexões)
            return self::$instancia;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';                         // endereço do servidor MySQL; "?:" usa o padrão quando a variável está vazia
        $porta = getenv('DB_PORT') ?: '3306';                             // porta padrão do MySQL
        $banco = getenv('DB_NAME') ?: 'aquapulse';                        // nome do schema criado pelos scripts em storage/mock/sql
        $usuario = getenv('DB_USER') ?: '';                               // usuário e senha do banco: só vêm do ambiente
        $senha = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4"; // DSN = "endereço" da conexão; utf8mb4 suporta acentos e todos os caracteres Unicode

        try {
            self::$instancia = new PDO($dsn, $usuario, $senha, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,              // qualquer erro de SQL vira exceção (não passa despercebido como "false")
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // fetch() devolve ['coluna' => valor], sem índices numéricos duplicados
            ]);
        } catch (PDOException $e) {                                       // a mensagem original do PDO pode conter host e usuário do banco...
            throw new PDOException('Falha ao conectar ao banco de dados.'); // ...então é trocada por uma genérica; o handler global responde 500
        }

        return self::$instancia;
    }
}
