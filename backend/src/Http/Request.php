<?php
/**
 * Aquapulse — leitura e validação básica da requisição HTTP.
 *
 * Não conhece regras de negócio: apenas entrega dados já normalizados
 * para os pontos de entrada da API.
 *
 * Usada principalmente por api/v1/auth/login.php, que recebe e-mail e senha
 * em JSON no corpo de um POST.
 */

declare(strict_types=1);

namespace Aquapulse\Http;

final class Request
{
    /** Método HTTP da requisição atual. */
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));   // normaliza para maiúsculas ("post" -> "POST"); sem informação, assume GET
    }

    /** A requisição usa exatamente este método? */
    public static function isMethod(string $expected): bool
    {
        return self::method() === strtoupper($expected);
    }

    /** O Content-Type declara JSON? (aceita charset e demais parâmetros) */
    public static function hasJsonContentType(): bool
    {
        $header = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''); // o nome da variável muda conforme o servidor (Apache, FastCGI, embutido)
        $tipo = strtolower(trim(explode(';', $header)[0]));                   // "application/json; charset=utf-8" -> "application/json"

        return $tipo === 'application/json';
    }

    /** Corpo bruto da requisição. */
    public static function rawBody(): string
    {
        return (string) file_get_contents('php://input');                     // $_POST só é preenchido para formulários; JSON precisa ser lido do fluxo bruto
    }

    /**
     * Decodifica o corpo JSON.
     *
     * @return array<string,mixed>|null null quando o JSON é inválido ou não é um objeto.
     */
    public static function decodeJson(string $raw): ?array
    {
        if (trim($raw) === '') {                                              // corpo vazio não é JSON válido para o login
            return null;
        }

        $decoded = json_decode($raw, true, 16);                               // true = objeto vira array associativo; 16 = profundidade máxima (bloqueia JSON aninhado demais)

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {   // erro de sintaxe, ou JSON válido mas escalar (ex.: "123" ou "true")
            return null;
        }

        return $decoded;
    }

    /**
     * Lê um campo de texto do corpo já decodificado.
     *
     * Valores não escalares viram string vazia — nunca chegam às regras de negócio.
     *
     * @param bool $trim false para senhas: espaços fazem parte da senha e não podem ser removidos
     */
    public static function stringField(array $body, string $key, bool $trim = true): string
    {
        $valor = $body[$key] ?? '';                                           // campo ausente = string vazia

        if (!is_string($valor)) {                                             // {"email": ["x"]} ou {"email": 123} são rejeitados como vazios
            return '';
        }

        return $trim ? trim($valor) : $valor;
    }

    /** Normaliza o e-mail: sem espaços nas pontas e em minúsculas. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');                          // "Ana@Empresa.com " e "ana@empresa.com" passam a ser o mesmo login
    }

    /** Validação básica de formato de e-mail. */
    public static function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false; // validador nativo do PHP para o formato nome@dominio
    }
}
