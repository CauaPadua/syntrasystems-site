<?php
/**
 * Aquapulse — leitura e validação básica da requisição HTTP.
 *
 * Não conhece regras de negócio: apenas entrega dados já normalizados
 * para os pontos de entrada da API.
 */

declare(strict_types=1);

namespace Aquapulse\Http;

final class Request
{
    /** Método HTTP da requisição atual. */
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** A requisição usa exatamente este método? */
    public static function isMethod(string $expected): bool
    {
        return self::method() === strtoupper($expected);
    }

    /** O Content-Type declara JSON? (aceita charset e demais parâmetros) */
    public static function hasJsonContentType(): bool
    {
        $header = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $tipo = strtolower(trim(explode(';', $header)[0]));

        return $tipo === 'application/json';
    }

    /** Corpo bruto da requisição. */
    public static function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    /**
     * Decodifica o corpo JSON.
     *
     * @return array<string,mixed>|null null quando o JSON é inválido ou não é um objeto.
     */
    public static function decodeJson(string $raw): ?array
    {
        if (trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true, 16);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Lê um campo de texto do corpo já decodificado.
     *
     * Valores não escalares viram string vazia — nunca chegam às regras de negócio.
     */
    public static function stringField(array $body, string $key, bool $trim = true): string
    {
        $valor = $body[$key] ?? '';

        if (!is_string($valor)) {
            return '';
        }

        return $trim ? trim($valor) : $valor;
    }

    /** Normaliza o e-mail: sem espaços nas pontas e em minúsculas. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /** Validação básica de formato de e-mail. */
    public static function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
