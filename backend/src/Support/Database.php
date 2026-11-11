<?php
/**
 * Aquapulse — conexão com o banco de dados MySQL via PDO.
 *
 * A conexão é aberta uma única vez por requisição e reaproveitada por todos os
 * repositórios. As credenciais vêm das variáveis de ambiente (backend/.env ou
 * configuração do servidor) — nunca ficam escritas no código.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instancia = null;

    /** Existe banco configurado? Sem DB_HOST o sistema usa os dados de demonstração. */
    public static function configurado(): bool
    {
        return (bool) getenv('DB_HOST');
    }

    /**
     * Devolve a conexão PDO, criando-a na primeira chamada.
     *
     * @throws PDOException com mensagem genérica quando não consegue conectar
     */
    public static function conexao(): PDO
    {
        if (self::$instancia !== null) {
            return self::$instancia;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $porta = getenv('DB_PORT') ?: '3306';
        $banco = getenv('DB_NAME') ?: 'aquapulse';
        $usuario = getenv('DB_USER') ?: '';
        $senha = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

        try {
            self::$instancia = new PDO($dsn, $usuario, $senha, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // As datas são gravadas no horário de Brasília (mesmo fuso do Clock).
            // Sem isto, NOW() e CURRENT_TIMESTAMP seguiriam o fuso do servidor.
            self::$instancia->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            // A mensagem original pode conter host e usuário do banco.
            throw new PDOException('Falha ao conectar ao banco de dados.');
        }

        return self::$instancia;
    }
}
