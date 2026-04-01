<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

/**
 * Exceção 409 Conflict.
 *
 * Lance quando a operação conflitar com o estado atual do recurso.
 * Casos comuns: chave duplicada, registro já existente, transição de
 * estado inválida (ex: cancelar um contrato já encerrado).
 *
 * Exemplo:
 *   $existente = $db->queryOne('SELECT id FROM contratos WHERE numero = ?', [$numero]);
 *   if ($existente !== null) {
 *       throw new ConflictException("Já existe um contrato com o número {$numero}.");
 *   }
 *
 * @package OpenRest\Core\Exceptions
 */
class ConflictException extends AppException
{
    public function __construct(string $message = 'Conflito com o estado atual do recurso.')
    {
        parent::__construct($message, 409, 'CONFLICT');
    }
}
