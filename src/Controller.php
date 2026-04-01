<?php

declare(strict_types=1);

namespace OpenRest\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Classe base para todos os controllers da API.
 *
 * Fornece métodos utilitários para formatação de resposta JSON padronizada
 * e paginação. Métodos HTTP não sobrescritos pelo controller filho
 * retornam 405 Method Not Allowed automaticamente — sem código extra.
 *
 * Convenção de métodos × rotas (registradas automaticamente pelo Router):
 *   index()  → GET    /v1/{recurso}
 *   show()   → GET    /v1/{recurso}/{id}
 *   post()   → POST   /v1/{recurso}
 *   put()    → PUT    /v1/{recurso}/{id}
 *   patch()  → PATCH  /v1/{recurso}/{id}
 *   delete() → DELETE /v1/{recurso}/{id}
 *
 * Para criar um novo recurso, crie um controller em controllers/ e
 * sobrescreva apenas os métodos que o recurso suporta.
 *
 * @package OpenRest\Core
 */
abstract class Controller
{
    // -------------------------------------------------------------------------
    // Métodos HTTP — sobrescreva apenas os que o recurso utiliza.
    // Métodos não sobrescritos retornam 405 automaticamente.
    // -------------------------------------------------------------------------

    /**
     * Responde a GET /v1/{recurso} — listagem de registros.
     *
     * Use $this->paginar($request) para aplicar paginação à query.
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        return $this->methodNotAllowed($response);
    }

    /**
     * Responde a GET /v1/{recurso}/{id} — detalhe de um registro.
     *
     * O ID está disponível em $args['id'].
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        return $this->methodNotAllowed($response);
    }

    /**
     * Responde a POST /v1/{recurso} — criação de um novo registro.
     *
     * Os dados do body estão disponíveis via $request->getParsedBody().
     */
    public function post(Request $request, Response $response, array $args): Response
    {
        return $this->methodNotAllowed($response);
    }

    /**
     * Responde a PUT /v1/{recurso}/{id} — substituição completa de um registro.
     *
     * O ID está disponível em $args['id'].
     */
    public function put(Request $request, Response $response, array $args): Response
    {
        return $this->methodNotAllowed($response);
    }

    /**
     * Responde a PATCH /v1/{recurso}/{id} — atualização parcial de um registro.
     *
     * O ID está disponível em $args['id'].
     */
    public function patch(Request $request, Response $response, array $args): Response
    {
        return $this->methodNotAllowed($response);
    }

    /**
     * Responde a DELETE /v1/{recurso}/{id} — remoção de um registro.
     *
     * O ID está disponível em $args['id'].
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->methodNotAllowed($response);
    }

    // -------------------------------------------------------------------------
    // Helpers de resposta — use estes métodos em todos os controllers.
    // -------------------------------------------------------------------------

    /**
     * Retorna uma resposta JSON de sucesso com envelope padronizado.
     *
     * Formato da resposta:
     *   { "data": ..., "meta": { "timestamp": "...", ...meta_extra } }
     *
     * @param Response $response Objeto de resposta PSR-7.
     * @param mixed    $data     Dados a serem retornados no campo "data".
     * @param int      $status   Código HTTP (padrão: 200).
     * @param array    $meta     Metadados adicionais (ex: dados de paginação).
     *
     * @return Response
     */
    protected function json(
        Response $response,
        mixed $data,
        int $status = 200,
        array $meta = []
    ): Response {
        $envelope = [
            'data' => $data,
            'meta' => array_merge(['timestamp' => date('c')], $meta),
        ];

        $response->getBody()->write(
            json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Retorna uma resposta JSON de erro padronizada.
     *
     * Formato da resposta:
     *   { "data": null, "errors": [{ "code": "...", "message": "..." }] }
     *
     * @param Response $response Objeto de resposta PSR-7.
     * @param string   $message  Mensagem de erro legível.
     * @param int      $status   Código HTTP do erro.
     * @param string   $code     Código de erro interno (ex: "NOT_FOUND").
     *
     * @return Response
     */
    protected function error(
        Response $response,
        string $message,
        int $status,
        string $code = 'ERROR'
    ): Response {
        $envelope = [
            'data'   => null,
            'errors' => [
                ['code' => $code, 'message' => $message],
            ],
        ];

        $response->getBody()->write(
            json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Cria um Paginator a partir dos parâmetros da query string da requisição.
     *
     * Parâmetros aceitos via URL:
     *   ?pagina=2&por_pagina=20
     *
     * Limites aplicados automaticamente:
     *   - pagina    → mínimo 1
     *   - por_pagina → mínimo 1, máximo 100
     *
     * @param Request $request   Objeto de requisição PSR-7.
     * @param int     $porPagina Itens por página padrão (usado se não informado na URL).
     *
     * @return Paginator
     */
    protected function paginar(Request $request, int $porPagina = 20): Paginator
    {
        $params  = $request->getQueryParams();
        $pagina  = max(1, (int) ($params['pagina'] ?? 1));
        $limite  = max(1, min(100, (int) ($params['por_pagina'] ?? $porPagina)));

        return new Paginator($pagina, $limite);
    }

    /**
     * Retorna 405 Method Not Allowed para métodos não implementados.
     *
     * @param Response $response Objeto de resposta PSR-7.
     *
     * @return Response
     */
    private function methodNotAllowed(Response $response): Response
    {
        return $this->error(
            $response,
            'Método HTTP não permitido para este recurso.',
            405,
            'METHOD_NOT_ALLOWED'
        );
    }
}
