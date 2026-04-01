<?php

declare(strict_types=1);

namespace OpenRest\Core;

class ApiResponse
{
    private readonly ?array $decodedBody;
    private readonly bool $jsonValid;

    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers,
        private readonly string $url,
        private readonly string $method
    ) {
        $decoded = json_decode($body, true, 512);

        $this->jsonValid = json_last_error() === JSON_ERROR_NONE;
        $this->decodedBody = $this->jsonValid ? $decoded : null;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): ?array
    {
        return $this->decodedBody;
    }

    public function isJson(): bool
    {
        return $this->jsonValid;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return is_array($value) ? $value[0] : $value;
            }
        }

        return null;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function serverError(): bool
    {
        return $this->status >= 500;
    }
}
