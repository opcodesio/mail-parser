<?php

namespace Opcodes\MailParser;

class Message
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
                $this->headers[$headerInProgress] .= "\n" . $line;
                $headerInProgress = str_ends_with($line, ';');
                continue;
            }

            if ($currentBodyHeaderInProgress) {
                $currentBodyHeaders[$currentBodyHeaderInProgress] .= "\n" . $line;
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
                $currentBodyHeaders[$matches['key']] = $matches['value'];

                // if the last character is a semicolon, then the header is continued on the next line
                if (str_ends_with($matches['value'], ';')) {
                    $currentBodyHeaderInProgress = $matches['key'];
                }

                continue;
            }

            if ($collectingBody) {
                $currentBody .= $line."\n";
                continue;
            }

            if (preg_match('/^Content-Type: multipart\/mixed; boundary=(?<boundary>.*)$/', $line, $matches)) {
                $this->headers['Content-Type'] = 'multipart/mixed; boundary='.$matches['boundary'];
                $this->boundary = trim($matches['boundary'], '"');
                continue;
            }

            if (preg_match('/^(?<key>[A-Za-z\-0-9]+): (?<value>.*)$/', $line, $matches)) {
                $this->headers[$matches['key']] = $matches['value'];

                // if the last character is a semicolon, then the header is continued on the next line
                if (str_ends_with($matches['value'], ';')) {
                    $headerInProgress = $matches['key'];
                }

                continue;
            }
        }
    }

    protected function addPart(string $currentBody, array $currentBodyHeaders): void
    {
        $this->parts[] = new MessagePart(trim($currentBody), $currentBodyHeaders);
    }
}
