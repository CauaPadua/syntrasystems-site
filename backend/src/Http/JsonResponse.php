<?php
/**
 * Aquapulse — construção das respostas JSON da API.
 *
 * Toda saída da API passa por aqui: nenhum endpoint imprime JSON diretamente.
 * Isso garante cabeçalhos, formato de envelope e códigos HTTP consistentes.
 *
 * Na prática, esta classe atende os endpoints de AUTENTICAÇÃO
 * (api/v1/auth/login.php, logout.php, me.php) e o tratador global de exceções
 * do bootstrap. O envelope aqui é { "data": ... } ou { "error": ... }.
 * Os endpoints de dados do dashboard usam Support\ApiResponse, com o envelope
 * { success, data, meta, error }.
 */

declare(strict_types=1);

namespace Aquapulse\Http;

final class JsonResponse
{
    /** Envia o payload como JSON e encerra a requisição. */
    public static function send(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');   // respostas de login/sessão nunca podem ficar em cache
            header('Pragma: no-cache');                                     // equivalente para proxies e clientes HTTP/1.0 antigos
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;                                                               // encerra a requisição logo após responder
    }

    /** Resposta bem-sucedida: sempre dentro do envelope "data". */
    public static function data(array $data, int $status = 200): void
    {
        self::send(['data' => $data], $status);                             // ex.: login devolve {"data":{"user":{...}}}
    }

    /**
     * Resposta de erro: sempre dentro do envelope "error".
     *
     * @param array<string,string> $details Erros por campo (usado apenas em 422).
     */
    public static function error(string $code, string $message, int $status, array $details = []): void
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {                                              // só inclui "details" quando há erros de campo (ex.: e-mail vazio)
            $error['details'] = $details;                                   // login.js usa isso para marcar o campo específico no formulário
        }

        self::send(['error' => $error], $status);
    }

    /** 405 com o cabeçalho Allow correspondente. */
    public static function methodNotAllowed(array $allowed): void
    {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));                    // informa quais métodos o endpoint aceita (ex.: "POST")
        }

        self::error(
            'METHOD_NOT_ALLOWED',
            'Método não permitido para este endpoint.',
            405
        );
    }
}
