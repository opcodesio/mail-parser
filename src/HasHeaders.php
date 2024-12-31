<?php

namespace Opcodes\MailParser;

trait HasHeaders
{
    protected array $headers = [];

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $header, $default = null): mixed
    {
        $header = strtolower($header);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $header) {
                return $value;
            }
        }

        return $default;
    }

    public function setHeader(string $header, $value): void
    {
        $this->headers[$header] = Utils::decodeHeader($value);
    }

    public function removeHeader(string $header): void
    {
        $header = strtolower($header);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $header) {
                unset($this->headers[$key]);
            }
        }
    }
}
