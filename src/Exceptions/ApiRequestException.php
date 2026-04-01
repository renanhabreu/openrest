<?php

declare(strict_types=1);

namespace OpenRest\Core\Exceptions;

use OpenRest\Core\ApiResponse;

class ApiRequestException extends AppException
{
    private ?ApiResponse $apiResponse;
    private array $context = [];

    public function __construct(
        string $message,
        int $httpStatus = 502,
        string $errorCode = 'API_REQUEST_ERROR',
        ?ApiResponse $response = null,
        array $context = []
    ) {
        parent::__construct($message, $httpStatus, $errorCode);
        $this->apiResponse = $response;
        $this->context = $context;
    }

    public function getApiResponse(): ?ApiResponse
    {
        return $this->apiResponse;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function connectionError(
        string $method,
        string $url,
        int $curlCode,
        string $curlMessage
    ): self {
        $category = match (true) {
            $curlCode === CURLE_OPERATION_TIMEDOUT => 'TIMEOUT',
            $curlCode === CURLE_SSL_CONNECT_ERROR   => 'SSL_ERROR',
            $curlCode === CURLE_COULDNT_RESOLVE_HOST => 'DNS_ERROR',
            $curlCode === CURLE_COULDNT_CONNECT      => 'CONNECTION_REFUSED',
            default                                   => 'CONNECTION_ERROR',
        };

        return new self(
            "Falha de conexao: {$method} {$url} — {$category}",
            502,
            'API_' . $category,
            null,
            [
                'method'    => $method,
                'url'       => $url,
                'curl_code' => $curlCode,
                'category'  => $category,
            ]
        );
    }

    public static function httpError(
        int $statusCode,
        string $method,
        string $url,
        ApiResponse $response
    ): self {
        return new self(
            "API retornou HTTP {$statusCode} para {$method} {$url}",
            $statusCode,
            'API_HTTP_ERROR',
            $response,
            [
                'status' => $statusCode,
                'method' => $method,
                'url'    => $url,
            ]
        );
    }

    public static function responseTooLarge(
        string $method,
        string $url,
        int $bytes,
        int $maxBytes
    ): self {
        return new self(
            "Resposta excede o limite de {$maxBytes} bytes para {$method} {$url}",
            502,
            'API_RESPONSE_TOO_LARGE',
            null,
            [
                'method'     => $method,
                'url'        => $url,
                'body_size'  => $bytes,
                'max_bytes'  => $maxBytes,
            ]
        );
    }
}
