<?php

namespace Opcodes\MailParser;

class MessagePart implements \JsonSerializable
{
    use HasHeaders;

    protected string $rawMessage;

    protected string $content;

    public function __construct(string $message)
    {
        $this->rawMessage = $message;

        $this->parse();
    }

    protected function parse(): void
    {
        // Split part into headers and content
        if (strpos($this->rawMessage, "\r\n\r\n") !== false) {
            [$headers, $content] = explode("\r\n\r\n", $this->rawMessage, 2);

            // Parse part headers
            $this->headers = Utils::parseHeaders($headers);
            $this->headers = Utils::decodeHeaders($this->headers);

            $this->content = trim($content);
        } else {
            // No headers, just content
            $this->content = trim($this->rawMessage);
        }
    }

    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    public function getContent(): string
    {
        if (strtolower($this->getHeader('Content-Transfer-Encoding', '')) === 'base64') {
            return Utils::normaliseLineEndings(base64_decode($this->content));
        }

        return Utils::normaliseLineEndings($this->content);
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
        return strlen($this->rawMessage);
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
