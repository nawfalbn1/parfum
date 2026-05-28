<?php


if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
    session_start();
}


function requireLogin(string $loginUrl = '/public/login.php'): void
{
    if (empty($_SESSION['user_id'])) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$loginUrl}?redirect={$return}");
        exit;
    }
}


function requireAdmin(string $homeUrl = '/frontend/views/index.html'): void
{
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header("Location: {$homeUrl}");
        exit;
    }
}

function isLoggedIn(): bool  { return !empty($_SESSION['user_id']); }
function isAdmin(): bool     { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function currentUserId(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function currentSessionId(): string { return session_id(); }
