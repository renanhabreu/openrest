# OpenRest

Framework PHP minimalista para APIs RESTful.

Oferece roteamento automatico por convencao de diretorios, Active Record integrado, validacao declarativa, paginacao, cliente HTTP embutido e tratamento padronizado de erros com rastreabilidade via `trace_id`. Zero configuracao de rotas — crie um controller e as endpoints surgem sozinhas.

- **PHP 8.1+** · **PSR-12** · **GPL-3.0**
- Construido sobre [Slim 4](https://www.slimframework.com/)

---

## Instalacao

```bash
composer require renanhabreu/openrest
```

## Inicio rapido

### 1. Entry point (`public/index.php`)

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use OpenRest\Core\Router;
use OpenRest\Core\Middleware\ErrorMiddleware;
use OpenRest\Core\Middleware\CorsMiddleware;
use OpenRest\Core\Middleware\JsonMiddleware;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

$app = AppFactory::create();
$app->setBasePath('/');
$app->addRoutingMiddleware();

$app->add(new JsonMiddleware());
$app->add(new CorsMiddleware());
$app->add(new ErrorMiddleware(
    logger: $logger,
    responseFactory: new ResponseFactory(),
    exibirDetalhes: ($_ENV['APP_ENV'] ?? 'production') === 'development'
));

(new Router())->registrar($app);

$app->run();
```

### 2. Criar um controller

```php
<?php
// controllers/ProdutoController.php
declare(strict_types=1);

namespace App\Controllers;

use OpenRest\Core\Controller;
use OpenRest\Core\Database;
use OpenRest\Core\Validator;
use OpenRest\Core\Exceptions\NotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProdutoController extends Controller
{
    public function index(Request $request, Response $response, array $args): Response
    {
        $db        = Database::connection();
        $paginacao = $this->paginar($request);

        $dados = $db->query(
            'SELECT * FROM produtos ORDER BY nome LIMIT ? OFFSET ?',
            [$paginacao->limite(), $paginacao->offset()]
        );

        $total = (int) $db->queryOne('SELECT COUNT(*) as total FROM produtos')['total'];

        return $this->json($response, $dados, meta: $paginacao->meta($total));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $produto = Database::connection()->queryOne(
            'SELECT * FROM produtos WHERE id = ?',
            [(int) $args['id']]
        );

        if ($produto === null) {
            throw new NotFoundException('Produto nao encontrado.');
        }

        return $this->json($response, $produto);
    }

    public function post(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();

        Validator::verificar($data, [
            'nome'  => 'required|string|max:200',
            'preco' => 'required|numeric|min:0',
        ]);

        $db = Database::connection();
        $db->execute(
            'INSERT INTO produtos (nome, preco) VALUES (?, ?)',
            [$data['nome'], $data['preco']]
        );

        $novo = $db->queryOne(
            'SELECT * FROM produtos WHERE id = ?',
            [(int) $db->lastInsertId()]
        );

        return $this->json($response, $novo, status: 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $db = Database::connection();

        if ($db->queryOne('SELECT id FROM produtos WHERE id = ?', [$id]) === null) {
            throw new NotFoundException('Produto nao encontrado.');
        }

        $db->execute('DELETE FROM produtos WHERE id = ?', [$id]);

        return $this->json($response, ['mensagem' => "Produto {$id} removido."]);
    }
}
```

As rotas aparecem automaticamente:

| Verbo | Rota | Metodo |
|---|---|---|
| GET | `/v1/produto` | `index()` |
| GET | `/v1/produto/{id}` | `show()` |
| POST | `/v1/produto` | `post()` |
| DELETE | `/v1/produto/{id}` | `delete()` |

---

## Componentes

---

### Router — Roteamento automatico

Escaneia a pasta `controllers/`, detecta metodos implementados e registra as rotas no Slim. Nenhum arquivo de configuracao necessario.

**Conversao de nome para rota (PascalCase → kebab-case):**

| Controller | Rota base |
|---|---|
| `ProdutoController` | `/v1/produto` |
| `TipoContratoController` | `/v1/tipo-contrato` |
| `UserController` | `/v1/user` |

**Mapeamento de metodos:**

| Metodo do controller | Verbo HTTP | Rota |
|---|---|---|
| `index()` | GET | `/v1/{recurso}` |
| `show()` | GET | `/v1/{recurso}/{id}` |
| `post()` | POST | `/v1/{recurso}` |
| `put()` | PUT | `/v1/{recurso}/{id}` |
| `patch()` | PATCH | `/v1/{recurso}/{id}` |
| `delete()` | DELETE | `/v1/{recurso}/{id}` |

**Versionamento:**

```php
(new Router('/v1'))->registrar($app);
(new Router('/v2'))->registrar($app);
```

---

### Controller — Classe base

Herde de `OpenRest\Core\Controller` e sobrescreva apenas os metodos HTTP que o recurso precisa. Metodos nao implementados retornam `405 Method Not Allowed` automaticamente.

**Helpers:**

```php
// Resposta JSON de sucesso
return $this->json($response, $dados);
return $this->json($response, $dados, status: 201);
return $this->json($response, $dados, meta: $paginacao->meta($total));

// Resposta de erro
return $this->error($response, 'Mensagem', 400, 'CODIGO');

// Paginacao a partir da query string
$paginacao = $this->paginar($request);           // padrao: 20 por pagina
$paginacao = $this->paginar($request, 50);       // padrao: 50 por pagina
```

---

### Database — Conexao singleton

Conexao PDO unica por requisicao com suporte a SQLite, PostgreSQL e MySQL. Troca de driver via variavel `DB_DRIVER` no `.env` — sem alterar codigo.

```php
use OpenRest\Core\Database;

$db = Database::connection();

// SELECT — multiplas linhas
$produtos = $db->query('SELECT * FROM produtos WHERE status = ?', ['ativo']);

// SELECT — uma linha
$produto = $db->queryOne('SELECT * FROM produtos WHERE id = ?', [$id]);

// INSERT, UPDATE, DELETE
$linhas = $db->execute('UPDATE produtos SET preco = ? WHERE id = ?', [99.90, $id]);

// Ultimo ID inserido
$novoId = (int) $db->lastInsertId();

// Transacoes
$db->beginTransaction();
try {
    $db->execute('INSERT ...', [...]);
    $db->execute('INSERT ...', [...]);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    throw $e;
}
```

---

### Model — Active Record

Classe abstrata com operacoes CRUD. Crie um model por tabela herde de `OpenRest\Core\Model`:

```php
use OpenRest\Core\Model;

class ProdutoModel extends Model
{
    protected static string $table = 'produtos';

    public static function findByNome(string $nome): ?array
    {
        return static::findOne('nome = ?', [$nome]);
    }
}
```

**Metodos disponiveis:**

```php
ProdutoModel::findAll(?string $where, ?array $params, ?string $orderBy, ?int $limit, ?int $offset): array
ProdutoModel::findById(int $id): ?array
ProdutoModel::findOne(string $where, array $params): ?array
ProdutoModel::count(?string $where, ?array $params): int
ProdutoModel::create(array $data): int
ProdutoModel::update(int $id, array $data): int
ProdutoModel::delete(int $id): int
ProdutoModel::exists(int $id): bool
```

---

### Validator — Validacao declarativa

Valida dados e lanca `ValidationException` automaticamente (capturada pelo ErrorMiddleware como `422`):

```php
use OpenRest\Core\Validator;

Validator::verificar($data, [
    'nome'        => 'required|string|max:255',
    'valor'       => 'required|numeric|min:0',
    'data_inicio' => 'required|date',
    'status'      => 'required|in:ativo,inativo,suspenso',
    'email'       => 'email',
]);
```

**Regras:** `required` · `string` · `numeric` · `integer` · `boolean` · `email` · `date` · `min:N` · `max:N` · `in:a,b,c`

---

### Paginator — Paginacao

```php
$paginacao = $this->paginar($request);

$paginacao->limite();   // LIMIT para SQL
$paginacao->offset();   // OFFSET para SQL
$paginacao->meta(342);  // ['pagina' => 2, 'por_pagina' => 20, 'total' => 342, 'paginas' => 18]
```

Parametros via query string: `?pagina=2&por_pagina=20` (max: 100)

---

### ApiClient — Cliente HTTP RESTful

Cliente HTTP imutavel e fluente para consumir APIs externas. Zero dependencias alem do cURL nativo.

```php
use OpenRest\Core\ApiClient;

// GET
$resp = ApiClient::new('https://api.example.com')
    ->withToken($jwt)
    ->get('/users', ['page' => 1]);

$resp->status();       // 200
$resp->json();         // ['id' => 1, 'name' => 'Joao']
$resp->successful();   // true

// POST
$resp = ApiClient::new('https://api.example.com')
    ->withApiKey('minha-chave')
    ->post('/orders', ['product' => 'abc', 'qty' => 2]);

// PUT / PATCH / DELETE
$resp = ApiClient::new('https://api.example.com')
    ->withBasicAuth('user', 'pass')
    ->put('/orders/1', ['status' => 'shipped']);
```

**Configuracoes disponiveis:**

| Metodo | Descricao |
|---|---|
| `withToken(token)` | Authorization: Bearer |
| `withBasicAuth(user, pass)` | Authorization: Basic |
| `withApiKey(key)` | Header X-API-Key customizado |
| `withHeader(name, value)` | Header arbitrario |
| `withHeaders(array)` | Multiplos headers |
| `timeout(int)` | Timeout em segundos (default: 30) |
| `maxBodySize(int)` | Limite de response body (default: 10MB) |
| `maxRedirects(int)` | Max redirects (default: 5) |
| `withoutSslVerification()` | Desabilita SSL verify (dev only) |
| `throw()` | Lanca excecao em status >= 400 |

**ApiResponse — metodos:**

| Metodo | Retorno |
|---|---|
| `status()` | Codigo HTTP |
| `body()` | Body bruto |
| `json()` | Body decodificado ou `null` |
| `isJson()` | Se o body e JSON valido |
| `headers()` | Todos os headers |
| `header(name)` | Valor de um header (case-insensitive) |
| `successful()` | Status 2xx |
| `failed()` | Status fora de 2xx |
| `clientError()` | Status 4xx |
| `serverError()` | Status 5xx |

---

### Excecoes

Hierarquia de excecoes tipadas integrada ao ErrorMiddleware:

```
AppException                  (base)
├── NotFoundException          → 404
├── ValidationException        → 422
├── UnauthorizedException      → 401
├── ForbiddenException         → 403
├── ConflictException          → 409
└── ApiRequestException        → 502
```

**Uso:**

```php
use OpenRest\Core\Exceptions\NotFoundException;
use OpenRest\Core\Exceptions\ConflictException;

throw new NotFoundException('Produto nao encontrado.');
throw new ConflictException("Numero '{$numero}' ja existe.");
```

**ApiRequestException** (lançada pelo ApiClient):

```php
use OpenRest\Core\Exceptions\ApiRequestException;

try {
    ApiClient::new('https://api.example.com')
        ->withToken($token)
        ->throw()
        ->get('/users/1');
} catch (ApiRequestException $e) {
    $e->getHttpStatus();    // 502 ou status retornado
    $e->getErrorCode();     // API_TIMEOUT, API_SSL_ERROR, API_HTTP_ERROR...
    $e->getContext();       // ['method' => 'GET', 'url' => '...', 'curl_code' => 28]
    $e->getApiResponse();   // ?ApiResponse (null se falhou antes da resposta)
}
```

---

### Middlewares

| Middleware | Descricao |
|---|---|
| `ErrorMiddleware` | Captura excecoes, loga com trace_id, retorna JSON padronizado |
| `JsonMiddleware` | Parseia body JSON em `$request->getParsedBody()` |
| `CorsMiddleware` | Headers CORS em todas as respostas + preflight OPTIONS |
| `AuthMiddleware` | Validacao JWT Bearer, injeta payload em `$request->getAttribute('auth')` |

---

## Formato de resposta

Toda resposta segue o mesmo envelope:

```json
// Sucesso
{
    "data": { "id": 1, "nome": "Produto A" },
    "meta": { "timestamp": "2026-04-01T10:00:00-03:00" }
}

// Lista paginada
{
    "data": [ ... ],
    "meta": {
        "timestamp": "2026-04-01T10:00:00-03:00",
        "pagina": 2,
        "por_pagina": 20,
        "total": 342,
        "paginas": 18
    }
}

// Erro
{
    "data": null,
    "errors": [{
        "code": "NOT_FOUND",
        "message": "Produto nao encontrado.",
        "trace_id": "a3f7c2b1"
    }]
}
```

---

## Seguranca

O OpenRest inclui protecoes embutidas:

- **Cliente HTTP**: Validacao de protocolo (bloqueia SSRF), limite de response body, limite de redirects, sanitizacao de URL em mensagens de erro (nunca expoe credenciais)
- **Validacao**: SQL injection prevenido por prepared statements com placeholders `?`
- **Paginacao**: `por_pagina` limitado a 100 por padrao
- **Erros**: Em producao, erros internos nao expoe stack trace; em desenvolvimento, detalhes sao exibidos

---

## Estrutura de pastas do pacote

```
src/
├── ApiClient.php           ← cliente HTTP RESTful
├── ApiResponse.php         ← value object de resposta HTTP
├── Controller.php          ← classe base para controllers
├── Database.php            ← conexao singleton PDO
├── Model.php               ← active record base
├── Paginator.php           ← paginacao
├── Router.php              ← roteamento automatico
├── Validator.php            ← validacao declarativa
├── Exceptions/
│   ├── AppException.php
│   ├── ApiRequestException.php
│   ├── ConflictException.php
│   ├── ForbiddenException.php
│   ├── NotFoundException.php
│   ├── UnauthorizedException.php
│   └── ValidationException.php
└── Middleware/
    ├── AuthMiddleware.php
    ├── CorsMiddleware.php
    ├── ErrorMiddleware.php
    └── JsonMiddleware.php
```

---

## Licenca

[GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html)
