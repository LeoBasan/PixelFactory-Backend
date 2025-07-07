<?php
// Habilita CORS para que Angular pueda acceder
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require_once __DIR__ . '/../../private/config/db_connection.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];

// PUT/DELETE con body JSON en PHP 
parse_str(file_get_contents("php://input"), $put_vars);

switch ($method) {
    case 'GET':
        // Obtener el carrito actual
        $clerk_user_id = $_GET['clerk_user_id'] ?? null;

        if ($clerk_user_id) {
            $stmt = $db->prepare("SELECT * FROM carts WHERE clerk_user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$clerk_user_id]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cart) {
                $stmt2 = $db->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
                $stmt2->execute([$cart['id']]);
                $products= $stmt2->fetchAll(PDO::FETCH_ASSOC);
                // Estructura uniforme para el frontend:
                $response = [
                    'id' => $cart['id'],
                    'clerk_user_id' => $cart['clerk_user_id'],
                    'total_amount' => $cart['total_amount'],
                    'products' => $products
                ];
            }else{
                $response = [
                'id' => '',
                'clerk_user_id' => $clerk_user_id,
                'total_amount' => 0,
                'products' => []
            ];
            }
            echo json_encode($response);
        } 
        break;

    case 'POST':
        // Fusionar carrito localStorage con el de la db 
        $input = json_decode(file_get_contents('php://input'), true);
        $clerk_user_id = $input['clerk_user_id'] ?? null;
        $items = $input['items'] ?? [];
        $action = $input['action'] ?? '';

                // Manejo de actualizar un producto 
        if ($action === 'update_quantity' && $clerk_user_id && !empty($items)) {
            // 1. Busco el carrito
            $stmt = $db->prepare("SELECT id FROM carts WHERE clerk_user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$clerk_user_id]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cart) {
                $cart_id = $cart['id'];
                foreach ($items as $item) {
                    // 2. Actualizo la cantidad del producto en cart_items
                    $stmt2 = $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
                    $stmt2->execute([$item['quantity'], $cart_id, $item['product_id']]);
                }

                // 3. Recalculo el total_amount del carrito
                $stmt3 = $db->prepare("SELECT ci.quantity, p.price FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?");
                $stmt3->execute([$cart_id]);
                $total = 0;
                while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
                    $total += $row['quantity'] * $row['price'];
                }
                $stmt4 = $db->prepare("UPDATE carts SET total_amount = ? WHERE id = ?");
                $stmt4->execute([$total, $cart_id]);

                // 4. Devolver el carrito actualizado
                $stmt5 = $db->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
                $stmt5->execute([$cart_id]);
                $products = $stmt5->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'id' => $cart_id,
                    'clerk_user_id' => $clerk_user_id,
                    'total_amount' => $total,
                    'products' => $products,
                ]);
                // Esto SIEMPRE actualiza el updated_at
                $stmt = $db->prepare("UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$cart_id]);

                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'No cart found']);
                exit;
            }
        }

        if ($clerk_user_id && count($items)) {
            // Busco si hay un carrito abierto (1 por user)
            $stmt = $db->prepare("SELECT * FROM carts WHERE clerk_user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$clerk_user_id]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si ya existe un carrito con ese idUser
            if($cart){
                $cart_id = $cart['id'];

                // Traer productos actuales
                $stmt2 = $db->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
                $stmt2->execute([$cart_id]);
                $existing_items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                // Crear un array por product_id
                $product_map = [];
                foreach ($existing_items as $ei) {
                    $product_map[$ei['product_id']] = $ei['quantity'];
                }

                // Fusionar
                foreach ($items as $it) {
                    $product_id = $it['product_id'];
                    $quantity = $it['quantity'];

                    if (isset($product_map[$product_id])) {
                        // Seteo las cantidades del front
                        // $new_quantity = $product_map[$product_id] + $quantity;
                        $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?")
                            ->execute([$quantity, $cart_id, $product_id]);
                    } else {
                        // Insertar nuevo producto
                        $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)")
                            ->execute([$cart_id, $product_id, $quantity]);
                    }
                }

                // Recalcular el total
                $stmt3 = $db->prepare("SELECT ci.quantity, p.price FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?");
                $stmt3->execute([$cart_id]);
                $total = 0;
                foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $total += $row['price'] * $row['quantity'];
                }
                $db->prepare("UPDATE carts SET total_amount = ? WHERE id = ?")->execute([$total, $cart_id]);

                echo json_encode(['success' => true, 'cart_id' => $cart_id, 'total' => $total, 'fusion' => true]);
                // Esto SIEMPRE actualiza el updated_at
                $stmt = $db->prepare("UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$cart_id]);

                exit;
            } else {
                // No tenia un carrito, creo uno nuevo 
                $stmt = $db->prepare("INSERT INTO carts (clerk_user_id, total_amount) VALUES (?, 0.00)");
                $stmt->execute([$clerk_user_id]);
                $cart_id = $db->lastInsertId();
                $total = 0;

                foreach ($items as $it) {
                    $product_id = $it['product_id'];
                    $quantity = $it['quantity'];
                    $stmtp = $db->prepare("SELECT price FROM products WHERE id = ?");
                    $stmtp->execute([$product_id]);
                    $prod = $stmtp->fetch(PDO::FETCH_ASSOC);
                    $price = $prod ? $prod['price'] : 0;
                    $total += $price * $quantity;
    
                    $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)")
                       ->execute([$cart_id, $product_id, $quantity]);
                }
                $db->prepare("UPDATE carts SET total_amount = ? WHERE id = ?")
                   ->execute([$total, $cart_id]);
    
                echo json_encode(['success' => true, 'cart_id' => $cart_id, 'total' => $total]);
                // Esto SIEMPRE actualiza el updated_at
                $stmt = $db->prepare("UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$cart_id]);

                exit;
            }
        } else {
            // Carrito no encontrado, lo maneja Angular
            echo json_encode(['success' => false, 'error'=> 'Carrito no valido']);
            exit;
        }

        break;
    
    case 'PUT':
        // Actualizo todo el carrito (sobrescribe los productos con los nuevos)
        $rawBody = file_get_contents("php://input");
        $input = json_decode($rawBody, true);
        $cart_id = $_GET['id'] ?? null;
        $products = $input['products'] ?? [];
        $totalAmount = $input['totalAmount'] ?? 0;

        if ($cart_id && is_array($products)) {
            $db->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cart_id]);
            foreach ($products as $it) {
                $product_id = $it['product_id'];
                $quantity = $it['quantity'];
                $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)")
                   ->execute([$cart_id, $product_id, $quantity]);
            }
            $db->prepare("UPDATE carts SET total_amount = ? WHERE id = ?")
               ->execute([$totalAmount, $cart_id]);
               echo json_encode(['success' => true, 'cart_id' => $cart_id]);
            // Esto SIEMPRE actualiza el updated_at
            $stmt = $db->prepare("UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$cart_id]);

            } else {
            echo json_encode(['success' => false, 'error' => 'Faltan datos para actualizar']);
        }

        break;

    case 'DELETE':
        // Vaciar el carrito (al pagar o cancelar)
        $cart_id = $_GET['cart_id'] ?? null;
        if ($cart_id) {
            $db->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cart_id]);
            echo json_encode(['success' => true]);
            // Esto SIEMPRE actualiza el updated_at
            $stmt = $db->prepare("UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$cart_id]);
        }else{
            echo json_encode(['success' => false, 'error' => 'Falta cart_id']);
        }

        break;
}
