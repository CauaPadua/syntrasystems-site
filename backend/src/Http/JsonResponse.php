<?php
/**
 * Aquapulse — construção das respostas JSON da API.
 *
 * Toda saída da API passa por aqui: nenhum endpoint imprime JSON diretamente.
 * Isso garante cabeçalhos, formato de envelope e códigos HTTP consistentes.
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
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Resposta bem-sucedida: sempre dentro do envelope "data". */
    public static function data(array $data, int $status = 200): void
    {
        self::send(['data' => $data], $status);
    }

    /**
     * Resposta de erro: sempre dentro do envelope "error".
     *
     * @param array<string,string> $details Erros por campo (usado apenas em 422).
     */
    public static function error(string $code, string $message, int $status, array $details = []): void
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        self::send(['error' => $error], $status);
    }

    /** 405 com o cabeçalho Allow correspondente. */
    public static function methodNotAllowed(array $allowed): void
    {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));
        }

        self::error(
            'METHOD_NOT_ALLOWED',
            'Método não permitido para este endpoint.',
            405
        );
    }
}
