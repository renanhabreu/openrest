<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

use RuntimeException;

/**
 * Exceção base da aplicação.
 *
 * Todas as exceções de domínio herdam desta classe.
 * Carrega o código HTTP e o código interno de erro para
 * padronização da resposta pelo ErrorMiddleware.
 *
 * Não lance esta classe diretamente — use as subclasses:
 *   - NotFoundException      → 404
 *   - ValidationException    → 422
 *   - UnauthorizedException  → 401
 *   - ForbiddenException     → 403
 *   - ConflictException      → 409
 *
 * @package OpenRest\Core\Exceptions
 */
class AppException extends RuntimeException
{
    /**
     * @param string $message   Mensagem descritiva do erro.
     * @param int    $httpStatus Código HTTP correspondente (ex: 404, 422).
     * @param string $errorCode  Código interno do erro (ex: "NOT_FOUND").
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly string $errorCode
    ) {
        parent::__construct($message);
    }

    /**
     * Retorna o código HTTP associado a esta exceção.
     *
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Retorna o código de erro interno para uso no envelope JSON.
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
