<?php
/**
 * Aquapulse — envelope padrão das respostas da API do sistema.
 *
 * Formato de sucesso:
 *   { "success": true, "data": {...}, "meta": {...}, "error": null }
 *
 * Formato de erro:
 *   { "success": false, "data": null, "meta": {}, "error": { "code", "message" } }
 *
 * Nenhum endpoint imprime JSON diretamente: tudo passa por aqui, garantindo
 * cabeçalhos, envelope e códigos HTTP consistentes.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

final class ApiResponse
{
    /** Envia o payload e encerra a requisição. */
    private static function send(array $payload, int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Resposta de sucesso.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $meta Contexto aplicado (empresa, represa, período...).
     */
    public static function success(array $data, array $meta = [], int $status = 200): void
    {
        self::send([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge([
                'source'       => 'mock',
                'generated_at' => Clock::iso(Clock::lastUpdate()),
                // rotulo calculado no servidor: o relogio demonstrativo e fixo,
                // entao o navegador nao pode derivar isso da data atual dele
                'updated_label' => 'há 2 min',
            ], $meta),
            'error'   => null,
        ], $status);
    }

    /**
     * Resposta de erro. A mensagem é sempre voltada ao usuário —
     * nunca contém caminho interno, stack trace ou detalhe técnico.
     */
    public static function error(string $code, string $message, int $status = 400): void
    {
        self::send([
            'success' => false,
            'data'    => null,
            'meta'    => [],
            'error'   => ['code' => $code, 'message' => $message],
        ], $status);
    }

    /** 401 padronizado para sessão ausente ou expirada. */
    public static function unauthorized(): void
    {
        self::error('UNAUTHENTICATED', 'Sessão não encontrada ou expirada. Faça login novamente.', 401);
    }

    /** 405 com o cabeçalho Allow. */
    public static function methodNotAllowed(array $allowed): void
    {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));
        }
        self::error('METHOD_NOT_ALLOWED', 'Método não permitido para este endpoint.', 405);
    }
}
