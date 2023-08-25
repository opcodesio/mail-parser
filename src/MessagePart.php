<?php

namespace Opcodes\MailParser;

class MessagePart implements \JsonSerializable
{
    protected string $content;

    protected array $headers;

    public function __construct(string $content, array $headers = [])
    {
        $this->content = $content;
        $this->headers = $headers;
    }

    public function getContentType(): string
    {
        return $this->headers['Content-Type'] ?? '';
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    public function getContent(): string
    {
        if (strtolower($this->getHeader('Content-Transfer-Encoding', '')) === 'base64') {
            return base64_decode($this->content);
        }

        return $this->content;
    }

    public function isHtml(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/html');
    }

    public function isText(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/plain');
    }

    public function isImage(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'image/');
    }

    public function isAttachment(): bool
    {
        return str_starts_with($this->getHeader('Content-Disposition', ''), 'attachment');
    }

    public function getFilename(): string
    {
        if (preg_match('/filename=([^;]+)/', $this->getHeader('Content-Disposition'), $matches)) {
            return trim($matches[1], '"');
        }

        if (preg_match('/name=([^;]+)/', $this->getContentType(), $matches)) {
            return trim($matches[1], '"');
        }

        return '';
    }

    public function getSize(): int
    {
        return strlen($this->getContent());
    }

    public function toArray(): array
    {
        return [
            'headers' => $this->getHeaders(),
            'content' => $this->getContent(),
            'filename' => $this->getFilename(),
            'size' => $this->getSize(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
