<?php
// ── Products API: list / get one / create / update / delete ───────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

// Start session to check admin
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 604800, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

// ── GET ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Single product
    if ($id) {
        $stmt = $db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.is_active = 1 LIMIT 1"
        );
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) respond(['success' => false, 'message' => 'Produit introuvable.'], 404);
        respond(['success' => true, 'data' => $product]);
    }

    // Search
    if (!empty($_GET['q'])) {
        $q = '%' . trim($_GET['q']) . '%';
        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.brand, p.price_100ml, p.image_url, c.name AS category_name
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND (p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)
             ORDER BY p.avg_rating DESC LIMIT 20"
        );
        $stmt->execute([$q, $q, $q]);
        $results = $stmt->fetchAll();
        respond(['success' => true, 'data' => $results, 'count' => count($results)]);
    }

    // Featured
    if (!empty($_GET['featured'])) {
        $stmt = $db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.is_featured = 1 AND p.is_active = 1
             ORDER BY p.avg_rating DESC LIMIT 6"
        );
        $stmt->execute();
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Filtered list with pagination
    $where  = ['p.is_active = 1'];
    $params = [];

    if (!empty($_GET['category'])) {
        $where[]  = 'c.slug = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['min_price'])) {
        $where[]  = 'p.price_100ml >= ?';
        $params[] = (float) $_GET['min_price'];
    }
    if (!empty($_GET['max_price'])) {
        $where[]  = 'p.price_100ml <= ?';
        $params[] = (float) $_GET['max_price'];
    }
    if (!empty($_GET['brand'])) {
        $where[]  = 'p.brand = ?';
        $params[] = $_GET['brand'];
    }
    if (!empty($_GET['search'])) {
        $like     = '%' . $_GET['search'] . '%';
        $where[]  = '(p.name LIKE ? OR p.brand LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $sortMap = ['price_asc' => 'p.price_100ml ASC', 'price_desc' => 'p.price_100ml DESC', 'rating' => 'p.avg_rating DESC', 'name' => 'p.name ASC'];
    $sort    = $sortMap[$_GET['sort'] ?? ''] ?? 'p.created_at DESC';

    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 12)));
    $offset  = ($page - 1) * $perPage;

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Fetch page
    $stmt = $db->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p JOIN categories c ON c.id = p.category_id
         $whereSQL ORDER BY $sort LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    respond([
        'success'     => true,
        'products'    => $products,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / $perPage),
    ]);
}

// ── POST – Create product (admin only) ───────────────────────────────────
if ($method === 'POST') {
    if (!isAdmin()) respond(['success' => false, 'message' => 'Accès refusé.'], 403);

    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = [];
    if (empty($data['name']))        $errors['name']        = 'Nom requis.';
    if (empty($data['brand']))       $errors['brand']       = 'Marque requise.';
    if (empty($data['category_id'])) $errors['category_id'] = 'Catégorie requise.';
    if (empty($data['price_100ml'])) $errors['price_100ml'] = 'Prix 100ml requis.';
    if ($errors) respond(['success' => false, 'errors' => $errors], 422);

    // Build slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));

    $db->prepare(
        "INSERT INTO products
         (category_id, name, slug, brand, description, top_notes, heart_notes, base_notes,
          price_50ml, price_75ml, price_100ml, stock_50ml, stock_75ml, stock_100ml, image_url, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $data['category_id'],
        $data['name'],
        $slug,
        $data['brand'],
        $data['description']  ?? null,
        $data['top_notes']    ?? null,
        $data['heart_notes']  ?? null,
        $data['base_notes']   ?? null,
        $data['price_50ml']   ?? null,
        $data['price_75ml']   ?? null,
        $data['price_100ml'],
        $data['stock_50ml']   ?? 0,
        $data['stock_75ml']   ?? 0,
        $data['stock_100ml']  ?? 0,
        $data['image_url']    ?? null,
        $data['is_featured']  ?? 0,
    ]);

    respond(['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Produit créé.'], 201);
}

// ── PUT – Update product (admin only) ────────────────────────────────────
if ($method === 'PUT') {
    if (!isAdmin()) respond(['success' => false, 'message' => 'Accès refusé.'], 403);
    if (!$id)       respond(['success' => false, 'message' => 'ID requis.'], 400);

    $data    = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['category_id','name','brand','description','top_notes','heart_notes','base_notes',
                 'price_50ml','price_75ml','price_100ml','stock_50ml','stock_75ml','stock_100ml',
                 'image_url','is_featured','is_active'];

    $sets   = [];
    $params = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $sets[]   = "$field = ?";
            $params[] = $data[$field];
        }
    }
    if (!$sets) respond(['success' => false, 'message' => 'Rien à mettre à jour.'], 400);

    $params[] = $id;
    $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

    respond(['success' => true, 'message' => 'Produit mis à jour.']);
}

// ── DELETE – Delete product (admin only) ─────────────────────────────────
if ($method === 'DELETE') {
    if (!isAdmin()) respond(['success' => false, 'message' => 'Accès refusé.'], 403);
    if (!$id)       respond(['success' => false, 'message' => 'ID requis.'], 400);

    // Soft-delete (safe if product has orders)
    $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$id]);
    respond(['success' => true, 'message' => 'Produit supprimé.']);
}

respond(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
