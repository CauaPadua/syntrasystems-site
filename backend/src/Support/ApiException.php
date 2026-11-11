<?php
/**
 * Aquapulse — erro de regra de negócio que deve virar uma resposta da API.
 *
 * As classes de serviço não imprimem nada: quando uma validação falha, lançam
 * esta exceção com o código, a mensagem e o status HTTP. O endpoint captura e
 * responde com ApiResponse::error() — ver aq_api_run() em api/v1/_boot.php.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use RuntimeException;

final class ApiException extends RuntimeException
{
    private string $errorCode;
    private int $status;

    /** @var array<string,string> */
    private array $details;

    /**
     * @param string               $errorCode identificador estável (ex.: EQUIPMENT_NOT_FOUND)
     * @param string               $message   texto exibido ao usuário
     * @param int                  $status    código HTTP
     * @param array<string,string> $details   erros por campo, em validações de formulário
     */
    public function __construct(string $errorCode, string $message, int $status = 400, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string,string> */
    public function details(): array
    {
        return $this->details;
    }
}
