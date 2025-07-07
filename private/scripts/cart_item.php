<?php
// Habilita CORS para que Angular pueda acceder
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require_once __DIR__ . '/../../private/config/db_connection.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Agregar producto a carrito (sesión o base)
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = $input['product_id'];
        $quantity = intval($input['quantity'] ?? 1);
        $cart_id = $input['cart_id'] ?? null;

        if ($cart_id) {
            // Carrito en la base
            $stmt = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            $stmt->execute([$cart_id, $product_id, $quantity]);
            echo json_encode(['success' => true]);
        } else {
            // Si no hay cart_id, Angular maneja el carrito local
            echo json_encode(['success' => false, 'error' => 'Carrito no valido']);
        }
        break;

    case 'DELETE':
        // Quitar producto
        $product_id = $_GET['product_id'] ?? null;
        $cart_id = $_GET['cart_id'] ?? null;
        if ($cart_id) {
            $db->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?")->execute([$cart_id, $product_id]);

            // Recalcular total
            $stmt = $db->prepare("SELECT SUM(ci.quantity * p.price) AS total
                                  FROM cart_items ci
                                  JOIN products p ON ci.product_id = p.id
                                  WHERE ci.cart_id = ?");
            $stmt->execute([$cart_id]);
            $total = $stmt->fetchColumn();
            $db->prepare("UPDATE carts SET total_amount = ? WHERE id = ?")->execute([$total ?: 0, $cart_id]);
        }
        
        echo json_encode(['success' => true]);
        break;
}
