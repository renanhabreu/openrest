<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

/**
 * Exceção 404 Not Found.
 *
 * Lance quando um recurso solicitado não existir no banco de dados.
 *
 * Exemplo:
 *   $contrato = $db->queryOne('SELECT * FROM contratos WHERE id = ?', [$id]);
 *   if ($contrato === null) {
 *       throw new NotFoundException('Contrato não encontrado.');
 *   }
 *
 * @package OpenRest\Core\Exceptions
 */
class NotFoundException extends AppException
{
    public function __construct(string $message = 'Recurso não encontrado.')
    {
        parent::__construct($message, 404, 'NOT_FOUND');
    }
}
