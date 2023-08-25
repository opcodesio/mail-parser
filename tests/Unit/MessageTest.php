<?php

namespace Opcodes\MailParser\Tests\Unit;

use Opcodes\MailParser\Message;

it('can parse a simple mail message', function () {
    date_default_timezone_set('UTC');
    $messageString = <<<EOF
From: sender@example.com
To: recipient@example.com
Cc: cc@example.com
Bcc: bcc@example.com
Subject: This is an email with common headers
Date: Thu, 24 Aug 2023 21:15:01 PST
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="----=_Part_1_1234567890"

------=_Part_1_1234567890
Content-Type: text/plain; charset="utf-8"

This is the text version of the email.

------=_Part_1_1234567890
Content-Type: text/html; charset="utf-8"

<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>

------=_Part_1_1234567890--
EOF;

    $message = new Message($messageString);

    expect($message->getHeaders())->toBe([
        'From' => 'sender@example.com',
        'To' => 'recipient@example.com',
        'Cc' => 'cc@example.com',
        'Bcc' => 'bcc@example.com',
        'Subject' => 'This is an email with common headers',
        'Date' => 'Thu, 24 Aug 2023 21:15:01 PST',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/mixed; boundary="----=_Part_1_1234567890"',
    ])
        ->and($message->getSubject())->toBe('This is an email with common headers')
        ->and($message->getFrom())->toBe('sender@example.com')
        ->and($message->getTo())->toBe('recipient@example.com')
        ->and($message->getDate())->toBeInstanceOf(\DateTime::class)
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2023-08-24 21:15:01');

    $parts = $message->getParts();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->getContentType())->toBe('text/plain; charset="utf-8"')
        ->and($parts[0]->getContent())->toBe('This is the text version of the email.')
        ->and($parts[1]->getContentType())->toBe('text/html; charset="utf-8"')
        ->and($parts[1]->getContent())->toBe(<<<EOF
<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>
EOF);

});

it('can parse a complex mail message', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/complex_email.eml');

    expect($message->getFrom())->toBe('Arunas Practice <no-reply@example.com>')
        ->and($message->getTo())->toBe('Arunas arukomp <arukomp@example.com>')
        ->and($message->getReplyTo())->toBe('Arunas Practice <arunas@example.com>')
        ->and($message->getSubject())->toBe('Appointment confirmation')
        ->and($message->getId())->toBe('fddff4779513441c3f0c1811193f5b12@example.com')
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2023-08-24 14:51:14')
        ->and($message->getBoundary())->toBe('lGiKDww4');

    $parts = $message->getParts();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->getContentType())->toBe('text/html; charset=utf-8')
        ->and($parts[0]->getHeaders())->toBe([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ])
        ->and($parts[1]->getContentType())->toBe('text/calendar; name=Appointment.ics')
        ->and($parts[1]->getHeaders())->toBe([
            'Content-Type' => 'text/calendar; name=Appointment.ics',
            'Content-Transfer-Encoding' => 'base64',
            'Content-Disposition' => 'attachment; name=Appointment.ics;
 filename=Appointment.ics',
        ]);
});

it('can get contents of an encoded part', function () {
    $messageString = <<<EOF
From: sender@example.com
To: recipient@example.com
Subject: This is an email with common headers
Date: Thu, 24 Aug 2023 21:15:01 PST
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="----=_Part_1_1234567890"

------=_Part_1_1234567890
Content-Type: text/html; charset="utf-8"

<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>

------=_Part_1_1234567890
Content-Type: text/plain; name=test.txt
Content-Transfer-Encoding: base64
Content-Disposition: attachment; name=test.txt;
 filename="test.txt"; name="test.txt"

VGhpcyBpcyBhIHRlc3Qgc3RyaW5n
------=_Part_1_1234567890--
EOF;

    $message = new Message($messageString);

    $parts = $message->getParts();

    expect($parts)->toHaveCount(2);

    $attachmentPart = $parts[1];

    expect($attachmentPart->getContent())->toBe('This is a test string')
        ->and($attachmentPart->isAttachment())->toBe(true)
        ->and($attachmentPart->getFilename())->toBe('test.txt');
});
