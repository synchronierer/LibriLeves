<?php
// src/includes/security.php
// Sichere Session + CSRF + Flash-Messages, robust auch wenn bereits Output gesendet wurde.

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    // Nur Cookie-Parameter setzen, wenn noch keine Header gesendet wurden
    if (!headers_sent()) {
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,   // in Produktion hinter HTTPS auf true
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        } else {
            // Fallback ohne Array-Syntax (ältere PHP-Versionen)
            session_set_cookie_params(0, '/; samesite=Strict', '', $isHttps, true);
            // Hinweis: Bei sehr alten PHP-Versionen wird SameSite so evtl. ignoriert
        }
        session_start();
    } else {
        // Header wurden bereits gesendet: Session ohne Parameternachjustierung starten
        // Das unterdrückt Warnungen und funktioniert, wenn ein Session-Cookie schon existiert.
        @session_start();
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool {
    $expected = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $expected && hash_equals($expected, $token);
}

function require_admin(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: /admin/login.php");
        exit();
    }
}

// Flash-Messages (kurzlebige Popups nach Redirect)
function set_flash(string $type, string $message): void {
    if ($type === 'success') {
        $_SESSION['flash_success'] = $message;
    } else {
        $_SESSION['flash_error'] = $message;
    }
}
function flash_success(string $message): void { set_flash('success', $message); }
function flash_error(string $message): void   { set_flash('error', $message);  }
