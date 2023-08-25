<?php

namespace Opcodes\MailParser\Tests\Unit;

use Opcodes\MailParser\Message;

it('can parse a simple mail message', function () {
    $messageString = <<<EOF
From: Sender <no-reply@example.com>
To: Receiver <receiver@example.com>
Subject: Test Subject
Message-ID: <6e30b164904cf01158c7cc58f144b9ca@example.com>
MIME-Version: 1.0
Date: Fri, 25 Aug 2023 15:36:13 +0200
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: quoted-printable

Email content goes here.
EOF;

    $message = Message::fromString($messageString);

    expect($message->getFrom())->toBe('Sender <no-reply@example.com>')
        ->and($message->getTo())->toBe('Receiver <receiver@example.com>')
        ->and($message->getSubject())->toBe('Test Subject')
        ->and($message->getId())->toBe('6e30b164904cf01158c7cc58f144b9ca@example.com')
        ->and($message->getDate()?->format('Y-m-d H:i:s'))->toBe('2023-08-25 15:36:13')
        ->and($message->getContentType())->toBe('text/html; charset=utf-8')
        ->and($message->getHtmlPart()?->getContent())->toBe('Email content goes here.')
        ->and($message->getHtmlPart()?->getHeaders())->toBe([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ]);
});

it('can parse lowercase headers', function () {
    $messageString = <<<EOF
from: Sender <no-reply@example.com>
to: Receiver <receiver@example.com>
subject: Test Subject
message-id: <6e30b164904cf01158c7cc58f144b9ca@example.com>
mime-version: 1.0
date: Fri, 25 Aug 2023 15:36:13 +0200
content-type: text/html; charset=utf-8
content-transfer-encoding: quoted-printable

Email content goes here.
EOF;

    $message = Message::fromString($messageString);

    expect($message->getHeaders())->toBe([
        'from' => 'Sender <no-reply@example.com>',
        'to' => 'Receiver <receiver@example.com>',
        'subject' => 'Test Subject',
        'message-id' => '<6e30b164904cf01158c7cc58f144b9ca@example.com>',
        'mime-version' => '1.0',
        'date' => 'Fri, 25 Aug 2023 15:36:13 +0200',
        'content-type' => 'text/html; charset=utf-8',
    ])
        ->and($message->getFrom())->toBe('Sender <no-reply@example.com>')
        ->and($message->getHeader('Content-Type'))->toBe('text/html; charset=utf-8');
});

it('can parse a mail message with boundaries', function () {
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

    $htmlPart = $parts[0];

    expect($htmlPart->getContentType())->toBe('text/html; charset="utf-8"')
        ->and($htmlPart->isHtml())->toBe(true);

    $attachmentPart = $parts[1];

    expect($attachmentPart->getContent())->toBe('This is a test string')
        ->and($attachmentPart->isAttachment())->toBe(true)
        ->and($attachmentPart->getFilename())->toBe('test.txt');

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1)
        ->and($attachments)->toHaveKey(0);
});

it('skips initial content that is not part of the message', function () {
    $messageString = <<<EOF
This is some initial content that is not part of the message.

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

------=_Part_1_1234567890--
EOF;

    $message = Message::fromString($messageString);

    expect($message->getFrom())->toBe('sender@example.com')
        ->and($message->getHtmlPart()?->getContent())->toBe(<<<EOF
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

it('catches boundaries on the same line', function () {
    $messageString = <<<EOF
From: sender@example.com
To: recipient@example.com
Subject: This is an email with common headers
Date: Thu, 24 Aug 2023 21:15:01 PST
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="b552as-tfy"

--b552as-tfy
Content-Type: text/html; charset="utf-8"

<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>--b552as-tfy
Content-Type: text/plain; name=test.txt
Content-Transfer-Encoding: base64
Content-Disposition: attachment; name=test.txt;
 filename="test.txt"; name="test.txt"

VGhpcyBpcyBhIHRlc3Qgc3RyaW5n--b552as-tfy--
EOF;

    $message = Message::fromString($messageString);

    expect($message->getParts())->toHaveCount(2)
        ->and($message->getParts()[0]->getContent())->toBe(<<<EOF
<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>
EOF)
        ->and($message->getPArts()[1]->getContent())->toBe('This is a test string');
});

it('still parses with a broken boundary', function () {
    $messageString = <<<EOF
From: sender@example.com
To: recipient@example.com
Subject: This is an email with common headers
Date: Thu, 24 Aug 2023 21:15:01 PST
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary¨cQXEYh

--a8cQXEYh
Content-Type: text/html; charset="utf-8"

<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>--a8cQXEYh
Content-Type: text/plain; name=test.txt
Content-Transfer-Encoding: base64
Content-Disposition: attachment; name=test.txt;
 filename="test.txt"; name="test.txt"


--a8cQXEYh--
EOF;
    $messageString = str_replace("\n", "\r\n", $messageString);

    $message = Message::fromString($messageString);

    expect($message->getParts())->toHaveCount(2)
        ->and($message->getParts()[1]->isAttachment())->toBe(true)
        ->and($message->getParts()[1]->getContent())->toBeEmpty();
});
