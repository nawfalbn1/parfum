<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inscription – Fragrance by Nawfal</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{min-height:100vh;display:flex;align-items:center;justify-content:center;
       background:#f9f7f4;font-family:'Inter',sans-serif;padding:40px 20px}
  .card{background:#fff;width:100%;max-width:460px;border-radius:4px;
        box-shadow:0 4px 40px rgba(0,0,0,.08);overflow:hidden}
  .card-header{padding:40px 40px 28px;border-bottom:1px solid #f0ede8;text-align:center}
  .brand{font-family:'Playfair Display',serif;font-size:1.6rem;letter-spacing:-.5px}
  .subtitle{font-size:.8rem;color:#888;margin-top:6px}
  .card-body{padding:36px 40px}
  .form-group{margin-bottom:18px}
  label{display:block;font-size:.75rem;font-weight:500;letter-spacing:.5px;
        text-transform:uppercase;color:#555;margin-bottom:7px}
  input{width:100%;padding:13px 16px;border:1px solid #e0ddd8;border-radius:3px;
        font-size:.9rem;font-family:inherit;transition:.2s;background:#faf9f7;color:#111}
  input:focus{outline:none;border-color:#b8860b;background:#fff}
  .field-error{color:#c0392b;font-size:.78rem;margin-top:4px}
  input.invalid{border-color:#c0392b}
  .btn{width:100%;padding:14px;background:#b8860b;color:#fff;border:none;
       border-radius:3px;font-size:.8rem;font-weight:600;letter-spacing:1.5px;
       text-transform:uppercase;cursor:pointer;transition:.2s;margin-top:6px}
  .btn:hover{background:#9a7209}
  .links{text-align:center;margin-top:20px;font-size:.82rem;color:#888}
  .links a{color:#b8860b;text-decoration:none;font-weight:500}
  .links a:hover{text-decoration:underline}
  @media (max-width: 768px) {
      body { background: #f8f9fa; padding: 15px; align-items: flex-start; }
      .card { border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-top: 10px; }
      input { border-radius: 12px; font-size: 16px; }
      .btn { border-radius: 12px; }
  }
</style>
</head>
<body>
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth   = new AuthController();
$errors = [];

if ($auth->isLoggedIn()) {
    header('Location: ' . APP_URL);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->register($_POST);
    if ($result['success']) {
        header('Location: ' . APP_URL);
        exit;
    }
    $errors = $result['errors'] ?? [];
}

function fieldError(array $errors, string $key): string {
    return isset($errors[$key])
        ? '<div class="field-error">' . htmlspecialchars($errors[$key]) . '</div>'
        : '';
}
function hasError(array $errors, string $key): string {
    return isset($errors[$key]) ? ' invalid' : '';
}
function old(string $key): string {
    return htmlspecialchars($_POST[$key] ?? '');
}
?>
<div class="card">
  <div class="card-header">
    <div class="brand">Fragrance by Nawfal</div>
    <div class="subtitle">Créer votre compte</div>
  </div>
  <div class="card-body">
    <form method="POST" novalidate>
      <div class="form-group">
        <label for="name">Nom complet</label>
        <input type="text" id="name" name="name" class="<?= hasError($errors,'name') ?>"
               value="<?= old('name') ?>" placeholder="Votre nom" required>
        <?= fieldError($errors,'name') ?>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="<?= hasError($errors,'email') ?>"
               value="<?= old('email') ?>" placeholder="votre@email.ma" required>
        <?= fieldError($errors,'email') ?>
      </div>
      <div class="form-group">
        <label for="phone">Téléphone (optionnel)</label>
        <input type="text" id="phone" name="phone" value="<?= old('phone') ?>" placeholder="+212 6xx xxx xxx">
      </div>
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" class="<?= hasError($errors,'password') ?>"
               placeholder="Min. 8 caractères" required>
        <?= fieldError($errors,'password') ?>
      </div>
      <div class="form-group">
        <label for="password_confirm">Confirmer le mot de passe</label>
        <input type="password" id="password_confirm" name="password_confirm"
               class="<?= hasError($errors,'password_confirm') ?>"
               placeholder="Répéter le mot de passe" required>
        <?= fieldError($errors,'password_confirm') ?>
      </div>
      <button type="submit" class="btn">Créer mon compte</button>
    </form>
    <div class="links">
      Déjà inscrit? <a href="login.php">Se connecter</a>
    </div>
  </div>
</div>
</body>
</html>
