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
 *
 * Usada pelos endpoints de DADOS do dashboard (api/v1/*.php, monitoring/, map/).
 * Os endpoints de autenticação usam outra classe, Http\JsonResponse.
 * No front-end, assets/js/api-client.js lê exatamente os campos success,
 * data, meta e error deste envelope.
 */

declare(strict_types=1);

namespace Aquapulse\Support;

final class ApiResponse
{
    /** Envia o payload e encerra a requisição. */
    private static function send(array $payload, int $status): void
    {
        if (!headers_sent()) {                                                    // cabeçalhos só podem ser definidos antes de qualquer saída
            http_response_code($status);                                          // código HTTP (200, 400, 401, 404, 405...)
            header('Content-Type: application/json; charset=utf-8');              // avisa o navegador que o corpo é JSON em UTF-8
            header('Cache-Control: no-store, no-cache, must-revalidate');         // dados de monitoramento não podem ser servidos de cache antigo
            header('X-Content-Type-Options: nosniff');                            // impede o navegador de "adivinhar" outro tipo de conteúdo
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); // acentos e barras saem como texto legível, sem sequências de escape
        exit;                                                                     // encerra aqui: nada depois da resposta é executado
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
            'data'    => $data,                                                   // conteúdo da tela (KPIs, séries dos gráficos, tabelas...)
            'meta'    => array_merge([                                            // valores padrão; os do endpoint ($meta) sobrescrevem chaves iguais
                'source'       => 'mock',                                         // origem declarada dos dados
                'generated_at' => Clock::iso(Clock::lastUpdate()),                // instante da última coleta, em ISO 8601
                // rotulo calculado no servidor: o relogio demonstrativo e fixo,
                // entao o navegador nao pode derivar isso da data atual dele
                'updated_label' => 'há 2 min',                                    // texto usado no botão "Atualizado há..." da topbar
            ], $meta),
            'error'   => null,
        ], $status);
    }

    /**
     * Resposta de erro. A mensagem é sempre voltada ao usuário —
     * nunca contém caminho interno, stack trace ou detalhe técnico.
     *
     * @param string $code    identificador estável que o JavaScript pode testar (ex.: RESERVOIR_REQUIRED)
     * @param string $message texto exibido na tela
     * @param int    $status  código HTTP correspondente
     */
    public static function error(string $code, string $message, int $status = 400): void
    {
        self::send([
            'success' => false,                                                   // o front-end confere este campo antes de usar "data"
            'data'    => null,
            'meta'    => [],
            'error'   => ['code' => $code, 'message' => $message],
        ], $status);
    }

    /** 401 padronizado para sessão ausente ou expirada. */
    public static function unauthorized(): void
    {
        self::error('UNAUTHENTICATED', 'Sessão não encontrada ou expirada. Faça login novamente.', 401); // api-client.js redireciona ao login ao receber 401
    }

    /** 405 com o cabeçalho Allow. */
    public static function methodNotAllowed(array $allowed): void
    {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));                          // o padrão HTTP pede que o 405 informe quais métodos são aceitos
        }
        self::error('METHOD_NOT_ALLOWED', 'Método não permitido para este endpoint.', 405);
    }
}
