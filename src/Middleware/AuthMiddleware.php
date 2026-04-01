<?php

declare(strict_types=1);

namespace OpenRest\Core\Middleware;

use OpenRest\Core\Exceptions\UnauthorizedException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Throwable;

/**
 * Middleware de autenticação via JWT (JSON Web Token).
 *
 * Valida o token Bearer no header Authorization. Em caso de sucesso,
 * injeta o payload decodificado no atributo 'auth' da requisição.
 *
 * Leitura do payload no controller:
 *   $auth = $request->getAttribute('auth');
 *   $usuarioId = $auth->sub;
 *   $perfil    = $auth->perfil;
 *
 * Aplicação seletiva — adicione apenas nas rotas que exigem login:
 *   $app->group('/v1/contrato', function ($group) {
 *       // rotas aqui
 *   })->add(AuthMiddleware::class);
 *
 * Configuração:
 *   JWT_SECRET=sua-chave-secreta-no-.env
 *
 * @package OpenRest\Core\Middleware
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     *
     * @throws UnauthorizedException Se o token estiver ausente, inválido ou expirado.
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        
        if (empty($header) || !str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException('Token de autenticação não fornecido.');
        }
        
        $token = substr($header, 7);
        
        try {
            $now = time();
            
            $payload = JWT::decode(
                $token,
                new Key($_ENV['JWT_SECRET'] ?? '', 'HS256')
            );
            
            // Verifica se o token está expirado
            if (isset($payload['exp']) && $payload['exp'] < $now) {
                throw new UnauthorizedException('Token expirado.');
            }
            
            // Injeta o payload decodificado para uso nos controllers
            $request = $request->withAttribute('auth', $payload);
        } catch (Throwable $e) {
            throw new UnauthorizedException('Token inválido ou expirado.');
        }
        
        return $handler->handle($request);
    }
}