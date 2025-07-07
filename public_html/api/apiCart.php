<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE', 'PUT', 'GET'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../private/config/');
$dotenv->load();

require_once __DIR__ . '/../../private/scripts/cart.php';

?>
