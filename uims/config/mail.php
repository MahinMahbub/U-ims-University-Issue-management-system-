<?php
/*
 * Outgoing email settings (used when staff are assigned to a job).
 *
 * Do NOT put your real password in this file if the project is on GitHub.
 * Create config/mail.local.php instead (it is git-ignored) and copy only the
 * settings you want to change into it, in the same format:
 *
 *   <?php return ['host' => 'smtp.gmail.com', 'username' => 'you@gmail.com', 'password' => 'app-password', ...];
 *
 * Gmail:   host smtp.gmail.com, port 587, encryption 'tls', and an "App password"
 *          (Google Account > Security > 2-Step Verification > App passwords).
 * Outlook: host smtp.office365.com, port 587, encryption 'tls'.
 * Port 465 providers: use port 465 with encryption 'ssl'.
 */
return [
    'driver'     => 'smtp',        // 'smtp' (recommended) or 'mail' (PHP's built-in mail(), needs a configured server)
    'host'       => '',            // e.g. smtp.gmail.com. Leave empty and no email will be sent.
    'port'       => 587,
    'encryption' => 'tls',         // 'tls' = STARTTLS (port 587), 'ssl' = implicit TLS (port 465), '' = none
    'username'   => '',            // usually the full email address
    'password'   => '',
    'from_email' => '',            // falls back to username when that is an email address
    'from_name'  => 'U-IMS',
    'verify_tls' => true,          // set false only for a test server with a self-signed certificate
    'timeout'    => 15,            // seconds
];
