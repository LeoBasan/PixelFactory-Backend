<?php
    ob_start();
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json");
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../private/config/');
    $dotenv->load();
    require_once __DIR__ . '/../../private/scripts/mp_webhook.php'; // Acá la lógica real
    $output = ob_get_clean();
    if (strlen($output) > 0) {
        file_put_contents(__DIR__.'/log-php-warning.txt', $output . "\n", FILE_APPEND);
    }
?>
