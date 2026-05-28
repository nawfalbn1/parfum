<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../helpers/fragrance_details.php';

$auth = new AuthController();
$auth->requireAdmin();

function jsonOut(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$name = fragrance_clean_optional($body['name'] ?? '') ?? '';
$brand = fragrance_clean_optional($body['brand'] ?? '') ?? '';

if (strlen($name) < 2) {
    jsonOut(['success' => false, 'message' => 'Nom du parfum trop court.'], 400);
}

// Pass any notes already submitted so Gemini only fills what's missing
$submitted = [
    'top_notes'   => fragrance_clean_optional($body['top_notes']   ?? '') ,
    'heart_notes' => fragrance_clean_optional($body['heart_notes'] ?? '') ,
    'base_notes'  => fragrance_clean_optional($body['base_notes']  ?? '') ,
    'description' => fragrance_clean_optional($body['description'] ?? '') ,
];
$result = fragrance_complete_details($name, $brand, $submitted, true);
$details = $result['details'];

jsonOut([
    'success' => true,
    'perfume' => trim($name . ($brand !== '' ? " by {$brand}" : '')),
    'top_notes' => $details['top_notes'],
    'heart_notes' => $details['heart_notes'],
    'base_notes' => $details['base_notes'],
    'description' => $details['description'],
    'source' => $result['source'],
    'warning' => $result['warning'],
]);
