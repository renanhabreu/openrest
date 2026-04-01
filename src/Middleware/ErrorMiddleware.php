<?php

declare(strict_types=1);

namespace OpenRest\Core\Middleware;

use OpenRest\Core\Exceptions\AppException;
use OpenRest\Core\Exceptions\ValidationException;
use Monolog\Logger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Throwable;

/**
 * Middleware global de tratamento de erros e exceções.
 *
 * Captura toda exceção lançada durante o ciclo de vida da requisição,
 * registra em log com contexto completo e retorna uma resposta JSON
 * padronizada com um trace_id único para rastreabilidade.
 *
 * Comportamento por tipo de exceção:
 *   - AppException e filhas → usa HTTP status e código definidos na exceção
 *   - ValidationException   → inclui erros por campo no envelope
 *   - Throwable (genérica)  → retorna 500 e loga stack trace completa
 *
 * O trace_id é incluído em toda resposta de erro. O usuário pode
 * informá-lo ao suporte para localizar o log exato do problema.
 *
 * Aplicação: global, deve ser o primeiro middleware adicionado
 * em public/api.php para capturar erros de toda a pilha.
 *
 * @package OpenRest\Core\Middleware
 */
class ErrorMiddleware implements MiddlewareInterface
{
    /**
     * @param Logger                   $logger          Instância do Monolog para gravação de logs.
     * @param ResponseFactoryInterface $responseFactory Factory PSR-17 para criar respostas.
     * @param bool                     $exibirDetalhes  Se true, expõe stack trace na resposta.
     *                                                  Ative apenas em APP_ENV=development.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $exibirDetalhes = false
    ) {}

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, Handler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->tratar($request, $e);
        }
    }

    /**
     * Processa a exceção capturada: loga e monta a resposta JSON.
     *
     * @param Request   $request Requisição que gerou o erro.
     * @param Throwable $e       Exceção capturada.
     *
     * @return Response
     */
    private function tratar(Request $request, Throwable $e): Response
    {
        $traceId = $this->gerarTraceId();
        $status  = $e instanceof AppException ? $e->getHttpStatus() : 500;

        $this->registrarLog($request, $e, $traceId, $status);

        $envelope = [
            'data'   => null,
            'errors' => $this->montarErros($e, $traceId),
        ];

        $resposta = $this->responseFactory->createResponse($status);
        $resposta->getBody()->write(
            json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $resposta->withHeader('Content-Type', 'application/json');
    }

    /**
     * Constrói o array de erros para o envelope de resposta.
     *
     * @param Throwable $e       Exceção capturada.
     * @param string    $traceId ID único gerado para este erro.
     *
     * @return array
     */
    private function montarErros(Throwable $e, string $traceId): array
    {
        // Erros de validação incluem detalhes por campo
        if ($e instanceof ValidationException) {
            return array_map(
                fn(array $erro) => array_merge($erro, [
                    'code'     => 'VALIDATION_ERROR',
                    'trace_id' => $traceId,
                ]),
                $e->getErros()
            );
        }

        $erro = [
            'code'     => $e instanceof AppException ? $e->getErrorCode() : 'INTERNAL_ERROR',
            'message'  => $e instanceof AppException ? $e->getMessage() : 'Erro interno no servidor.',
            'trace_id' => $traceId,
        ];

        // Em desenvolvimento, expõe detalhes técnicos para facilitar o debug
        if ($this->exibirDetalhes && !($e instanceof AppException)) {
            $erro['debug'] = [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        return [$erro];
    }

    /**
     * Registra a exceção no log com contexto completo da requisição.
     *
     * Erros 5xx são registrados como ERROR com stack trace.
     * Erros 4xx são registrados como WARNING (problema do cliente).
     *
     * @param Request   $request  Requisição original.
     * @param Throwable $e        Exceção capturada.
     * @param string    $traceId  ID único do erro.
     * @param int       $status   Código HTTP da resposta.
     *
     * @return void
     */
    private function registrarLog(Request $request, Throwable $e, string $traceId, int $status): void
    {
        $contexto = [
            'trace_id'  => $traceId,
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'request'   => [
                'method' => $request->getMethod(),
                'uri'    => (string) $request->getUri(),
                'body'   => (string) $request->getBody(),
            ],
        ];

        if ($status >= 500) {
            $contexto['stack_trace'] = $e->getTraceAsString();
            $this->logger->error($e->getMessage(), $contexto);
        } else {
            $this->logger->warning($e->getMessage(), $contexto);
        }
    }

    /**
     * Gera um ID único de 8 caracteres para identificar o erro no log.
     *
     * @return string Ex: "a3f7c2b1"
     */
    private function gerarTraceId(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
