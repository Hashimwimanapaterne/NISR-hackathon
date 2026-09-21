<?php
/**
 * Shared security helpers: hardened sessions, CSRF tokens, and
 * standard security headers. Include this at the top of every
 * page that starts a session (admin panel) or renders a form.
 */

declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // only send cookie over HTTPS in production
        'httponly' => true,       // not accessible to JavaScript
        'samesite' => 'Lax',
    ]);

    session_name('umurima_sid');
    session_start();

    // Regenerate the session ID periodically to reduce fixation risk.
    if (empty($_SESSION['_started_at'])) {
        $_SESSION['_started_at'] = time();
        session_regenerate_id(true);
    } elseif (time() - $_SESSION['_started_at'] > 900) {
        $_SESSION['_started_at'] = time();
        session_regenerate_id(true);
    }
}

/** Send standard defensive headers. Call once near the top of each entry script. */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'");
}

/** Generate (or reuse) a CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden input carrying the CSRF token, for use inside <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** Verify a submitted CSRF token using a timing-safe comparison. Dies with 403 on failure. */
function verify_csrf(string $submittedToken): void
{
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !hash_equals($expected, $submittedToken)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

/** Require an authenticated admin session; redirect to login otherwise. */
function require_admin_login(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
