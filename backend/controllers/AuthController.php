<?php

/**
 * Auth Controller – registration, login, logout, session management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Cart.php';

class AuthController
{
    private User $user;
    private Cart $cart;

    public function __construct()
    {
        $this->user = new User();
        $this->cart = new Cart();
        $this->startSession();
    }

    // ───────────────────────── REGISTER ─────────────────────────

    public function register(array $input): array
    {
        $errors = $this->validateRegistration($input);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $email = trim($input['email']);

        if ($this->user->emailExists($email)) {
            return [
                'success' => false,
                'errors' => ['email' => 'Cet email est déjà utilisé.']
            ];
        }

        $userId = $this->user->create($input);

        // auto login after register
        return $this->login([
            'email' => $input['email'],
            'password' => $input['password']
        ]);
    }

    // ───────────────────────── LOGIN ─────────────────────────

    public function login(array $input): array
    {
        $errors = [];

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email))    $errors['email'] = 'Email requis.';
        if (empty($password)) $errors['password'] = 'Mot de passe requis.';

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $userData = $this->user->findByEmail($email);

        if (
            !$userData ||
            !$this->user->verifyPassword($password, $userData['password'])
        ) {
            return [
                'success' => false,
                'errors' => ['general' => 'Email ou mot de passe incorrect.']
            ];
        }

        // IMPORTANT: regenerate session BEFORE storing data
        session_regenerate_id(true);

        $_SESSION['user_id']   = $userData['id'];
        $_SESSION['user_name'] = $userData['name'];
        $_SESSION['user_role'] = $userData['role'];

        // merge guest cart
        $sessionId = session_id();
        $this->cart->mergeGuestCart($sessionId, (int)$userData['id']);

        return [
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => [
                'id'   => $userData['id'],
                'name' => $userData['name'],
                'role' => $userData['role'],
            ]
        ];
    }

    // ───────────────────────── LOGOUT ─────────────────────────

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // ───────────────────────── SESSION HELPERS ─────────────────────────

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public function isAdmin(): bool
    {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }

    public function currentUserId(): ?int
    {
        return $this->isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/backend/views/auth/login.php');
            exit;
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();

        if (!$this->isAdmin()) {
            header('Location: ' . APP_URL);
            exit;
        }
    }

    // ───────────────────────── VALIDATION ─────────────────────────

    private function validateRegistration(array $input): array
    {
        $errors = [];

        if (empty($input['name']) || strlen(trim($input['name'])) < 2) {
            $errors['name'] = 'Nom requis (min 2 caractères).';
        }

        if (
            empty($input['email']) ||
            !filter_var($input['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $errors['email'] = 'Email invalide.';
        }

        if (empty($input['password']) || strlen($input['password']) < 8) {
            $errors['password'] = 'Mot de passe min 8 caractères.';
        }

        if (($input['password'] ?? '') !== ($input['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        return $errors;
    }

    // ───────────────────────── SESSION START ─────────────────────────

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

    // ───────────────────────── API CHECK ─────────────────────────

    private function isApiRequest(): bool
    {
        return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}