<?php
// ── Auth API: register / login / logout / me ──────────────────────────────
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 604800, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? '';

// Send JSON and stop
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDB();

// ── REGISTER ─────────────────────────────────────────────────────────────
if ($action === 'register') {
    $name     = trim($body['name']     ?? '');
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password']          ?? '';
    $confirm  = $body['password_confirm']  ?? '';
    $phone    = $body['phone']             ?? null;

    $errors = [];
    if (strlen($name) < 2)                          $errors['name']             = 'Nom requis (min 2 caractères).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']            = 'Email invalide.';
    if (strlen($password) < 8)                      $errors['password']         = 'Mot de passe min 8 caractères.';
    if ($password !== $confirm)                     $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';

    if ($errors) respond(['success' => false, 'errors' => $errors], 422);

    // Check email not taken
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) respond(['success' => false, 'errors' => ['email' => 'Cet email est déjà utilisé.']], 422);

    // Insert user
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)")
       ->execute([$name, $email, $hash, $phone]);
    $userId = (int) $db->lastInsertId();

    // Auto-login
    $oldSession = session_id();
    session_regenerate_id(true);
    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'customer';

    // Merge guest cart
    $guestItems = $db->prepare("SELECT * FROM cart_items WHERE session_id = ?");
    $guestItems->execute([$oldSession]);
    foreach ($guestItems->fetchAll() as $item) {
        $existing = $db->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ? AND size_ml = ? LIMIT 1");
        $existing->execute([$userId, $item['product_id'], $item['size_ml']]);
        if ($existing->fetch()) {
            $db->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND product_id = ? AND size_ml = ?")
               ->execute([$item['quantity'], $userId, $item['product_id'], $item['size_ml']]);
        } else {
            $db->prepare("INSERT INTO cart_items (user_id, product_id, size_ml, quantity) VALUES (?, ?, ?, ?)")
               ->execute([$userId, $item['product_id'], $item['size_ml'], $item['quantity']]);
        }
    }
    $db->prepare("DELETE FROM cart_items WHERE session_id = ?")->execute([$oldSession]);

    respond(['success' => true, 'message' => 'Inscription réussie.', 'user' => ['id' => $userId, 'name' => $name, 'role' => 'customer']]);
}

// ── LOGIN ─────────────────────────────────────────────────────────────────
if ($action === 'login') {
    $email    = strtolower(trim($body['email']    ?? ''));
    $password = $body['password'] ?? '';

    $errors = [];
    if (!$email)    $errors['email']    = 'Email requis.';
    if (!$password) $errors['password'] = 'Mot de passe requis.';
    if ($errors) respond(['success' => false, 'errors' => $errors], 422);

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        respond(['success' => false, 'errors' => ['general' => 'Email ou mot de passe incorrect.']], 401);
    }

    $oldSession = session_id();
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    // Merge guest cart
    $guestItems = $db->prepare("SELECT * FROM cart_items WHERE session_id = ?");
    $guestItems->execute([$oldSession]);
    foreach ($guestItems->fetchAll() as $item) {
        $existing = $db->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ? AND size_ml = ? LIMIT 1");
        $existing->execute([$user['id'], $item['product_id'], $item['size_ml']]);
        if ($existing->fetch()) {
            $db->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND product_id = ? AND size_ml = ?")
               ->execute([$item['quantity'], $user['id'], $item['product_id'], $item['size_ml']]);
        } else {
            $db->prepare("INSERT INTO cart_items (user_id, product_id, size_ml, quantity) VALUES (?, ?, ?, ?)")
               ->execute([$user['id'], $item['product_id'], $item['size_ml'], $item['quantity']]);
        }
    }
    $db->prepare("DELETE FROM cart_items WHERE session_id = ?")->execute([$oldSession]);

    respond(['success' => true, 'message' => 'Connexion réussie.', 'user' => ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]]);
}

// ── LOGOUT ────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    respond(['success' => true, 'message' => 'Déconnecté.']);
}

// ── ME ────────────────────────────────────────────────────────────────────
if ($action === 'me') {
    if (empty($_SESSION['user_id'])) respond(['success' => false, 'message' => 'Non connecté.'], 401);
    $stmt = $db->prepare("SELECT id, name, email, role, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    respond(['success' => true, 'data' => $user]);
}

respond(['success' => false, 'message' => 'Action inconnue.'], 404);
