<?php
// public/index.php – Main entry point
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = ($_SESSION['user_role'] ?? '') === 'admin';

// Admin → dashboard
if ($isLoggedIn && $isAdmin) {
    header('Location: ../backend/views/admin/dashboard.php');
    exit;
}

// Everyone (guest + logged-in user) → main site
header('Location: ../frontend/views/index.html');
exit;