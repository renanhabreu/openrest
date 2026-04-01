<?php

declare(strict_types=1);

namespace OpenRest\Core\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Middleware de CORS (Cross-Origin Resource Sharing).
 *
 * Adiciona os headers necessários para que browsers permitam
 * requisições originadas de domínios diferentes da API.
 *
 * Responde a requisições OPTIONS (preflight) imediatamente com 200,
 * sem propagar para os controllers.
 *
 * Configuração via variáveis de ambiente no .env:
 *   CORS_ORIGIN  → domínios permitidos (padrão: *)
 *   CORS_METHODS → métodos HTTP permitidos
 *   CORS_HEADERS → headers permitidos
 *
 * Aplicação: global (adicionar em public/api.php via $app->add()).
 *
 * @package OpenRest\Core\Middleware
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(Request $request, Handler $handler): Response
    {
        $resposta = $handler->handle($request);

        $resposta = $resposta
            ->withHeader('Access-Control-Allow-Origin',  $_ENV['CORS_ORIGIN']  ?? '*')
            ->withHeader('Access-Control-Allow-Methods', $_ENV['CORS_METHODS'] ?? 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', $_ENV['CORS_HEADERS'] ?? 'Content-Type, Authorization, X-Requested-With');

        // Requisições preflight (OPTIONS) são respondidas imediatamente
        if ($request->getMethod() === 'OPTIONS') {
            return $resposta->withStatus(200);
        }

        return $resposta;
    }
}
