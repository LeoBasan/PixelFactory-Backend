<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // Autoload de Composer

// Cargar variables del archivo .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use MercadoPago\MercadoPagoConfig;

// Verificar que la variable esté definida
if (!isset($_ENV['MP_ACCESS_TOKEN'])) {
    echo json_encode(['success' => false, 'error' => 'Access Token de MercadoPago no definido']);
    exit;
}

// Inicializar MercadoPago SDK con el token
MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);
?>
