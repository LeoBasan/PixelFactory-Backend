<?php
// --- Cargar conexión y utilidades ---
require_once __DIR__ . '/../config/db_connection.php';

// Guardar raw input para debugging (opcional, comentar en producción)
$raw_input = file_get_contents('php://input');
file_put_contents(__DIR__ . '/log_mp_webhook.txt', date('c')." | ".$raw_input."\n", FILE_APPEND);

// MercadoPago envía POST, pero a veces usa GET para verificación
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'msg' => 'GET OK (health check)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Parsear body JSON
$data = json_decode($raw_input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Body inválido']);
    exit;
}

// 1. Validar el tipo de evento recibido (ejemplo: "payment")
$topic = $data['type'] ?? $data['topic'] ?? null;
if ($topic !== 'payment') {
    echo json_encode(['success' => true, 'msg' => 'No es pago, ignorado']);
    exit;
}

// 2. Obtener ID del pago y consultar a la API de MercadoPago para más info
$payment_id = $data['data']['id'] ?? $data['data_id'] ?? null;
if (!$payment_id) {
    echo json_encode(['success' => false, 'error' => 'No se recibió payment_id']);
    exit;
}

// -- Consultar el pago en la API REST de MercadoPago --
$access_token = $_ENV['MP_ACCESS_TOKEN'];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$payment_id");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$mp_payment = json_decode($response, true);

// Validar estado del pago
if (!isset($mp_payment['status'])) {
    file_put_contents(__DIR__ . '/log_mp_webhook.txt', date('c')." | Pago no encontrado: $payment_id\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Pago no encontrado']);
    exit;
}

$status = $mp_payment['status']; // "approved", "pending", etc.
$external_reference = $mp_payment['external_reference'] ?? null; // aca vendria el clerk_user_id
$email = $mp_payment['payer']['email'] ?? '';
$amount = $mp_payment['transaction_amount'] ?? 0;

// Acá podés hacer lógica según tu modelo de DB, por ejemplo registrar la venta:
try {
    $db->beginTransaction();
    // Insertar en sales 
    $stmt = $db->prepare("INSERT INTO sales 
        (clerk_user_id, total_amount, payment_id, created_at) 
        VALUES (?, ?, ?, NOW())");
    $stmt->execute([
        $external_reference, // clerk_user_id
        $amount, // total_amount
        $payment_id, // guardo el id del pago para consulta mas tarde
    ]);
    $sales_id = $db ->lastInsertId();
    // 2. Insertar ítems en sales_items
    if (isset($mp_payment['additional_info']['items']) && is_array($mp_payment['additional_info']['items'])) {
        $items = $mp_payment['additional_info']['items'];
        $stmtItem = $db->prepare("INSERT INTO sales_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmtStock = $db->prepare("SELECT stock, price FROM products WHERE id = ?");
        $stmtUpdateStock = $db->prepare("UPDATE products SET stock = ? WHERE id = ?");
        foreach ($items as $item) {
            $product_id = $item['id'] ?? null; 
            $quantity = $item['quantity'] ?? 1;
            $price = $item['unit_price'] ?? 0;

            if ($product_id) {
                // Validar stock suficiente
                $stmtStock->execute([$product_id]);
                $prod = $stmtStock->fetch(PDO::FETCH_ASSOC);
                if (!$prod || $prod['stock'] < $quantity) {
                    throw new Exception('Sin stock para el producto: '.$product_id);
                }
                // Descontar stock
                $nuevoStock = $prod['stock'] - $quantity;
                $stmtUpdateStock->execute([$nuevoStock, $product_id]);

                // Guardar item de venta
                $stmtItem->execute([$sale_id, $product_id, $quantity, $price]);
            }
        }
    }
    $db->commit();
    echo json_encode(['success' => true, 'status' => $status]);
} catch (PDOException $e) {
    $db->rollBack();
    file_put_contents(__DIR__ . '/log_mp_webhook.txt', date('c')." | Error DB: ".$e->getMessage()."\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Error DB']);
    exit;
}

// Podés retornar una respuesta para confirmar a MP que se recibió bien:
echo json_encode(['success' => true, 'status' => $status]);
exit;
?>