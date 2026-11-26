<?php
/**
 * Aquapulse — limite de tentativas de login (tabela login_attempts).
 *
 * Sem limite, um programa pode testar milhares de senhas na mesma conta. Com ele,
 * em 15 minutos são aceitas até 5 tentativas erradas por e-mail e 20 por endereço
 * de rede (IP); depois disso, a resposta é 429 até a janela passar.
 *
 * A tentativa é gravada ANTES de conferir a senha: assim, várias requisições ao
 * mesmo tempo não passam todas antes de a primeira ser contada. Vale também para
 * e-mail que não existe, com a mesma mensagem, para não revelar quem tem conta.
 *
 * Usado por api/v1/auth/login.php (só com banco configurado).
 */

declare(strict_types=1);

namespace Aquapulse\Auth;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use PDO;

final class LoginThrottle
{
    public const MAX_PER_EMAIL = 5;
    public const MAX_PER_IP = 20;

    /** Janela em que as tentativas contam (segundos). */
    public const WINDOW = 900;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra uma tentativa e a recusa se o limite já foi atingido.
     *
     * @throws ApiException TOO_MANY_ATTEMPTS (429)
     */
    public function hit(string $email, string $ip): void
    {
        $desde = Clock::sql(Clock::now()->modify('-' . self::WINDOW . ' seconds'));
        $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at <= :desde')->execute(['desde' => $desde]);

        $this->pdo->prepare('INSERT INTO login_attempts (email_hash, ip, attempted_at) VALUES (:email, :ip, :agora)')
            ->execute(['email' => self::hash($email), 'ip' => $ip, 'agora' => Clock::sql()]);
        $id = (int) $this->pdo->lastInsertId();

        $espera = max(
            $this->espera('email_hash', self::hash($email), self::MAX_PER_EMAIL),
            $this->espera('ip', $ip, self::MAX_PER_IP)
        );
        if ($espera === 0) {
            return;
        }

        // A recusada não conta: insistir enquanto está bloqueado não aumenta a espera.
        $this->pdo->prepare('DELETE FROM login_attempts WHERE id = :id')->execute(['id' => $id]);

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Retry-After: ' . $espera);
        }
        $minutos = (int) ceil($espera / 60);
        throw new ApiException(
            'TOO_MANY_ATTEMPTS',
            'Muitas tentativas de acesso. Espere ' . $minutos . ($minutos === 1 ? ' minuto' : ' minutos') . ' e tente de novo.',
            429
        );
    }

    /** Senha certa: esquece as tentativas deste e-mail. */
    public function clear(string $email): void
    {
        $this->pdo->prepare('DELETE FROM login_attempts WHERE email_hash = :email')->execute(['email' => self::hash($email)]);
    }

    /** Segundos até caber uma nova tentativa; 0 = dentro do limite. A coluna vem só deste arquivo. */
    private function espera(string $coluna, string $valor, int $maximo): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total, MIN(attempted_at) AS primeira FROM login_attempts WHERE {$coluna} = :valor");
        $stmt->execute(['valor' => $valor]);
        $l = $stmt->fetch();

        if ((int) $l['total'] <= $maximo) {
            return 0;
        }
        return max(1, Clock::parse((string) $l['primeira'])->getTimestamp() + self::WINDOW - Clock::now()->getTimestamp());
    }

    private static function hash(string $email): string
    {
        return hash('sha256', $email);
    }
}
