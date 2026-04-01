<?php

declare(strict_types=1);

namespace OpenRest\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Slim\App;

/**
 * Roteador automático baseado na estrutura de pastas.
 *
 * Escaneia a pasta controllers/ e registra as rotas no Slim
 * de acordo com os métodos implementados em cada controller.
 * Nenhuma configuração manual de rotas é necessária.
 *
 * Convenção de mapeamento (métodos × rotas):
 *   index()  → GET    /v1/{recurso}
 *   show()   → GET    /v1/{recurso}/{id}
 *   post()   → POST   /v1/{recurso}
 *   put()    → PUT    /v1/{recurso}/{id}
 *   patch()  → PATCH  /v1/{recurso}/{id}
 *   delete() → DELETE /v1/{recurso}/{id}
 *
 * Somente métodos definidos no próprio controller são registrados.
 * Métodos herdados da classe Controller base são ignorados.
 *
 * Conversão de nome de classe para rota:
 *   ContratoController     → /contrato
 *   TipoContratoController → /tipo-contrato
 *   UserController         → /user
 *
 * @package OpenRest\Core
 */
class Router
{
    /** @var string Prefixo de versão aplicado a todas as rotas. */
    private string $prefixo;

    /** @var string Caminho absoluto para a pasta controllers/. */
    private string $pastaControllers;

    /**
     * @param string $prefixo          Prefixo de versão (ex: '/v1').
     * @param string $pastaControllers Caminho absoluto para a pasta controllers/.
     *                                 Se omitido, usa o padrão relativo ao projeto.
     */
    public function __construct(
        string $prefixo = '/v1',
        string $pastaControllers = ''
    ) {
        $this->prefixo          = rtrim($prefixo, '/');
        $this->pastaControllers = $pastaControllers ?: dirname(__DIR__) . '/controllers';
    }

    /**
     * Registra todas as rotas descobertas no Slim.
     *
     * @param App $app Instância do Slim.
     *
     * @return void
     */
    public function registrar(App $app): void
    {
        foreach ($this->descobrirControllers() as $classe => $recurso) {
            $this->registrarRotas($app, $classe, $recurso);
        }
    }

    /**
     * Escaneia a pasta controllers/ e retorna um mapa [ClasseCompleta => /recurso].
     *
     * @return array<string, string>
     */
    private function descobrirControllers(): array
    {
        $controllers = [];
        $iterador    = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pastaControllers)
        );

        foreach ($iterador as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $conteudo = file_get_contents($arquivo->getPathname());

            preg_match('/namespace\s+([^;]+)/m', $conteudo, $ns);
            preg_match('/class\s+(\w+)/m', $conteudo, $cl);

            if (empty($cl[1]) || !str_ends_with($cl[1], 'Controller')) {
                continue;
            }

            $namespace = isset($ns[1]) ? trim($ns[1]) . '\\' : '';
            $classe    = $namespace . $cl[1];
            $recurso   = $this->classeParaRota($cl[1]);

            $controllers[$classe] = $recurso;
        }

        return $controllers;
    }

    /**
     * Registra as rotas de um controller no Slim, verificando quais
     * métodos foram implementados pelo próprio controller (não herdados).
     *
     * @param App    $app     Instância do Slim.
     * @param string $classe  Namespace completo da classe.
     * @param string $recurso Segmento de rota (ex: '/contrato').
     *
     * @return void
     */
    private function registrarRotas(App $app, string $classe, string $recurso): void
    {
        $base   = $this->prefixo . $recurso;
        $baseId = $base . '/{id}';

        if ($this->implementa($classe, 'index'))  $app->get($base,    [$classe, 'index']);
        if ($this->implementa($classe, 'show'))   $app->get($baseId,  [$classe, 'show']);
        if ($this->implementa($classe, 'post'))   $app->post($base,   [$classe, 'post']);
        if ($this->implementa($classe, 'put'))    $app->put($baseId,  [$classe, 'put']);
        if ($this->implementa($classe, 'patch'))  $app->patch($baseId,[$classe, 'patch']);
        if ($this->implementa($classe, 'delete')) $app->delete($baseId,[$classe, 'delete']);
    }

    /**
     * Verifica se o método foi implementado pelo próprio controller
     * (e não apenas herdado da classe Controller base).
     *
     * @param string $classe  Namespace completo da classe.
     * @param string $metodo  Nome do método a verificar.
     *
     * @return bool
     */
    private function implementa(string $classe, string $metodo): bool
    {
        if (!class_exists($classe)) {
            return false;
        }

        $ref = new ReflectionClass($classe);

        return $ref->hasMethod($metodo)
            && $ref->getMethod($metodo)->getDeclaringClass()->getName() === $classe;
    }

    /**
     * Converte o nome da classe controller em segmento de rota kebab-case.
     *
     * Exemplos:
     *   ContratoController     → /contrato
     *   TipoContratoController → /tipo-contrato
     *   UserController         → /user
     *
     * @param string $classe Nome simples da classe (sem namespace).
     *
     * @return string Segmento de rota em kebab-case precedido de '/'.
     */
    private function classeParaRota(string $classe): string
    {
        $nome  = str_replace('Controller', '', $classe);
        $kebab = preg_replace('/([A-Z])/', '-$1', lcfirst($nome));

        return '/' . strtolower(ltrim($kebab, '-'));
    }
}
