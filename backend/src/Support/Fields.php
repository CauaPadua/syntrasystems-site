<?php
/**
 * Aquapulse — leitura e validação dos campos de um formulário.
 *
 * Cada método lê um campo, confere e devolve o valor pronto para o SQL; quando
 * o campo está errado, guarda a mensagem. No fim, check() devolve todas as
 * mensagens de uma vez (422), para a tela mostrar cada uma abaixo do seu campo.
 *
 *   $f = new Fields($dados);
 *   $nome  = $f->text('name', 3, 120, 'Informe o nome.');
 *   $email = $f->email('email');
 *   $f->check();
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Http\Request;

final class Fields
{
    /** O bcrypt, usado por password_hash(), só considera os primeiros 72 bytes. */
    private const PASSWORD_MAX = 72;

    /** @var array<string,mixed> */
    private array $dados;

    /** @var array<string,string> */
    private array $erros = [];

    /** @param array<string,mixed> $dados corpo da requisição */
    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    /** Texto com tamanho mínimo e máximo. Com $min = 0, o campo é opcional. */
    public function text(string $campo, int $min, int $max, string $mensagem): string
    {
        $valor = $this->bruto($campo);
        $tamanho = mb_strlen($valor, 'UTF-8');

        if ($tamanho < $min || $tamanho > $max) {
            $this->erros[$campo] = $mensagem;
        }
        return $valor;
    }

    /** Texto que precisa seguir uma expressão regular. */
    public function pattern(string $campo, string $regex, string $mensagem): string
    {
        $valor = $this->bruto($campo);

        if (!preg_match($regex, $valor)) {
            $this->erros[$campo] = $mensagem;
        }
        return $valor;
    }

    /** E-mail em minúsculas e sem espaços (a mesma normalização do login). */
    public function email(string $campo): string
    {
        $valor = Request::normalizeEmail($this->bruto($campo));

        if (!Request::isValidEmail($valor) || strlen($valor) > 190) {
            $this->erros[$campo] = 'Informe um e-mail válido.';
        }
        return $valor;
    }

    /** Senha exatamente como digitada (espaços fazem parte dela). */
    public function password(string $campo): string
    {
        $valor = is_string($this->dados[$campo] ?? null) ? $this->dados[$campo] : '';

        if (mb_strlen($valor, 'UTF-8') < 8) {
            $this->erros[$campo] = 'A senha precisa ter pelo menos 8 caracteres.';
        } elseif (strlen($valor) > self::PASSWORD_MAX) {
            $this->erros[$campo] = 'Use uma senha de até ' . self::PASSWORD_MAX . ' caracteres.';
        }
        return $valor;
    }

    /** Número dentro de uma faixa, escrito como no Brasil (12,5 ou 1.500) ou com ponto decimal (-22.3875). */
    public function number(string $campo, float $min, float $max, string $mensagem): float
    {
        $bruto = $this->dados[$campo] ?? '';
        $texto = is_string($bruto) ? str_replace(',', '.', trim($bruto)) : $bruto;

        // "1.500" é mil e quinhentos — a menos que isso saia da faixa (-22.387 é uma latitude).
        if (is_string($bruto) && preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', trim($bruto))) {
            $milhar = (float) str_replace(['.', ','], ['', '.'], trim($bruto));
            if ($milhar >= $min && $milhar <= $max) {
                return $milhar;
            }
        }

        if (!is_numeric($texto) || (float) $texto < $min || (float) $texto > $max) {
            $this->erros[$campo] = $mensagem;
            return 0.0;
        }
        return (float) $texto;
    }

    /**
     * Valor que precisa estar em uma lista fechada.
     *
     * @param array<int,string> $validos
     */
    public function option(string $campo, array $validos, string $mensagem): string
    {
        $valor = $this->bruto($campo);

        if (!in_array($valor, $validos, true)) {
            $this->erros[$campo] = $mensagem;
        }
        return $valor;
    }

    /**
     * Lista de valores, todos dentro de uma lista fechada.
     *
     * @param array<int,string> $validos
     * @return array<int,string>
     */
    public function options(string $campo, array $validos, string $mensagem): array
    {
        $bruto = $this->dados[$campo] ?? [];
        $valores = is_array($bruto) ? array_values(array_unique(array_filter($bruto, 'is_string'))) : [];

        if ($valores === [] || array_diff($valores, $validos) !== []) {
            $this->erros[$campo] = $mensagem;
            return [];
        }
        return $valores;
    }

    /** Registra um erro encontrado fora daqui (regra que envolve mais de um campo, duplicidade...). */
    public function error(string $campo, string $mensagem): void
    {
        $this->erros[$campo] = $this->erros[$campo] ?? $mensagem;
    }

    public function hasError(string ...$campos): bool
    {
        return array_intersect_key($this->erros, array_flip($campos)) !== [];
    }

    /** Interrompe com 422 se algum campo tiver erro. */
    public function check(): void
    {
        if ($this->erros !== []) {
            throw new ApiException('VALIDATION_ERROR', 'Verifique os campos informados.', 422, $this->erros);
        }
    }

    private function bruto(string $campo): string
    {
        return is_string($this->dados[$campo] ?? null) ? trim($this->dados[$campo]) : '';
    }
}
