<?php
// ── Bootstrap: must be FIRST, before any HTML output ────────
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Contact.php';
require_once __DIR__ . '/../../models/Review.php';

$auth = new AuthController();
$auth->requireAdmin();

$productModel = new Product();
$orderModel   = new Order();
$userModel    = new User();
$contactModel = new Contact();
$reviewModel  = new Review();

$tab = $_GET['tab'] ?? 'dashboard';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_product')    { $productModel->create($_POST); $msg = 'Produit créé.'; $tab = 'products'; }
    if ($action === 'delete_product')    { $productModel->delete((int)$_POST['id']); $msg = 'Produit supprimé.'; $tab = 'products'; }
    if ($action === 'update_order_status') { $orderModel->updateStatus((int)$_POST['order_id'], $_POST['status']); $msg = 'Statut mis à jour.'; $tab = 'orders'; }
    if ($action === 'approve_review')    { $reviewModel->approve((int)$_POST['review_id']); $msg = 'Avis approuvé.'; $tab = 'reviews'; }
    if ($action === 'delete_review')     { $reviewModel->delete((int)$_POST['review_id']); $msg = 'Avis supprimé.'; $tab = 'reviews'; }
    if ($action === 'mark_contact_read') { $contactModel->markRead((int)$_POST['contact_id']); $tab = 'contacts'; }
    if ($action === 'logout')            { $auth->logout(); header('Location: ../../public/login.php'); exit; }
}

$stats    = [
    'products' => $productModel->count(),
    'orders'   => $orderModel->count(),
    'users'    => $userModel->count(),
    'revenue'  => $orderModel->totalRevenue(),
    'messages' => $contactModel->unreadCount(),
];
$products = $productModel->getAll([], 1, 50);
$orders   = $orderModel->getAll(1, 30);
$contacts = $contactModel->getAll();
$reviews  = $reviewModel->getPending();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin – Fragrance by Nawfal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f5f5f5;color:#222}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:240px;background:#111;color:#fff;padding:0;z-index:100;overflow-y:auto}
.sidebar-brand{padding:28px 24px;font-size:1rem;font-weight:600;border-bottom:1px solid #333;letter-spacing:-.3px}
.sidebar-brand span{color:#b8860b}
.sidebar-nav{padding:16px 0}
.nav-item{display:flex;align-items:center;gap:12px;padding:13px 24px;color:#aaa;font-size:.85rem;cursor:pointer;transition:.2s;text-decoration:none}
.nav-item:hover,.nav-item.active{color:#fff;background:#1a1a1a}
.nav-item i{width:16px;text-align:center}
.main{margin-left:240px;padding:30px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}
.topbar h1{font-size:1.4rem;font-weight:600}
.badge{background:#b8860b;color:#fff;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:30px}
.stat-card{background:#fff;padding:24px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.stat-card .value{font-size:2rem;font-weight:700;color:#111;line-height:1}
.stat-card .label{font-size:.78rem;color:#888;margin-top:8px}
.stat-card .icon{font-size:1.4rem;color:#b8860b;margin-bottom:12px}
.section{background:#fff;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:24px;overflow:hidden}
.section-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #f0f0f0}
.section-header h2{font-size:.95rem;font-weight:600}
table{width:100%;border-collapse:collapse}
th,td{padding:13px 24px;text-align:left;font-size:.83rem}
th{background:#fafafa;font-weight:600;color:#555;border-bottom:1px solid #f0f0f0}
td{border-bottom:1px solid #f8f8f8;color:#333}
tr:last-child td{border-bottom:none}
.status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
.status.pending{background:#fff3cd;color:#856404}
.status.confirmed{background:#d1ecf1;color:#0c5460}
.status.delivered{background:#d4edda;color:#155724}
.status.cancelled{background:#f8d7da;color:#721c24}
.btn-sm{padding:6px 14px;border-radius:3px;font-size:.75rem;font-weight:600;cursor:pointer;border:none;transition:.2s}
.btn-gold{background:#b8860b;color:#fff}.btn-gold:hover{background:#9a7209}
.btn-danger{background:#dc3545;color:#fff}.btn-danger:hover{background:#c82333}
.btn-outline{background:transparent;border:1px solid #ddd;color:#555}.btn-outline:hover{background:#f5f5f5}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:24px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:.75rem;font-weight:500;color:#555;text-transform:uppercase;letter-spacing:.5px}
.form-group input,.form-group select,.form-group textarea{padding:10px 14px;border:1px solid #e0e0e0;border-radius:3px;font-size:.85rem;font-family:inherit}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#b8860b}
.form-actions{padding:0 24px 24px;display:flex;gap:10px}
.alert{padding:12px 16px;border-radius:4px;font-size:.85rem;margin-bottom:20px}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
@media (max-width: 768px) {
    body { display: flex; flex-direction: column; }
    .sidebar { position: static; width: 100%; height: auto; overflow-y: visible; padding-bottom: 20px; }
    .sidebar-nav { display: flex; flex-wrap: wrap; }
    .nav-item { width: 50%; justify-content: center; padding: 10px; }
    .main { margin-left: 0; padding: 15px; }
    .stats { grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-row { grid-template-columns: 1fr; padding: 15px; }
    .section { overflow-x: auto; border-radius: 12px; }
    table { display: block; overflow-x: auto; white-space: nowrap; width: 100%; }
    .topbar { flex-direction: column; align-items: flex-start; gap: 10px; margin-bottom: 20px; }
}
@media (max-width: 480px) {
    .stats { grid-template-columns: 1fr; }
    .nav-item { width: 100%; justify-content: flex-start; }
}
</style>
</head>
<body>


<div class="sidebar">
    <div class="sidebar-brand">Fragrance <span>Admin</span></div>
    <nav class="sidebar-nav">
        <a class="nav-item <?= $tab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="fas fa-chart-bar"></i> Dashboard</a>
        <a class="nav-item <?= $tab==='products'?'active':'' ?>"  href="?tab=products"><i class="fas fa-spray-can"></i> Produits</a>
        <a class="nav-item <?= $tab==='orders'?'active':'' ?>"    href="?tab=orders"><i class="fas fa-box"></i> Commandes</a>
        <a class="nav-item <?= $tab==='reviews'?'active':'' ?>"   href="?tab=reviews"><i class="fas fa-star"></i> Avis <?= $reviews ? '<span class="badge">'.count($reviews).'</span>' : '' ?></a>
        <a class="nav-item <?= $tab==='contacts'?'active':'' ?>"  href="?tab=contacts"><i class="fas fa-envelope"></i> Messages <?= $stats['messages'] ? '<span class="badge">'.$stats['messages'].'</span>' : '' ?></a>
        <a class="nav-item" href="../../../frontend/views/index.html"><i class="fas fa-home"></i> Site</a>
        <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="nav-item" style="width:100%;background:none;border:none;cursor:pointer;text-align:left">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </button>
        </form>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h1><?= ucfirst($tab) ?></h1>
        <span><?= htmlspecialchars($_SESSION['user_name']) ?> &nbsp;|&nbsp; Admin</span>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif ?>

    <?php if ($tab === 'dashboard'): ?>
    <div class="stats">
        <div class="stat-card"><div class="icon"><i class="fas fa-spray-can"></i></div><div class="value"><?= $stats['products'] ?></div><div class="label">Produits actifs</div></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-box"></i></div><div class="value"><?= $stats['orders'] ?></div><div class="label">Commandes</div></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-users"></i></div><div class="value"><?= $stats['users'] ?></div><div class="label">Clients</div></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-coins"></i></div><div class="value"><?= number_format($stats['revenue'],0,',',' ') ?> DH</div><div class="label">Chiffre d'affaires</div></div>
    </div>

    <div class="section">
        <div class="section-header"><h2>Dernières commandes</h2></div>
        <table>
            <thead><tr><th>N°</th><th>Client</th><th>Total</th><th>Statut</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($orders, 0, 8) as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['order_number']) ?></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><?= number_format($o['total'],2) ?> DH</td>
                <td><span class="status <?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                <td><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'products'): ?>
    <div class="section" style="margin-bottom:20px">
        <div class="section-header"><h2>Ajouter un produit</h2></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_product">
            <div class="form-row">
                <div class="form-group"><label>Nom</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Marque</label><input type="text" name="brand" required></div>
                <div class="form-group"><label>Catégorie</label>
                    <select name="category_id">
                        <option value="1">Homme</option><option value="2">Femme</option>
                        <option value="3">Mixte</option><option value="4">Édition Limitée</option>
                    </select>
                </div>
                <div class="form-group"><label>Prix 100ml (DH)</label><input type="number" name="price_100ml" step="0.01" required></div>
                <div class="form-group"><label>Prix 75ml</label><input type="number" name="price_75ml" step="0.01"></div>
                <div class="form-group"><label>Prix 50ml</label><input type="number" name="price_50ml" step="0.01"></div>
                <div class="form-group"><label>Stock 100ml</label><input type="number" name="stock_100ml" value="0"></div>
                <div class="form-group"><label>Stock 75ml</label><input type="number" name="stock_75ml" value="0"></div>
                <div class="form-group" style="grid-column:span 2"><label>Image URL</label><input type="url" name="image_url"></div>
                <div class="form-group" style="grid-column:span 2"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-sm btn-gold">Créer le produit</button></div>
        </form>
    </div>

    <div class="section">
        <div class="section-header"><h2>Tous les produits (<?= count($products) ?>)</h2></div>
        <table>
            <thead><tr><th>ID</th><th>Nom</th><th>Marque</th><th>Prix</th><th>Stock</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td>#<?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['brand']) ?></td>
                <td><?= number_format($p['price_100ml'],2) ?> DH</td>
                <td><?= $p['stock_100ml'] ?> unités</td>
                <td>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer?')">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'orders'): ?>
    <div class="section">
        <div class="section-header"><h2>Toutes les commandes</h2></div>
        <table>
            <thead><tr><th>N°</th><th>Client</th><th>Email</th><th>Total</th><th>Statut</th><th>Paiement</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['order_number']) ?></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><?= htmlspecialchars($o['customer_email']) ?></td>
                <td><?= number_format($o['total'],2) ?> DH</td>
                <td><span class="status <?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                <td><?= $o['payment_status'] ?></td>
                <td><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="status" style="padding:5px;font-size:.75rem;border:1px solid #ddd;border-radius:3px">
                            <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach ?>
                        </select>
                        <button type="submit" class="btn-sm btn-gold">OK</button>
                    </form>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'reviews'): ?>
    <div class="section">
        <div class="section-header"><h2>Avis en attente (<?= count($reviews) ?>)</h2></div>
        <table>
            <thead><tr><th>Produit</th><th>Auteur</th><th>Note</th><th>Commentaire</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($reviews)): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:#aaa">Aucun avis en attente.</td></tr>
            <?php else: foreach ($reviews as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['product_name']) ?></td>
                <td><?= htmlspecialchars($r['author_name']) ?></td>
                <td>⭐ <?= $r['rating'] ?>/5</td>
                <td><?= htmlspecialchars(substr($r['body'] ?? '', 0, 80)) ?>...</td>
                <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                <td style="display:flex;gap:6px">
                    <form method="POST"><input type="hidden" name="action" value="approve_review"><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><button class="btn-sm btn-gold">Approuver</button></form>
                    <form method="POST" onsubmit="return confirm('Supprimer?')"><input type="hidden" name="action" value="delete_review"><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><button class="btn-sm btn-danger">Supprimer</button></form>
                </td>
            </tr>
            <?php endforeach; endif ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'contacts'): ?>
    <div class="section">
        <div class="section-header"><h2>Messages reçus (<?= count($contacts) ?>)</h2></div>
        <table>
            <thead><tr><th>Nom</th><th>Email</th><th>Sujet</th><th>Message</th><th>Lu</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($contacts as $c): ?>
            <tr style="<?= !$c['is_read'] ? 'font-weight:500' : '' ?>">
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= htmlspecialchars($c['subject']) ?></td>
                <td><?= htmlspecialchars(substr($c['message'], 0, 60)) ?>...</td>
                <td><?= $c['is_read'] ? '✓' : '<span style="color:#b8860b">●</span>' ?></td>
                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <?php if (!$c['is_read']): ?>
                    <form method="POST"><input type="hidden" name="action" value="mark_contact_read"><input type="hidden" name="contact_id" value="<?= $c['id'] ?>"><button class="btn-sm btn-outline">Marquer lu</button></form>
                    <?php endif ?>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
</body>
</html>
