<?php

namespace Opcodes\MailParser;

class Message implements \JsonSerializable
{
    protected string $message;

    protected string $boundary;

    protected array $headers = [];

    /**
     * @var MessagePart[]
     */
    protected array $parts = [];

    public function __construct(string $message)
    {
        $this->message = $message;

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

    public function getBoundary(): string
    {
        return $this->boundary;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $header, $default = null): ?string
    {
        return $this->headers[$header] ?? $default;
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
        foreach ($this->parts as $part) {
            if ($part->isHtml()) {
                return $part;
            }
        }

        return null;
    }

    public function getTextPart(): ?MessagePart
    {
        foreach ($this->parts as $part) {
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
        return array_filter($this->parts, fn ($part) => $part->isAttachment());
    }

    protected function parse()
    {
        // Parse the email message into headers and body
        $lines = explode("\n", $this->message);
        $headerInProgress = null;

        $collectingBody = false;
        $currentBody = '';
        $currentBodyHeaders = [];
        $currentBodyHeaderInProgress = null;

        foreach ($lines as $line) {
            if ($headerInProgress) {
                $this->headers[$headerInProgress] .= "\n" . rtrim($line);
                $headerInProgress = str_ends_with($line, ';');
                continue;
            }

            if ($currentBodyHeaderInProgress) {
                $currentBodyHeaders[$currentBodyHeaderInProgress] .= "\n" . rtrim($line);
                $currentBodyHeaderInProgress = str_ends_with($line, ';');
                continue;
            }

            if (isset($this->boundary) && $line === '--'.$this->boundary.'--') {
                // We've reached the end of the message
                $this->addPart($currentBody, $currentBodyHeaders);
                continue;
            }

            if (isset($this->boundary) && $line === '--'.$this->boundary) {
                if ($collectingBody) {
                    // We've reached the end of a part, add it and reset the variables
                    $this->addPart($currentBody, $currentBodyHeaders);
                }

                $collectingBody = true;
                $currentBody = '';
                $currentBodyHeaders = [];
                continue;
            }

            if ($collectingBody && preg_match('/^(?<key>[A-Za-z\-0-9]+): (?<value>.*)$/', $line, $matches)) {
                $currentBodyHeaders[$matches['key']] = rtrim($matches['value']);

                // if the last character is a semicolon, then the header is continued on the next line
                if (str_ends_with($currentBodyHeaders[$matches['key']], ';')) {
                    $currentBodyHeaderInProgress = $matches['key'];
                }

                continue;
            }

            if ($collectingBody) {
                $currentBody .= rtrim($line)."\n";
                continue;
            }

            if (preg_match('/^Content-Type: multipart\/mixed; boundary=(?<boundary>.*)$/', $line, $matches)) {
                $this->headers['Content-Type'] = 'multipart/mixed; boundary='.$matches['boundary'];
                $this->boundary = trim($matches['boundary'], '"');
                continue;
            }

            if (preg_match('/^(?<key>[A-Za-z\-0-9]+): (?<value>.*)$/', $line, $matches)) {
                $this->headers[$matches['key']] = rtrim($matches['value']);

                // if the last character is a semicolon, then the header is continued on the next line
                if (str_ends_with($this->headers[$matches['key']], ';')) {
                    $headerInProgress = rtrim($matches['key']);
                }

                continue;
            }
        }
    }

    protected function addPart(string $currentBody, array $currentBodyHeaders): void
    {
        $this->parts[] = new MessagePart(trim($currentBody), $currentBodyHeaders);
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
}
