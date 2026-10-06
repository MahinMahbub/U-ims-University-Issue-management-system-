<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Sends email (built-in SMTP client, no libraries needed) and keeps a log of
 * every message so administrators can see what was sent, and what failed.
 */

function mail_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/mail.php';
        $local = __DIR__ . '/mail.local.php';
        if (is_file($local)) {
            $override = require $local;
            if (is_array($override)) {
                $cfg = array_merge($cfg, $override);
            }
        }
    }
    return $cfg;
}


/** Cuts text to N characters without needing the mbstring extension. */
function mail_cut(string $text, int $length): string
{
    return preg_match('/^.{0,' . $length . '}/su', $text, $m) ? $m[0] : substr($text, 0, $length);
}

/** The address mail is sent from ('' when none is usable). */
function mail_from_address(array $c): string
{
    foreach ([$c['from_email'] ?? '', $c['username'] ?? ''] as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return (string) $candidate;
        }
    }
    return '';
}

function mail_is_configured(): bool
{
    $c = mail_config();
    if (mail_from_address($c) === '') {
        return false;
    }
    return ($c['driver'] ?? 'smtp') === 'mail' || trim((string) ($c['host'] ?? '')) !== '';
}

/** One-line description of the current setup, for the admin screen. */
function mail_summary(): string
{
    $c = mail_config();
    if (!mail_is_configured()) {
        return '';
    }
    $from = mail_from_address($c);
    if (($c['driver'] ?? 'smtp') === 'mail') {
        return "PHP mail(), sending as $from";
    }
    $enc = $c['encryption'] === 'ssl' ? 'SSL' : ($c['encryption'] === 'tls' ? 'TLS' : 'no encryption');
    return "{$c['host']}:{$c['port']} ($enc), sending as $from";
}

/* ---------------------------------------------------------------------------
 * Message building
 * ------------------------------------------------------------------------- */

function mail_clean_header(string $text): string
{
    return trim((string) preg_replace('/[\r\n]+/', ' ', $text));
}

/** RFC 2047 encoding for non-ASCII header text (Bangla names, accents...). */
function mail_encode_header(string $text): string
{
    $text = mail_clean_header($text);
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    $words = [];
    $current = '';
    foreach ($chars as $ch) {
        if (strlen($current . $ch) > 42) {
            $words[] = $current;
            $current = '';
        }
        $current .= $ch;
    }
    if ($current !== '') {
        $words[] = $current;
    }
    return implode("\r\n ", array_map(function ($w) {
        return '=?UTF-8?B?' . base64_encode($w) . '?=';
    }, $words));
}

/** Returns the full message (headers, blank line, body) ready for SMTP DATA. */
function mail_build(string $fromEmail, string $fromName, string $to, string $subject, string $body): string
{
    $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
    $fromName = mail_clean_header($fromName);
    if ($fromName === '') {
        $from = $fromEmail;
    } elseif (preg_match('/^[\x20-\x7E]*$/', $fromName)) {
        $from = '"' . addcslashes($fromName, '"\\') . '" <' . $fromEmail . '>';
    } else {
        $from = mail_encode_header($fromName) . ' <' . $fromEmail . '>';
    }

    $headers = [
        'Date: ' . date('r'),
        'From: ' . $from,
        'To: ' . $to,
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $body = (string) preg_replace('/\r\n|\r|\n/', "\r\n", $body);
    return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
}

/* ---------------------------------------------------------------------------
 * SMTP client
 * ------------------------------------------------------------------------- */

/** Reads one (possibly multi-line) SMTP reply: [code, text]. */
function smtp_read($fp): array
{
    $text = '';
    $code = 0;
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        $code = (int) substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    if ($text === '') {
        return [0, 'No response from the mail server (timed out).'];
    }
    return [$code, trim($text)];
}

/** Sends one command and checks the reply code. Returns [ok, reply text]. */
function smtp_cmd($fp, string $command, array $okCodes): array
{
    if ($command !== '') {
        fwrite($fp, $command . "\r\n");
    }
    [$code, $text] = smtp_read($fp);
    return [in_array($code, $okCodes, true), $text];
}

/** Sends one message over SMTP. Returns [ok, error message]. */
function smtp_send(array $c, string $fromEmail, string $to, string $message): array
{
    $host = (string) $c['host'];
    $port = (int) $c['port'];
    $enc = (string) ($c['encryption'] ?? '');
    $timeout = max(3, (int) ($c['timeout'] ?? 15));
    $verify = !isset($c['verify_tls']) || (bool) $c['verify_tls'];

    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify,
    ]]);
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return [false, "Could not connect to $host:$port ($errstr)."];
    }
    stream_set_timeout($fp, $timeout);

    try {
        [$ok, $reply] = smtp_cmd($fp, '', [220]);
        if (!$ok) {
            return [false, "The mail server did not greet us: $reply"];
        }
        $name = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) gethostname()) ?: 'localhost';

        [$ok, $ehlo] = smtp_cmd($fp, "EHLO $name", [250]);
        if (!$ok) {
            return [false, "EHLO was refused: $ehlo"];
        }

        if ($enc === 'tls') {
            [$ok, $reply] = smtp_cmd($fp, 'STARTTLS', [220]);
            if (!$ok) {
                return [false, "The server does not support STARTTLS: $reply"];
            }
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return [false, 'Could not start an encrypted connection. If this is a test server with a self-signed certificate, set verify_tls to false.'];
            }
            [$ok, $ehlo] = smtp_cmd($fp, "EHLO $name", [250]);
            if (!$ok) {
                return [false, "EHLO was refused after STARTTLS: $ehlo"];
            }
        }

        $user = (string) ($c['username'] ?? '');
        if ($user !== '') {
            $pass = (string) ($c['password'] ?? '');
            if (stripos($ehlo, 'LOGIN') !== false || stripos($ehlo, 'AUTH') === false) {
                [$ok, $reply] = smtp_cmd($fp, 'AUTH LOGIN', [334]);
                if ($ok) {
                    [$ok, $reply] = smtp_cmd($fp, base64_encode($user), [334]);
                }
                if ($ok) {
                    [$ok, $reply] = smtp_cmd($fp, base64_encode($pass), [235]);
                }
            } else {
                [$ok, $reply] = smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0$user\0$pass"), [235]);
            }
            if (!$ok) {
                return [false, "Login was rejected by the mail server: $reply"];
            }
        }

        [$ok, $reply] = smtp_cmd($fp, "MAIL FROM:<$fromEmail>", [250]);
        if (!$ok) {
            return [false, "Sender was refused: $reply"];
        }
        [$ok, $reply] = smtp_cmd($fp, "RCPT TO:<$to>", [250, 251]);
        if (!$ok) {
            return [false, "Recipient was refused: $reply"];
        }
        [$ok, $reply] = smtp_cmd($fp, 'DATA', [354]);
        if (!$ok) {
            return [false, "The server would not accept the message: $reply"];
        }
        fwrite($fp, preg_replace('/^\./m', '..', $message) . "\r\n.\r\n");
        [$ok, $reply] = smtp_cmd($fp, '', [250]);
        if (!$ok) {
            return [false, "The message was rejected: $reply"];
        }
        smtp_cmd($fp, 'QUIT', [221]);
        return [true, ''];
    } finally {
        fclose($fp);
    }
}

/**
 * Sends one email. Never throws.
 * Returns ['status' => 'sent'|'failed'|'not_configured', 'error' => string].
 */
function send_mail(string $to, string $subject, string $body): array
{
    $to = mail_clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'failed', 'error' => 'That is not a valid email address.'];
    }
    if (!mail_is_configured()) {
        return ['status' => 'not_configured', 'error' => 'Email is not set up yet. Add your SMTP details in config/mail.local.php.'];
    }

    $c = mail_config();
    $from = mail_from_address($c);
    try {
        if (($c['driver'] ?? 'smtp') === 'mail') {
            $message = mail_build($from, (string) $c['from_name'], $to, $subject, $body);
            [$head, $rest] = explode("\r\n\r\n", $message, 2);
            // PHP's mail() adds To and Subject itself.
            $headerLines = array_filter(explode("\r\n", $head), function ($l) {
                return !preg_match('/^(To|Subject):/i', $l) && !preg_match('/^\s/', $l);
            });
            $ok = @mail($to, mail_encode_header($subject), $rest, implode("\r\n", $headerLines));
            return $ok ? ['status' => 'sent', 'error' => ''] : ['status' => 'failed', 'error' => 'PHP mail() could not hand the message to a mail server.'];
        }
        $message = mail_build($from, (string) $c['from_name'], $to, $subject, $body);
        [$ok, $error] = smtp_send($c, $from, $to, $message);
        return $ok ? ['status' => 'sent', 'error' => ''] : ['status' => 'failed', 'error' => $error];
    } catch (Throwable $e) {
        return ['status' => 'failed', 'error' => 'Unexpected error while sending: ' . $e->getMessage()];
    }
}

/* ---------------------------------------------------------------------------
 * Logging (this is what the admin sees)
 * ------------------------------------------------------------------------- */

/**
 * Sends an email and records the outcome for administrators.
 * Returns the send_mail() result.
 */
function send_and_log(PDO $pdo, string $to, ?string $toName, string $subject, string $body, ?int $issueId, ?int $staffId, ?int $sentBy, bool $seenWhenSent = false): array
{
    $subject = mail_clean_header($subject);
    if (text_length($subject) > 200) {
        $subject = mail_cut($subject, 197) . '...';
    }
    $result = send_mail($to, $subject, $body);
    try {
        $pdo->prepare(
            "INSERT INTO email_log(issue_id, staff_id, sent_by, to_email, to_name, subject, body, status, error, admin_seen)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $issueId, $staffId, $sentBy, mail_cut($to, 150), $toName, $subject, $body,
            $result['status'], $result['error'] !== '' ? mail_cut($result['error'], 500) : null,
            ($seenWhenSent && $result['status'] === 'sent') ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        // Logging must never break the page that triggered the email.
    }
    return $result;
}

/** Records a problem that stopped an email from even being tried (e.g. no address on file). */
function log_email_problem(PDO $pdo, string $subject, string $error, ?int $issueId, ?int $staffId, ?int $sentBy, string $toName): void
{
    try {
        $pdo->prepare(
            "INSERT INTO email_log(issue_id, staff_id, sent_by, to_email, to_name, subject, body, status, error)
             VALUES (?,?,?,?,?,?,?,'failed',?)"
        )->execute([$issueId, $staffId, $sentBy, '(no address)', $toName, mail_cut($subject, 200), '', $error]);
    } catch (PDOException $e) {
    }
}

/** Number of emails the administrators have not looked at yet. */
function unseen_email_count(PDO $pdo): int
{
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM email_log WHERE admin_seen = 0")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/* ---------------------------------------------------------------------------
 * Email texts
 * ------------------------------------------------------------------------- */

/** "You have been assigned" email. The reporter's identity is deliberately not included. */
function compose_job_email(array $issue, array $person, string $assignedBy, string $note): array
{
    $subject = '[U-IMS] New job: ' . $issue['title'] . ' (Issue #' . $issue['issue_id'] . ')';
    $lines = [
        'Hello ' . $person['name'] . ',',
        '',
        'You have been assigned as ' . $person['staff_role'] . ' for a job at United International University.',
        '',
        'Issue #' . $issue['issue_id'] . ': ' . $issue['title'],
        'Location: ' . $issue['location_name'],
        'Category: ' . $issue['category_name'],
        'Priority: ' . $issue['priority'],
        '',
        'Details:',
        $issue['description'],
    ];
    if (trim($note) !== '') {
        $lines[] = '';
        $lines[] = 'Note from ' . $assignedBy . ':';
        $lines[] = trim($note);
    }
    $lines[] = '';
    $lines[] = 'Please contact the maintenance office if you have any questions.';
    $lines[] = '';
    $lines[] = 'U-IMS, University Issue Management System';
    return [$subject, implode("\n", $lines)];
}

/** Free-form message from an authority about a job, with the issue summary attached. */
function compose_update_email(array $issue, array $person, string $sentBy, string $message): array
{
    $subject = '[U-IMS] Update on issue #' . $issue['issue_id'] . ': ' . $issue['title'];
    $lines = [
        'Hello ' . $person['name'] . ',',
        '',
        $sentBy . ' sent you a message about a job you are assigned to:',
        '',
        trim($message),
        '',
        '---',
        'Issue #' . $issue['issue_id'] . ': ' . $issue['title'],
        'Location: ' . $issue['location_name'],
        'Priority: ' . $issue['priority'],
        '',
        'U-IMS, University Issue Management System',
    ];
    return [$subject, implode("\n", $lines)];
}
