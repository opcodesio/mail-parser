<?php

namespace Opcodes\MailParser;

class Message implements \JsonSerializable
{
    use HasHeaders;

    protected string $rawMessage;

    protected string $boundary;

    /**
     * @var MessagePart[]
     */
    protected array $parts = [];

    public function __construct(string $message)
    {
        $this->rawMessage = Utils::cleanUntilFirstHeader($message);
        $this->rawMessage = Utils::normaliseLineEndings($this->rawMessage, true);

        $this->parse();
    }

    public static function fromString($message): self
    {
        return new self($message);
    }

    public static function fromFile($path): self
    {
        return new self(file_get_contents($path));
    }

    public function getBoundary(): ?string
    {
        return $this->boundary ?? null;
    }

    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    public function getId(): string
    {
        $header = $this->getHeader('Message-ID', '');

        return trim($header, '<>');
    }

    public function getSubject(): string
    {
        return $this->getHeader('Subject', '');
    }

    public function getFrom(): string
    {
        return $this->getHeader('From', '');
    }

    public function getTo(): string
    {
        return $this->getHeader('To', '');
    }

    public function getReplyTo(): string
    {
        return $this->getHeader('Reply-To', '');
    }

    public function getDate(): ?\DateTime
    {
        return \DateTime::createFromFormat(
            'D, d M Y H:i:s O',
            $this->getHeader('Date')
        ) ?: null;
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function getHtmlPart(): ?MessagePart
    {
        foreach ($this->getParts() as $part) {
            if ($part->isHtml()) {
                return $part;
            }
        }

        return null;
    }

    public function getTextPart(): ?MessagePart
    {
        foreach ($this->getParts() as $part) {
            if ($part->isText()) {
                return $part;
            }
        }

        return null;
    }

    /**
     * @return MessagePart[]
     */
    public function getAttachments(): array
    {
        return array_values(array_filter($this->parts, fn ($part) => $part->isAttachment()));
    }

    public function getSize(): int
    {
        return strlen($this->rawMessage);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'subject' => $this->getSubject(),
            'from' => $this->getFrom(),
            'to' => $this->getTo(),
            'reply_to' => $this->getReplyTo(),
            'date' => $this->getDate() ? $this->getDate()->format('c') : null,
            'headers' => $this->getHeaders(),
            'parts' => array_map(fn ($part) => $part->toArray(), $this->getParts()),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    protected function parse(): void
    {
        // Split email into headers and body
        [$rawHeaders, $body] = explode("\r\n\r\n", $this->rawMessage, 2);

        // Parse top-level headers
        $this->headers = Utils::parseHeaders($rawHeaders);
        $this->headers = Utils::decodeHeaders($this->headers);

        // Get boundary if this is a multipart email
        $contentType = $this->getHeader('Content-Type');
        if ($contentType && preg_match('/boundary="?([^";\r\n]+)"?/', $contentType, $matches)) {
            $this->boundary = $matches[1];
        }

        if (!isset($this->boundary) && str_contains($contentType ?? '', 'multipart/')) {
            // multipart email, perhaps the boundary is corrupted in the header.
            // Let's attempt to find a boundary in the body.
            if (preg_match("~^--(?<boundary>[0-9A-Za-z'()+_,-./:=?]{0,68}[0-9A-Za-z'()+_,-./=?])~", $body, $matches)) {
                $this->boundary = trim($matches['boundary']);
            }
        }

        // If no boundary, treat the entire body as a single part
        if (!isset($this->boundary)) {
            $part = $this->addPart($body ?? '');
            if ($contentType = $this->getHeader('Content-Type')) {
                $part->setHeader('Content-Type', $contentType);
            }
            if ($contentTransferEncoding = $this->getHeader('Content-Transfer-Encoding')) {
                $part->setHeader('Content-Transfer-Encoding', $contentTransferEncoding);
                $this->removeHeader('Content-Transfer-Encoding');
            }
            return;
        }

        // Split body into parts using boundary
        $parts = preg_split("/--" . preg_quote($this->boundary) . "(?:--|(?:\r\n|$))/", $body);

        // Process each part
        foreach ($parts as $rawPart) {
            if (empty(trim($rawPart))) continue;

            $this->addPart($rawPart);
        }
    }

    protected function addPart(string $rawMessage): MessagePart
    {
        $this->parts[] = $part = new MessagePart($rawMessage);

        return $part;
    }
}
