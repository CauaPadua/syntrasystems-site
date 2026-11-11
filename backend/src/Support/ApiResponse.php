<?php
/**
 * Aquapulse — envelope padrão das respostas da API do sistema.
 *
 * Sucesso:  { "success": true,  "data": {...}, "meta": {...}, "error": null }
 * Erro:     { "success": false, "data": null,  "meta": {},    "error": { "code", "message" } }
 *
 * Nenhum endpoint de dados imprime JSON diretamente: tudo passa por aqui. No
 * front-end, assets/js/api-client.js lê exatamente estes campos.
 * (Os endpoints de login/logout usam Http\JsonResponse, com envelope próprio.)
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
     * @param array<string,mixed> $data
     * @param array<string,mixed> $meta contexto aplicado (empresa, represa, período...)
     */
    public static function success(array $data, array $meta = [], int $status = 200): void
    {
        self::send([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge([
                'source'       => Database::configurado() ? 'database' : 'demo',   // de onde vieram os dados
                'generated_at' => Clock::iso(),                                    // quando esta resposta foi montada
            ], $meta),
            'error'   => null,
        ], $status);
    }

    /**
     * A mensagem é sempre voltada ao usuário — nunca contém caminho interno,
     * stack trace ou detalhe técnico.
     *
     * @param string               $code    identificador estável que o JavaScript pode testar
     * @param array<string,string> $details erros por campo, em respostas de validação (422)
     */
    public static function error(string $code, string $message, int $status = 400, array $details = []): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        self::send([
            'success' => false,
            'data'    => null,
            'meta'    => [],
            'error'   => $error,
        ], $status);
    }

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
