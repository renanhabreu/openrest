<?php

declare(strict_types=1);

namespace OpenRest\Core;

use OpenRest\Core\Exceptions\ValidationException;
use DateTime;

/**
 * Validador de dados de entrada.
 *
 * Valida arrays de dados contra um conjunto de regras por campo.
 * Em caso de falha, lança ValidationException com todos os erros
 * encontrados — o ErrorMiddleware formata e retorna o 422.
 *
 * Regras disponíveis (combináveis com pipe "|"):
 *   required       → campo obrigatório e não vazio
 *   string         → deve ser uma string
 *   numeric        → deve ser numérico (inteiro ou decimal)
 *   integer        → deve ser um número inteiro
 *   boolean        → deve ser verdadeiro ou falso
 *   email          → deve ser um e-mail válido
 *   date           → deve ser uma data válida no formato AAAA-MM-DD
 *   min:N          → valor mínimo (numérico) ou comprimento mínimo (string)
 *   max:N          → valor máximo (numérico) ou comprimento máximo (string)
 *   in:a,b,c       → valor deve estar na lista informada
 *
 * Uso no controller:
 *   Validator::verificar($request->getParsedBody(), [
 *       'nome'        => 'required|string|max:255',
 *       'valor'       => 'required|numeric|min:0',
 *       'data_inicio' => 'required|date',
 *       'status'      => 'required|in:ativo,inativo,suspenso',
 *       'email'       => 'email',
 *   ]);
 *
 * @package OpenRest\Core
 */
class Validator
{
    /**
     * Valida os dados contra as regras fornecidas.
     *
     * Coleta todos os erros antes de lançar a exceção,
     * retornando todos os problemas de uma só vez.
     *
     * @param array $dados  Dados a serem validados (ex: $request->getParsedBody()).
     * @param array $regras Regras por campo (ex: ['nome' => 'required|string|max:100']).
     *
     * @return void
     *
     * @throws ValidationException Se um ou mais campos falharem na validação.
     */
    public static function verificar(array $dados, array $regras): void
    {
        $erros = [];

        foreach ($regras as $campo => $regrasCampo) {
            $valor = $dados[$campo] ?? null;
            $lista = explode('|', $regrasCampo);

            foreach ($lista as $regra) {
                $erro = self::aplicarRegra($campo, $valor, $regra);

                if ($erro !== null) {
                    $erros[] = $erro;
                    break; // um erro por campo é suficiente
                }
            }
        }

        if (!empty($erros)) {
            throw new ValidationException($erros);
        }
    }

    /**
     * Aplica uma regra individual a um valor.
     *
     * @param string $campo Nome do campo sendo validado.
     * @param mixed  $valor Valor a ser validado.
     * @param string $regra Regra a aplicar (ex: "max:255").
     *
     * @return array|null Array com 'campo' e 'message' se inválido, null se válido.
     */
    private static function aplicarRegra(string $campo, mixed $valor, string $regra): ?array
    {
        // Separa nome da regra e parâmetro (ex: "max:255" → ['max', '255'])
        [$nome, $param] = array_pad(explode(':', $regra, 2), 2, null);

        return match ($nome) {
            'required' => self::required($campo, $valor),
            'string'   => self::isString($campo, $valor),
            'numeric'  => self::isNumeric($campo, $valor),
            'integer'  => self::isInteger($campo, $valor),
            'boolean'  => self::isBoolean($campo, $valor),
            'email'    => self::isEmail($campo, $valor),
            'date'     => self::isDate($campo, $valor),
            'min'      => self::min($campo, $valor, (float) $param),
            'max'      => self::max($campo, $valor, (float) $param),
            'in'       => self::isIn($campo, $valor, explode(',', $param ?? '')),
            default    => null,
        };
    }

    // -------------------------------------------------------------------------
    // Regras individuais
    // -------------------------------------------------------------------------

    private static function required(string $campo, mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return self::erro($campo, "O campo '{$campo}' é obrigatório.");
        }

        return null;
    }

    private static function isString(string $campo, mixed $valor): ?array
    {
        if ($valor !== null && !is_string($valor)) {
            return self::erro($campo, "O campo '{$campo}' deve ser um texto.");
        }

        return null;
    }

    private static function isNumeric(string $campo, mixed $valor): ?array
    {
        if ($valor !== null && !is_numeric($valor)) {
            return self::erro($campo, "O campo '{$campo}' deve ser numérico.");
        }

        return null;
    }

    private static function isInteger(string $campo, mixed $valor): ?array
    {
        if ($valor !== null && filter_var($valor, FILTER_VALIDATE_INT) === false) {
            return self::erro($campo, "O campo '{$campo}' deve ser um número inteiro.");
        }

        return null;
    }

    private static function isBoolean(string $campo, mixed $valor): ?array
    {
        if ($valor !== null && filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            return self::erro($campo, "O campo '{$campo}' deve ser verdadeiro ou falso.");
        }

        return null;
    }

    private static function isEmail(string $campo, mixed $valor): ?array
    {
        if ($valor !== null && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            return self::erro($campo, "O campo '{$campo}' deve ser um e-mail válido.");
        }

        return null;
    }

    private static function isDate(string $campo, mixed $valor): ?array
    {
        if ($valor !== null) {
            $data = DateTime::createFromFormat('Y-m-d', (string) $valor);

            if ($data === false || $data->format('Y-m-d') !== $valor) {
                return self::erro($campo, "O campo '{$campo}' deve ser uma data válida no formato AAAA-MM-DD.");
            }
        }

        return null;
    }

    private static function min(string $campo, mixed $valor, float $min): ?array
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor) && mb_strlen($valor) < $min) {
            return self::erro($campo, "O campo '{$campo}' deve ter no mínimo {$min} caracteres.");
        }

        if (is_numeric($valor) && (float) $valor < $min) {
            return self::erro($campo, "O campo '{$campo}' deve ser no mínimo {$min}.");
        }

        return null;
    }

    private static function max(string $campo, mixed $valor, float $max): ?array
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor) && mb_strlen($valor) > $max) {
            return self::erro($campo, "O campo '{$campo}' deve ter no máximo {$max} caracteres.");
        }

        if (is_numeric($valor) && (float) $valor > $max) {
            return self::erro($campo, "O campo '{$campo}' deve ser no máximo {$max}.");
        }

        return null;
    }

    private static function isIn(string $campo, mixed $valor, array $permitidos): ?array
    {
        if ($valor !== null && !in_array((string) $valor, $permitidos, strict: true)) {
            $lista = implode(', ', $permitidos);
            return self::erro($campo, "O campo '{$campo}' deve ser um dos valores: {$lista}.");
        }

        return null;
    }

    /**
     * Formata um erro de validação no padrão esperado pelo ValidationException.
     *
     * @param string $campo    Nome do campo.
     * @param string $mensagem Mensagem descritiva do erro.
     *
     * @return array{campo: string, message: string}
     */
    private static function erro(string $campo, string $mensagem): array
    {
        return [
            'campo'   => $campo,
            'message' => $mensagem,
        ];
    }
}
