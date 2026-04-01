<?php

declare(strict_types=1);

namespace OpenRest\Core;

/**
 * Gerenciador de paginação.
 *
 * Calcula offset, limite e metadados de paginação para uso em queries
 * SQL e no envelope de resposta JSON. Instanciado pelo método
 * paginar() da classe Controller base.
 *
 * Uso no controller:
 *   $paginacao = $this->paginar($request);
 *
 *   $dados = Database::connection()->query(
 *       'SELECT * FROM contratos LIMIT ? OFFSET ?',
 *       [$paginacao->limite(), $paginacao->offset()]
 *   );
 *
 *   $total = (int) Database::connection()
 *       ->queryOne('SELECT COUNT(*) as total FROM contratos')['total'];
 *
 *   return $this->json($response, $dados, meta: $paginacao->meta($total));
 *
 * Parâmetros aceitos via query string:
 *   ?pagina=2&por_pagina=20
 *
 * @package OpenRest\Core
 */
class Paginator
{
    /**
     * @param int $pagina    Número da página atual (base 1).
     * @param int $porPagina Quantidade de itens por página.
     */
    public function __construct(
        private readonly int $pagina,
        private readonly int $porPagina
    ) {}

    /**
     * Retorna o número de registros a pular (OFFSET para SQL).
     *
     * @return int
     */
    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    /**
     * Retorna o número máximo de registros a buscar (LIMIT para SQL).
     *
     * @return int
     */
    public function limite(): int
    {
        return $this->porPagina;
    }

    /**
     * Gera o array de metadados de paginação para o envelope de resposta.
     *
     * @param int $total Total de registros existentes (sem paginação).
     *
     * @return array{pagina: int, por_pagina: int, total: int, paginas: int}
     */
    public function meta(int $total): array
    {
        return [
            'pagina'     => $this->pagina,
            'por_pagina' => $this->porPagina,
            'total'      => $total,
            'paginas'    => (int) ceil($total / $this->porPagina),
        ];
    }
}
