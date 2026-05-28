<?php

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
    if ($action === 'update_product')    { $productModel->update((int)$_POST['id'], $_POST); $msg = 'Produit mis à jour.'; $tab = 'products'; }
    if ($action === 'delete_product')    { $productModel->delete((int)$_POST['id']); $msg = 'Produit supprimé.'; $tab = 'products'; }
    if ($action === 'update_order_status') { $orderModel->updateStatus((int)$_POST['order_id'], $_POST['status']); $msg = 'Statut mis à jour.'; $tab = 'orders'; }
    if ($action === 'update_payment_status') { $orderModel->updatePaymentStatus((int)$_POST['order_id'], $_POST['payment_status']); $msg = 'Statut de paiement mis à jour.'; $tab = 'orders'; }
    if ($action === 'approve_review')    { $reviewModel->approve((int)$_POST['review_id']); $msg = 'Avis approuvé.'; $tab = 'reviews'; }
    if ($action === 'delete_review')     { $reviewModel->delete((int)$_POST['review_id']); $msg = 'Avis supprimé.'; $tab = 'reviews'; }
    if ($action === 'mark_contact_read') { $contactModel->markRead((int)$_POST['contact_id']); $tab = 'contacts'; }
    if ($action === 'logout')            { $auth->logout(); header('Location: ' . APP_URL . '/public/login.php'); exit; }
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
            <thead><tr><th>N°</th><th>Client Info</th><th>Produits</th><th>Total</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($orders, 0, 8) as $o): 
                $items = $orderModel->getItems($o['id']);
            ?>
            <tr>
                <td><?= htmlspecialchars($o['order_number']) ?><br><small><?= date('d/m/Y', strtotime($o['created_at'])) ?></small></td>
                <td>
                    <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
                    <small><i class="fas fa-phone"></i> <?= htmlspecialchars($o['customer_phone'] ?? 'N/A') ?></small><br>
                    <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($o['shipping_address'] ?? 'N/A') ?>, <?= htmlspecialchars($o['shipping_city'] ?? '') ?></small>
                </td>
                <td>
                    <ul style="margin:0;padding-left:15px;font-size:0.85rem;color:#555;">
                    <?php foreach($items as $item): ?>
                        <li><?= htmlspecialchars($item['name'] ?? 'Parfum') ?> (<?= $item['size_ml'] ?>ml) x <?= $item['quantity'] ?></li>
                    <?php endforeach; ?>
                    </ul>
                </td>
                <td><?= number_format($o['total'],2) ?> DH</td>
                <td><span class="status <?= $o['status'] ?>"><?= $o['status'] ?></span></td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'products'): ?>
    <div class="section" style="margin-bottom:20px" id="productFormSection">
        <div class="section-header">
            <h2 id="formTitle">Ajouter un produit</h2>
            <div>
                <span style="font-size:.78rem;color:#888">✨ Saisissez le nom + marque puis cliquez sur <strong>Auto-remplir les notes</strong></span>
                <button type="button" class="btn-sm btn-outline" style="margin-left:10px;display:none" id="cancelEditBtn" onclick="resetForm()">Annuler</button>
            </div>
        </div>
        <form method="POST" id="productForm">
            <input type="hidden" name="action" id="formAction" value="create_product">
            <input type="hidden" name="id" id="productId" value="">
            <div class="form-row">
                <div class="form-group"><label>Nom du parfum</label><input type="text" id="perfumeName" name="name" required placeholder="Ex: Creed Aventus"></div>
                <div class="form-group"><label>Marque</label><input type="text" id="perfumeBrand" name="brand" required placeholder="Ex: Creed"></div>

                <!-- AI Auto-fill button -->
                <div class="form-group" style="grid-column:span 2">
                    <button type="button" id="aiFillBtn" onclick="autoFillNotes()" style="
                        background:linear-gradient(135deg,#b8860b,#d4a017);color:#fff;border:none;
                        padding:10px 22px;border-radius:4px;font-size:.85rem;font-weight:600;
                        cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:.2s">
                        <span id="aiBtnIcon">✨</span>
                        <span id="aiBtnText">Auto-remplir les notes avec l'IA</span>
                    </button>
                    <div id="aiStatus" style="margin-top:8px;font-size:.8rem;display:none"></div>
                </div>

                <div class="form-group"><label>Catégorie</label>
                    <select name="category_id" id="perfumeCategory">
                        <option value="1">Homme</option><option value="2">Femme</option>
                        <option value="3">Mixte</option><option value="4">Édition Limitée</option>
                    </select>
                </div>
                <div class="form-group"><label>Prix 100ml (DH)</label><input type="number" id="price100" name="price_100ml" step="0.01" required></div>
                <div class="form-group"><label>Prix 75ml</label><input type="number" id="price75" name="price_75ml" step="0.01"></div>
                <div class="form-group"><label>Prix 50ml</label><input type="number" id="price50" name="price_50ml" step="0.01"></div>
                <div class="form-group"><label>Stock 100ml</label><input type="number" id="stock100" name="stock_100ml" value="0"></div>
                <div class="form-group"><label>Stock 75ml</label><input type="number" id="stock75" name="stock_75ml" value="0"></div>
                <div class="form-group"><label>Stock 50ml</label><input type="number" id="stock50" name="stock_50ml" value="0"></div>

                <div class="form-group">
                    <label>Notes de Tête <span style="color:#b8860b;font-size:.7rem">AUTO ✨</span></label>
                    <input type="text" id="topNotes" name="top_notes" placeholder="Ex: Bergamote, Citron">
                </div>
                <div class="form-group">
                    <label>Notes de Cœur <span style="color:#b8860b;font-size:.7rem">AUTO ✨</span></label>
                    <input type="text" id="heartNotes" name="heart_notes" placeholder="Ex: Rose, Jasmin">
                </div>
                <div class="form-group">
                    <label>Notes de Fond <span style="color:#b8860b;font-size:.7rem">AUTO ✨</span></label>
                    <input type="text" id="baseNotes" name="base_notes" placeholder="Ex: Vanille, Musc">
                </div>

                <div class="form-group" style="grid-column:span 2">
                    <label>Image URL</label>
                    <input type="url" name="image_url" id="imageUrlInput" placeholder="https://..." oninput="previewImage(this.value)">
                    <div id="imagePreviewBox" style="margin-top:10px;display:none">
                        <img id="imagePreview" src="" alt="Aperçu" style="max-height:180px;max-width:200px;object-fit:contain;border-radius:10px;background:#f7f4ee;padding:12px;border:1px solid #eee">
                        <p id="imagePreviewStatus" style="font-size:.75rem;color:#888;margin-top:4px"></p>
                    </div>
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label>Description <span style="color:#b8860b;font-size:.7rem">AUTO ✨</span></label>
                    <textarea id="descriptionField" name="description" rows="3" placeholder="Description générée automatiquement par l'IA…"></textarea>
                </div>
                <div class="form-group">
                    <label>Produit vedette (Best-seller)</label>
                    <select name="is_featured" id="isFeatured">
                        <option value="0">Non</option>
                        <option value="1">Oui – afficher en page d'accueil</option>
                    </select>
                </div>
            </div>
            <div class="form-actions"><button type="submit" id="submitBtn" class="btn-sm btn-gold">Créer le produit</button></div>
        </form>
    </div>
    <script>
    function previewImage(url) {
        const box = document.getElementById('imagePreviewBox');
        const img = document.getElementById('imagePreview');
        const status = document.getElementById('imagePreviewStatus');
        if (!url) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        status.textContent = 'Chargement…';
        img.onload  = () => { status.textContent = '✅ Image trouvée (' + img.naturalWidth + '×' + img.naturalHeight + 'px)'; };
        img.onerror = () => { status.textContent = "❌ Image introuvable – vérifiez l'URL"; };
        img.src = url;
    }

    async function autoFillNotes() {
        const name  = document.getElementById('perfumeName').value.trim();
        const brand = document.getElementById('perfumeBrand').value.trim();
        const btn   = document.getElementById('aiFillBtn');
        const icon  = document.getElementById('aiBtnIcon');
        const text  = document.getElementById('aiBtnText');
        const status = document.getElementById('aiStatus');

        if (!name) { alert('Veuillez d\'abord saisir le nom du parfum.'); return; }

        // Read what the user already typed — only blank fields will be auto-filled
        const existingTop   = document.getElementById('topNotes').value.trim();
        const existingHeart = document.getElementById('heartNotes').value.trim();
        const existingBase  = document.getElementById('baseNotes').value.trim();
        const existingDesc  = document.getElementById('descriptionField').value.trim();

        // Loading state
        btn.disabled = true;
        icon.textContent = '⏳';
        text.textContent = 'Recherche en cours…';
        status.style.display = 'block';
        status.style.color = '#888';
        status.textContent = `Consultation de l'IA pour "${name}"${brand ? ' par ' + brand : ''}…`;

        try {
            // Send existing values to the API so it only fills what's missing
            const res = await fetch('/site%20dyali/backend/api/lookup_fragrance.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    name,
                    brand,
                    top_notes:   existingTop,
                    heart_notes: existingHeart,
                    base_notes:  existingBase,
                    description: existingDesc,
                }),
            });
            const data = await res.json();

            if (data.success) {
                // Only populate a field if the user left it BLANK
                if (!existingTop   && data.top_notes)   document.getElementById('topNotes').value        = data.top_notes;
                if (!existingHeart && data.heart_notes) document.getElementById('heartNotes').value      = data.heart_notes;
                if (!existingBase  && data.base_notes)  document.getElementById('baseNotes').value       = data.base_notes;
                if (!existingDesc  && data.description) document.getElementById('descriptionField').value = data.description;

                const filled = [!existingTop, !existingHeart, !existingBase, !existingDesc].filter(Boolean).length;
                status.style.color = '#155724';
                status.textContent = filled > 0
                    ? `✅ ${filled} champ(s) rempli(s) automatiquement. Vérifiez et corrigez si nécessaire.`
                    : '✅ Tous les champs étaient déjà remplis – aucune modification effectuée.';
                icon.textContent = '✅';
                text.textContent = 'Notes remplies avec succès';
            } else {
                status.style.color = '#721c24';
                status.textContent = '❌ ' + data.message;
                icon.textContent = '✨';
                text.textContent = 'Auto-remplir les notes avec l\'IA';
            }
        } catch (e) {
            status.style.color = '#721c24';
            status.textContent = '❌ Erreur réseau: ' + e.message;
            icon.textContent = '✨';
            text.textContent = 'Auto-remplir les notes avec l\'IA';
        } finally {
            btn.disabled = false;
        }
    }

    function editProduct(p) {
        document.getElementById('formAction').value = 'update_product';
        document.getElementById('productId').value = p.id;
        document.getElementById('formTitle').innerText = 'Modifier le produit #' + p.id;
        document.getElementById('submitBtn').innerText = 'Enregistrer les modifications';
        document.getElementById('cancelEditBtn').style.display = 'inline-block';

        document.getElementById('perfumeName').value = p.name || '';
        document.getElementById('perfumeBrand').value = p.brand || '';
        document.getElementById('perfumeCategory').value = p.category_id || 1;
        document.getElementById('price100').value = p.price_100ml || '';
        document.getElementById('price75').value = p.price_75ml || '';
        document.getElementById('price50').value = p.price_50ml || '';
        document.getElementById('stock100').value = p.stock_100ml || 0;
        document.getElementById('stock75').value = p.stock_75ml || 0;
        document.getElementById('stock50').value = p.stock_50ml || 0;
        document.getElementById('topNotes').value = p.top_notes || '';
        document.getElementById('heartNotes').value = p.heart_notes || '';
        document.getElementById('baseNotes').value = p.base_notes || '';
        document.getElementById('descriptionField').value = p.description || '';
        document.getElementById('imageUrlInput').value = p.image_url || '';
        document.getElementById('isFeatured').value = p.is_featured || 0;
        previewImage(p.image_url || '');

        window.scrollTo({ top: document.getElementById('productFormSection').offsetTop - 20, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formAction').value = 'create_product';
        document.getElementById('productId').value = '';
        document.getElementById('formTitle').innerText = 'Ajouter un produit';
        document.getElementById('submitBtn').innerText = 'Créer le produit';
        document.getElementById('cancelEditBtn').style.display = 'none';
        document.getElementById('productForm').reset();
        previewImage('');
    }
    </script>


    <div class="section">
        <div class="section-header"><h2>Tous les produits (<?= count($products) ?>)</h2></div>
        <table>
            <thead><tr><th>ID</th><th>Image</th><th>Nom</th><th>Marque</th><th>Details</th><th>Prix</th><th>Stock</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td>#<?= $p['id'] ?></td>
                <td>
                    <?php if (!empty($p['image_url'])): ?>
                    <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="" style="width:50px;height:50px;object-fit:contain;background:#f7f4ee;border-radius:6px;padding:4px;">
                    <?php else: ?>
                    <span style="color:#ccc;font-size:.75rem">Aucune</span>
                    <?php endif ?>
                </td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['brand']) ?></td>
                <td style="max-width:340px">
                    <div style="font-size:.75rem;color:#777;line-height:1.45">
                        <strong>Description:</strong>
                        <?= htmlspecialchars($p['description'] ?: 'Non renseignee') ?><br>
                        <strong>Tete:</strong> <?= htmlspecialchars($p['top_notes'] ?: 'Non renseignee') ?><br>
                        <strong>Coeur:</strong> <?= htmlspecialchars($p['heart_notes'] ?: 'Non renseignee') ?><br>
                        <strong>Fond:</strong> <?= htmlspecialchars($p['base_notes'] ?: 'Non renseignee') ?>
                    </div>
                </td>
                <td><?= number_format($p['price_100ml'],2) ?> DH</td>
                <td><?= $p['stock_100ml'] ?> unités</td>
                <td>
                    <button type="button" class="btn-sm btn-outline" style="margin-bottom:5px;" onclick='editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>Éditer</button>
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
            <thead><tr><th>N°</th><th>Client Info</th><th>Produits</th><th>Total</th><th>Statut / Paiement</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): 
                $items = $orderModel->getItems($o['id']);
            ?>
            <tr>
                <td><?= htmlspecialchars($o['order_number']) ?><br><small><?= date('d/m/Y', strtotime($o['created_at'])) ?></small></td>
                <td>
                    <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
                    <small><i class="fas fa-phone"></i> <?= htmlspecialchars($o['customer_phone'] ?? 'N/A') ?></small><br>
                    <small><i class="fas fa-envelope"></i> <?= htmlspecialchars($o['customer_email'] ?? 'N/A') ?></small><br>
                    <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($o['shipping_address'] ?? 'N/A') ?>, <?= htmlspecialchars($o['shipping_city'] ?? '') ?></small>
                </td>
                <td>
                    <ul style="margin:0;padding-left:15px;font-size:0.85rem;color:#555;">
                    <?php foreach($items as $item): ?>
                        <li><?= htmlspecialchars($item['name'] ?? 'Parfum') ?> (<?= $item['size_ml'] ?>ml) x <?= $item['quantity'] ?></li>
                    <?php endforeach; ?>
                    </ul>
                </td>
                <td><?= number_format($o['total'],2) ?> DH</td>
                <td>
                    <span class="status <?= $o['status'] ?>" style="display:block;margin-bottom:4px;text-align:center;"><?= $o['status'] ?></span>
                    <span style="font-size:0.75rem;display:block;text-align:center;color:#666">Paiement: <?= $o['payment_status'] ?></span>
                </td>
                <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;margin-bottom:5px">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="status" style="padding:5px;font-size:.75rem;border:1px solid #ddd;border-radius:3px;min-width:100px;">
                            <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach ?>
                        </select>
                        <button type="submit" class="btn-sm btn-gold">OK</button>
                    </form>
                    <form method="POST" style="display:flex;gap:6px;align-items:center">
                        <input type="hidden" name="action" value="update_payment_status">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="payment_status" style="padding:5px;font-size:.75rem;border:1px solid #ddd;border-radius:3px;min-width:100px;">
                            <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['payment_status']===$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach ?>
                        </select>
                        <button type="submit" class="btn-sm btn-outline">Payé?</button>
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
