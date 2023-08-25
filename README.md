<div align="center">
    <p>
        <h1>Mail Parser for PHP<br/>Simple, fast, no extensions required</h1>
    </p>
</div>

<p align="center">
    <a href="#features">Features</a> |
    <a href="#installation">Installation</a> |
    <a href="#credits">Credits</a>
</p>

<p align="center">
<a href="https://packagist.org/packages/opcodesio/mail-parser"><img src="https://img.shields.io/packagist/v/opcodesio/mail-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/opcodesio/mail-parser"><img src="https://img.shields.io/packagist/dm/opcodesio/mail-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/opcodesio/mail-parser"><img src="https://img.shields.io/packagist/php-v/opcodesio/mail-parser.svg?style=flat-square" alt="PHP from Packagist"></a>
</p>

## Features

[OPcodes's](https://www.opcodes.io/) **Mail Parser** has a very simple API to parse emails and their MIME contents. Unlike many other parsers out there, this package does not require the [mailparse](https://www.php.net/manual/en/book.mailparse.php) PHP extension.

Has not been fully tested against RFC 5322.

## Get Started

### Requirements

- **PHP 8.0+**

### Installation

To install the package via composer, Run:

```bash
composer require opcodesio/mail-parser
```

### Usage

```php
use Opcodes\MailParser\Message;

// Parse a message from a string
$message = Message::fromString('...');
// Or from a file location (accessible with file_get_contents())
$message = Message::fromFile('/path/to/email.eml');

$message->getHeaders();                 // get all headers
$message->getHeader('Content-Type');    // 'multipart/mixed; boundary="----=_Part_1_1234567890"'
$message->getFrom();                    // 'Arunas <arunas@example.com>
$message->getTo();                      // 'John Doe <johndoe@example.com>
$message->getSubject();                 // 'Subject line'
$message->getDate();                    // DateTime object when the email was sent
$message->getSize();                    // Email size in bytes

$message->getParts();       // Returns an array of \Opcodes\MailParser\MessagePart, which can be html parts, text parts, attachments, etc.
$message->getHtmlPart();    // Returns the \Opcodes\MailParser\MessagePart containing the HTML body
$message->getTextPart();    // Returns the \Opcodes\MailParser\MessagePart containing the Text body
$message->getAttachments(); // Returns an array of \Opcodes\MailParser\MessagePart that represent attachments

$messagePart = $message->getParts()[0];

$messagePart->getHeaders();                 // array of all headers for this message part
$messagePart->getHeader('Content-Type');    // value of a particular header
$messagePart->getContentType();             // 'text/html; charset="utf-8"'
$messagePart->getContent();                 // '<html><body>....'
$messagePart->getSize();                    // 312
$messagePart->getFilename();                // name of the file, in case this is an attachment part
```

## Contributing

A guide for contributing is in progress...

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Arunas Skirius](https://github.com/arukompas)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
