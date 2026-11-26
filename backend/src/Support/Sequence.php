<?php
/**
 * Aquapulse — números em sequência sem repetição (tabela sequences).
 *
 * Usado para os códigos ALT-1001, REP-2001... Antes o código era "o maior já
 * usado + 1": duas gravações ao mesmo tempo liam o mesmo maior número e a segunda
 * falhava. Aqui o próprio UPDATE soma 1 e devolve o número; a linha fica travada
 * até o fim da transação, então o segundo pedido espera e recebe o seguinte.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use PDO;
use RuntimeException;

final class Sequence
{
    /** Próximo número da sequência ('alert' ou 'report'). */
    public static function next(PDO $pdo, string $name): int
    {
        // LAST_INSERT_ID(x) guarda x na conexão: a leitura seguinte devolve o número desta conexão, não o de outra.
        $stmt = $pdo->prepare('UPDATE sequences SET value = LAST_INSERT_ID(value + 1) WHERE name = :name');
        $stmt->execute(['name' => $name]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Sequência não cadastrada: {$name}. Rode as migrations.");
        }

        return (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    }
}
