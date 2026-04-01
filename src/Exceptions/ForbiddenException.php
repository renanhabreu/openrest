<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

/**
 * Exceção 403 Forbidden.
 *
 * Lance quando o usuário estiver autenticado mas não tiver
 * permissão para acessar o recurso solicitado.
 *
 * Diferença em relação a UnauthorizedException:
 *   401 → não autenticado (quem é você?)
 *   403 → autenticado, mas sem permissão (eu sei quem você é, mas não pode)
 *
 * Exemplo:
 *   $auth = $request->getAttribute('auth');
 *   if ($auth->perfil !== 'admin') {
 *       throw new ForbiddenException('Apenas administradores podem excluir contratos.');
 *   }
 *
 * @package OpenRest\Core\Exceptions
 */
class ForbiddenException extends AppException
{
    public function __construct(string $message = 'Acesso não autorizado.')
    {
        parent::__construct($message, 403, 'FORBIDDEN');
    }
}
