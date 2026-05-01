<?php
require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/controllers/AuthController.php';

$auth  = new AuthController();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login($_POST);

    if ($result['success']) {
        if ($auth->isAdmin()) {
            header('Location: ../backend/views/admin/dashboard.php');
        } else {
            header('Location: ../frontend/views/index.html');
        }
        exit;
    }

    $error = $result['errors']['general'] ?? 'Email ou mot de passe incorrect.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion – Fragrance by Nawfal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0; padding: 0; box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            font-family: 'Inter', sans-serif;
            background: #f9f7f4;
        }

        /* ── Left panel ─────────────────────────────── */
        .left-panel {
            flex: 1;
            background: #0a0a0a;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 50%, rgba(184,134,11,0.15) 0%, transparent 60%);
        }

        .left-brand {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            color: #fff;
            letter-spacing: -1px;
            line-height: 1.1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .left-brand span {
            color: #b8860b;
            font-style: italic;
        }

        .left-tagline {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.4);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 18px;
            position: relative;
            z-index: 1;
        }

        .left-divider {
            width: 40px;
            height: 1px;
            background: #b8860b;
            margin: 24px auto;
            position: relative;
            z-index: 1;
        }

        .left-quote {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            color: rgba(255,255,255,0.25);
            font-size: 1rem;
            text-align: center;
            max-width: 280px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        /* ── Right panel (form) ─────────────────────── */
        .right-panel {
            width: 480px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 56px;
            background: #fff;
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 400;
            color: #0a0a0a;
            letter-spacing: -0.5px;
        }

        .form-header p {
            font-size: 0.82rem;
            color: #999;
            margin-top: 8px;
        }

        /* ── Error alert ────────────────────────────── */
        .alert-error {
            background: #fdf0ee;
            border: 1px solid #f5c6c0;
            border-left: 3px solid #c0392b;
            color: #922b21;
            padding: 13px 16px;
            border-radius: 3px;
            font-size: 0.83rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error::before {
            content: '!';
            width: 18px;
            height: 18px;
            background: #c0392b;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* ── Form groups ────────────────────────────── */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e5e2dd;
            border-radius: 3px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            background: #faf9f7;
            color: #111;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
        }

        .form-group input:focus {
            outline: none;
            border-color: #b8860b;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(184,134,11,0.1);
        }

        /* ── Submit button ──────────────────────────── */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: #0a0a0a;
            color: #fff;
            border: none;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.25s, transform 0.15s;
            margin-top: 6px;
        }

        .btn-login:hover {
            background: #b8860b;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* ── Footer link ────────────────────────────── */
        .form-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 0.82rem;
            color: #999;
        }

        .form-footer a {
            color: #b8860b;
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* ── Responsive ─────────────────────────────── */
        @media (max-width: 768px) {
            .left-panel { display: none; }
            body { background: #f8f9fa; padding: 15px; align-items: flex-start; }
            .right-panel { 
                width: 100%; 
                padding: 40px 25px; 
                border-radius: 20px; 
                box-shadow: 0 5px 20px rgba(0,0,0,0.05); 
                margin-top: 20px;
            }
            .form-header h1 { font-size: 1.8rem; }
            .form-group input { border-radius: 12px; font-size: 16px; }
            .btn-login { border-radius: 12px; }
        }
    </style>
</head>
<body>

    <!-- Left decorative panel -->
    <div class="left-panel">
        <div class="left-brand">Fragrance<br><span>by Nawfal</span></div>
        <div class="left-tagline">Maison de Parfums</div>
        <div class="left-divider"></div>
        <div class="left-quote">"Le parfum, c'est l'invisible qui reste"</div>
    </div>

    <!-- Right login form -->
    <div class="right-panel">
        <div class="form-header">
            <h1>Connexion</h1>
            <p>Bienvenue. Connectez-vous à votre compte.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <form method="POST" novalidate>
            <div class="form-group">
                <label for="email">Adresse Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="votre@email.ma" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>

        <div class="form-footer">
            Pas encore de compte? <a href="register.php">Créer un compte</a>
        </div>
    </div>

</body>
</html>
