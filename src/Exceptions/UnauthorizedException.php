<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

/**
 * Exceção 401 Unauthorized.
 *
 * Lance quando o usuário não estiver autenticado ou o token for inválido.
 * Para acesso negado a um usuário autenticado, use ForbiddenException (403).
 *
 * Exemplos:
 *   throw new UnauthorizedException('Token de autenticação não fornecido.');
 *   throw new UnauthorizedException('Token inválido.');
 *   throw new UnauthorizedException('Token expirado.');
 *
 * @package OpenRest\Core\Exceptions
 */
class UnauthorizedException extends AppException
{
    public function __construct(string $message = 'Autenticação necessária.')
    {
        parent::__construct($message, 401, 'UNAUTHORIZED');
    }
}
}
