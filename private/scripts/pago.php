<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

// Token
MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);

// 📥 Recibir datos del frontend
$input = json_decode(file_get_contents("php://input"), true);
file_put_contents(__DIR__ . '/logMPago.txt', json_encode($input) . "\n", FILE_APPEND);

// Validar
if (!isset($input['items']) || !is_array($input['items']) || count($input['items']) == 0) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron productos']);
    exit;
}
// Mail del que paga o un dummy para test 
$email = $input['email'] ?? 'no-email@dominio.com';

file_put_contents(__DIR__ . '/logMPago.txt', "EMAIL DEL PAGO: ".$email."\n", FILE_APPEND);
// 🔗 Clerk user id como referencia externa (para guardar en la venta)
$clerk_user_id = $input['clerk_user_id'] ?? null;

// 🛒 Crear ítem del pago con la preferencia de MercadoPago
$items = [];
foreach ($input['items'] as $itemData) {
    $items[] = [
        'id' => $itemData['id'],
        'title' => $itemData['title'],
        'quantity' => (int)$itemData['quantity'],
        'currency_id' => $itemData['currency_id'] ?? 'ARS',
        'unit_price' => (float)$itemData['unit_price']
    ];
}

try {
    $client = new PreferenceClient();
    $preference = $client->create([
        "items" => $items,
        "payer" => ["email" => $email],
        "back_urls" => [
            "success" => "https://lightsteelblue-eagle-319225.hostingersite.com/profile", // o la ruta que prefieras
            "failure" => "https://lightsteelblue-eagle-319225.hostingersite.com/",
            "pending" => "https://lightsteelblue-eagle-319225.hostingersite.com/cart"
        ],
        "external_reference" => $clerk_user_id,
        "auto_return" => "approved"
    ]);

    echo json_encode([
        'success' => true,
        'init_point' => $preference->init_point
    ]);
}catch (\MercadoPago\Exceptions\MPApiException $e) {
    $apiResponse = $e->getApiResponse();
    $errorContent = $apiResponse ? $apiResponse->getContent() : null;

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'api_error' => $errorContent
    ]); 
}catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;
?>
