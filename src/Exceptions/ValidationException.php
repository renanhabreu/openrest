<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

/**
 * Exceção 422 Unprocessable Entity — falha de validação.
 *
 * Recebe um array de erros por campo, gerado automaticamente pelo Validator.
 * O ErrorMiddleware formata cada erro individualmente no envelope de resposta.
 *
 * Em condições normais, não lance esta exceção diretamente.
 * Use o Validator, que a lança automaticamente ao encontrar erros:
 *
 *   Validator::verificar($data, [
 *       'nome'  => 'required|string|max:255',
 *       'valor' => 'required|numeric|min:0',
 *   ]);
 *
 * @package OpenRest\Core\Exceptions
 */
class ValidationException extends AppException
{
    /**
     * @param array  $erros   Array de erros gerado pelo Validator.
     *                        Formato: [['campo' => 'nome', 'message' => '...'], ...]
     * @param string $message Mensagem geral da exceção (opcional).
     */
    public function __construct(
        private readonly array $erros,
        string $message = 'Os dados enviados são inválidos.'
    ) {
        parent::__construct($message, 422, 'VALIDATION_ERROR');
    }

    /**
     * Retorna os erros de validação por campo.
     *
     * @return array
     */
    public function getErros(): array
    {
        return $this->erros;
    }
}
