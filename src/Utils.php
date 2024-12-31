<?php

namespace Opcodes\MailParser;

class Utils
{
    public static function cleanUntilFirstHeader(string $content): string
    {
        $headerPatterns = [
            // Any valid header format
            '/^[\w-]+: \S/m'
        ];

        foreach ($headerPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $content = substr($content, $matches[0][1]);
                break;
            }
        }

        return $content;
    }

    public static function normaliseLineEndings(string $content, bool $useCrLfEndings = false): string
    {
        $content = str_replace("\r\n", "\n", $content);

        if ($useCrLfEndings) {
            $content = str_replace("\n", "\r\n", $content);
        }

        return $content;
    }

    public static function parseHeaders(array|string $lines): array
    {
        if (is_string($lines)) {
            $lines = explode("\r\n", $lines);
        }

        $currentHeader = null;
        $headers = [];

        foreach ($lines as $line) {
            // If line starts with whitespace, it's a continuation of the previous header
            if (preg_match('/^[ \t]/', $line)) {
                if ($currentHeader) {
                    $headers[$currentHeader] .= ' ' . trim($line);
                }
                continue;
            }

            // New header
            if (preg_match('/^([\w-]+):\s*(.*)$/', $line, $matches)) {
                $currentHeader = $matches[1];
                $headers[$currentHeader] = $matches[2];
            }
        }

        return $headers;
    }

    public static function decodeHeaders(array $headers): array
    {
        $decodedHeaders = [];

        foreach ($headers as $key => $value) {
            if (str_starts_with($value, '=?')) {
                $decodedHeaders[$key] = self::decodeHeader($value);
            } else {
                $decodedHeaders[$key] = $value;
            }
        }

        return $decodedHeaders;
    }

    public static function decodeHeader(string $header): string
    {
        if (preg_match_all('/=\?([^?]+)\?([BQ])\?([^?]+)\?=/i', $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $encoding = $match[1];
                $type = $match[2];
                $data = $match[3];

                if ($type === 'B') {
                    $decoded = base64_decode($data);
                } else {
                    $decoded = quoted_printable_decode($data);
                }

                $header = str_replace($match[0], $decoded, $header);
            }
        }

        return $header;
    }
}
