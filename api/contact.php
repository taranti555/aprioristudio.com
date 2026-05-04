<?php
/**
 * Apriori Studio — contact form endpoint.
 *
 * Validates submission, stores in MySQL, sends notification email via SMTP
 * relay (mail.pogosov.com on port 587 with STARTTLS).
 *
 * Configuration via environment variables (see .env.example):
 *   APRIORI_DB_HOST
 *   APRIORI_DB_NAME
 *   APRIORI_DB_USER
 *   APRIORI_DB_PASS
 *   APRIORI_SMTP_HOST
 *   APRIORI_SMTP_PORT
 *   APRIORI_SMTP_USER
 *   APRIORI_SMTP_PASS
 *   APRIORI_NOTIFY_TO   (comma-separated email recipients)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

function fail(int $status, string $msg): never {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function envv(string $k, ?string $default = null): string {
    $v = getenv($k);
    if ($v === false || $v === '') {
        if ($default === null) {
            fail(500, "Server is not configured (missing $k).");
        }
        return $default;
    }
    return $v;
}

// --- Read & basic shape check ---------------------------------------------
$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$honey   = (string)($_POST['website'] ?? '');
$ts      = (int)($_POST['ts'] ?? 0);

// Honeypot: bots fill the hidden field.
if ($honey !== '') {
    // Pretend success so bots don't iterate.
    echo json_encode(['ok' => true]);
    exit;
}

// Timestamp window: form must be loaded ≥ 3s ago and < 24h ago.
$now = time();
if ($ts < ($now - 86400) || $ts > ($now - 3)) {
    fail(400, 'Form session expired or submitted too quickly. Please reload and try again.');
}

if ($name === '' || mb_strlen($name) > 120) {
    fail(400, 'Please provide your name (under 120 characters).');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    fail(400, 'Please provide a valid email address.');
}
if (mb_strlen($subject) > 200) {
    fail(400, 'Subject is too long (max 200 characters).');
}
if (mb_strlen($message) < 20 || mb_strlen($message) > 5000) {
    fail(400, 'Please write a message between 20 and 5000 characters.');
}

// --- Capture context -------------------------------------------------------
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if ($ip !== null) {
    // First IP only (X-Forwarded-For can be a list).
    $ip = trim(explode(',', $ip)[0]);
    if (mb_strlen($ip) > 45) $ip = substr($ip, 0, 45);
}
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
if ($ua !== null && mb_strlen($ua) > 255) $ua = substr($ua, 0, 255);
$ref = $_SERVER['HTTP_REFERER'] ?? null;
if ($ref !== null && mb_strlen($ref) > 255) $ref = substr($ref, 0, 255);

// --- Persist to DB ---------------------------------------------------------
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            envv('APRIORI_DB_HOST', '127.0.0.1'),
            envv('APRIORI_DB_NAME', 'aprioristudio')
        ),
        envv('APRIORI_DB_USER'),
        envv('APRIORI_DB_PASS'),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $stmt = $pdo->prepare(
        'INSERT INTO contact_submissions (name, email, subject, message, ip, user_agent, referer)
         VALUES (:name, :email, :subject, :message, :ip, :ua, :ref)'
    );
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':subject' => $subject !== '' ? $subject : null,
        ':message' => $message,
        ':ip'      => $ip,
        ':ua'      => $ua,
        ':ref'     => $ref,
    ]);
    $submissionId = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('[apriori contact] DB error: ' . $e->getMessage());
    fail(500, 'Could not save your message. Please try again or email us directly.');
}

// --- Send notification email via SMTP ------------------------------------
$emailSent = false;
try {
    $smtpHost = envv('APRIORI_SMTP_HOST');
    $smtpPort = (int)envv('APRIORI_SMTP_PORT', '587');
    $smtpUser = envv('APRIORI_SMTP_USER');
    $smtpPass = envv('APRIORI_SMTP_PASS');
    $notifyTo = array_map('trim', explode(',', envv('APRIORI_NOTIFY_TO')));

    $body = "New enquiry via aprioristudio.com\n\n"
        . "Submission ID: $submissionId\n"
        . "Name:    $name\n"
        . "Email:   $email\n"
        . "Subject: " . ($subject !== '' ? $subject : '(none)') . "\n"
        . "IP:      " . ($ip ?? '-') . "\n"
        . "Referer: " . ($ref ?? '-') . "\n"
        . "UA:      " . ($ua ?? '-') . "\n"
        . "\n----- Message -----\n"
        . $message
        . "\n";

    $emailSent = smtp_send(
        $smtpHost, $smtpPort, $smtpUser, $smtpPass,
        $smtpUser, // From: same as auth user
        $notifyTo,
        $email,    // Reply-To: submitter
        sprintf('[Apriori] %s — %s', $name, $subject !== '' ? $subject : 'New enquiry'),
        $body
    );
} catch (Throwable $e) {
    error_log('[apriori contact] SMTP error: ' . $e->getMessage());
    $emailSent = false;
}

if ($emailSent) {
    try {
        $pdo->prepare('UPDATE contact_submissions SET email_sent = 1 WHERE id = ?')
            ->execute([$submissionId]);
    } catch (Throwable $e) {
        // non-fatal
    }
}

echo json_encode(['ok' => true]);

// --- Minimal SMTP client (STARTTLS + AUTH PLAIN) --------------------------
function smtp_send(
    string $host, int $port, string $user, string $pass,
    string $from, array $to, string $replyTo,
    string $subject, string $body
): bool {
    $errno = 0; $errstr = '';
    $fp = stream_socket_client(
        "tcp://$host:$port",
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT
    );
    if (!$fp) {
        throw new RuntimeException("SMTP connect: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);

    $expect = function (int $code) use ($fp) {
        $line = '';
        do {
            $r = fgets($fp, 1024);
            if ($r === false) {
                throw new RuntimeException('SMTP read error');
            }
            $line = $r;
        } while (isset($line[3]) && $line[3] === '-');
        $got = (int)substr($line, 0, 3);
        if ($got !== $code) {
            throw new RuntimeException("SMTP expected $code, got: $line");
        }
        return $line;
    };
    $cmd = function (string $line) use ($fp) {
        fwrite($fp, $line . "\r\n");
    };

    $expect(220);
    $cmd('EHLO aprioristudio.com'); $expect(250);
    $cmd('STARTTLS'); $expect(220);
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new RuntimeException('SMTP TLS handshake failed');
    }
    $cmd('EHLO aprioristudio.com'); $expect(250);
    $cmd('AUTH PLAIN ' . base64_encode("\0$user\0$pass")); $expect(235);
    $cmd("MAIL FROM:<$from>"); $expect(250);
    foreach ($to as $rcpt) {
        $cmd("RCPT TO:<$rcpt>"); $expect(250);
    }
    $cmd('DATA'); $expect(354);

    $headers = [
        'From: Apriori Studio <' . $from . '>',
        'To: ' . implode(', ', $to),
        'Reply-To: ' . $replyTo,
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: aprioristudio-com/1',
        'Date: ' . date('r'),
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    // RFC 5321 dot-stuffing
    $payload = preg_replace('/^\./m', '..', $payload);
    fwrite($fp, $payload . "\r\n.\r\n");
    $expect(250);
    $cmd('QUIT');
    fclose($fp);
    return true;
}
