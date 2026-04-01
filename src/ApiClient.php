<?php

declare(strict_types=1);

namespace OpenRest\Core;

use OpenRest\Core\Exceptions\ApiRequestException;

class ApiClient
{
    private string $baseUrl;
    private array $headers = [];
    private int $timeout = 30;
    private bool $verifySsl = true;
    private bool $throwOnError = false;
    private int $maxRedirects = 5;
    private int $maxBodySize = 10 * 1024 * 1024;

    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->headers['Content-Type'] = 'application/json';
        $this->headers['Accept'] = 'application/json';
    }

    public static function new(string $baseUrl = ''): self
    {
        return new self($baseUrl);
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        $clone = clone $this;
        $clone->headers['Authorization'] = "{$type} {$token}";

        return $clone;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $clone = clone $this;
        $clone->headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");

        return $clone;
    }

    public function withApiKey(string $key, string $headerName = 'X-API-Key'): self
    {
        $clone = clone $this;
        $clone->headers[$headerName] = $key;

        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function timeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = max(1, $seconds);

        return $clone;
    }

    public function withoutSslVerification(): self
    {
        $clone = clone $this;
        $clone->verifySsl = false;

        return $clone;
    }

    public function maxBodySize(int $bytes): self
    {
        $clone = clone $this;
        $clone->maxBodySize = max(1, $bytes);

        return $clone;
    }

    public function maxRedirects(int $max): self
    {
        $clone = clone $this;
        $clone->maxRedirects = max(0, $max);

        return $clone;
    }

    public function throw(): self
    {
        $clone = clone $this;
        $clone->throwOnError = true;

        return $clone;
    }

    public function get(string $uri, array $query = []): ApiResponse
    {
        return $this->request('GET', $uri, null, $query);
    }

    public function post(string $uri, ?array $body = null, array $query = []): ApiResponse
    {
        return $this->request('POST', $uri, $body, $query);
    }

    public function put(string $uri, ?array $body = null, array $query = []): ApiResponse
    {
        return $this->request('PUT', $uri, $body, $query);
    }

    public function patch(string $uri, ?array $body = null, array $query = []): ApiResponse
    {
        return $this->request('PATCH', $uri, $body, $query);
    }

    public function delete(string $uri, ?array $body = null, array $query = []): ApiResponse
    {
        return $this->request('DELETE', $uri, $body, $query);
    }

    public function request(string $method, string $uri, ?array $body = null, array $query = []): ApiResponse
    {
        $url = $this->buildUrl($uri, $query);
        $this->validateProtocol($url);

        $responseHeaders = [];
        $ch = $this->createHandle($method, $url, $body, $responseHeaders);

        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            throw ApiRequestException::connectionError(
                $method,
                $this->sanitizeUrl($url),
                $errno,
                $error
            );
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (strlen($responseBody) > $this->maxBodySize) {
            throw ApiRequestException::responseTooLarge(
                $method,
                $this->sanitizeUrl($url),
                strlen($responseBody),
                $this->maxBodySize
            );
        }

        $response = new ApiResponse(
            $statusCode,
            $responseBody,
            $responseHeaders,
            $this->sanitizeUrl($url),
            $method
        );

        if ($this->throwOnError && $response->failed()) {
            throw ApiRequestException::httpError(
                $statusCode,
                $method,
                $this->sanitizeUrl($url),
                $response
            );
        }

        return $response;
    }

    private function createHandle(string $method, string $url, ?array $body, array &$responseHeaders): \CurlHandle
    {
        $ch = curl_init();

        if ($ch === false) {
            throw ApiRequestException::connectionError($method, $url, 0, 'Falha ao inicializar cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_MAXREDIRS      => $this->maxRedirects,
            CURLOPT_FOLLOWLOCATION => $this->maxRedirects > 0,
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_HEADERFUNCTION => function ($_, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        return $ch;
    }

    private function buildUrl(string $uri, array $query = []): string
    {
        $url = $this->baseUrl . '/' . ltrim($uri, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private function validateProtocol(string $url): void
    {
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ApiRequestException(
                "Protocolo nao permitido na URL. Use apenas http ou https.",
                400,
                'INVALID_PROTOCOL'
            );
        }
    }

    private function sanitizeUrl(string $url): string
    {
        $parsed = parse_url($url);

        if (!$parsed) {
            return '[url-invalida]';
        }

        $safe = ($parsed['scheme'] ?? 'https') . '://';
        $safe .= $parsed['host'] ?? '';

        if (isset($parsed['port'])) {
            $safe .= ':' . $parsed['port'];
        }

        $path = $parsed['path'] ?? '/';
        $safe .= $path;

        return $safe;
    }

    private function buildHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return $headers;
    }
}
