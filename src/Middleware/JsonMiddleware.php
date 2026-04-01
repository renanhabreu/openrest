<?php

declare(strict_types=1);

namespace OpenRest\Core\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Middleware de parse automático de corpo JSON.
 *
 * O Slim não parseia automaticamente requisições com Content-Type
 * application/json. Este middleware lê o body e disponibiliza os
 * dados via $request->getParsedBody() em todos os controllers.
 *
 * Aplicação: global (adicionar em public/api.php via $app->add()).
 *
 * @package OpenRest\Core\Middleware
 */
class JsonMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(Request $request, Handler $handler): Response
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();

            if (!empty($body)) {
                $dados = json_decode($body, associative: true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $request = $request->withParsedBody($dados);
                }
            }
        }

        return $handler->handle($request);
    }
}
