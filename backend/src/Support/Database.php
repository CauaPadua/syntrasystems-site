<?php
declare(strict_types=1);

namespace Aquapulse\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instancia = null;

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
        } catch (PDOException $e) {
            throw new PDOException('Falha ao conectar ao banco de dados.');
        }

        return self::$instancia;
    }
}
