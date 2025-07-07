<?php
// Habilita CORS para que Angular pueda acceder
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../../private/config/db_connection.php';

switch ($method){
    case 'GET': 
        if (isset($_GET['id'])) {
            // Venta puntual
            $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sale) {
                // Traer productos de la venta
                $stmt2 = $db->prepare("SELECT * FROM sales_items WHERE sale_id = ?");
                $stmt2->execute([$sale['id']]);
                $sale['products'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($sale ?: []);
			exit;
        } 
		if (isset($_GET['action']) && $_GET['action'] === 'count') {
            $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : null;
			$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : null;
			$user_name = isset($_GET['user_name']) ? $_GET['user_name'] : null;
			$clerk_user_id = isset($_GET['clerk_user_id']) ? $_GET['clerk_user_id'] : null;

			$where = [];
			$params = [];
			if ($from_date) {
				$where[] = "s.created_at >= ?";
				$params[] = $from_date;
			}
			if ($to_date) {
				$where[] = "s.created_at <= ?";
				$params[] = $to_date;
			}
			if ($user_name) {
				$where[] = "(u.name LIKE ? OR u.last_name LIKE ?)";
				$params[] = "%$user_name%";
				$params[] = "%$user_name%";
			}
			if ($clerk_user_id) {
				$where[] = "s.clerk_user_id = ?";
				$params[] = $clerk_user_id;
			}

			$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
			$sql = "SELECT COUNT(*) as total
					FROM sales s
					LEFT JOIN users u ON s.clerk_user_id = u.clerk_user_id
					$whereSql";
			$stmt = $db->prepare($sql);
			$stmt->execute($params);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			echo json_encode(['total' => $result['total'] ?? 0]);
			exit;
        }
		// Variables para paginación, orden, filtros, búsqueda
		$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
		$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
		$offset = ($page - 1) * $limit;

		$allowedOrderBy = ['created_at', 'cantidad'];
        $order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], $allowedOrderBy) ? $_GET['order_by'] : 'created_at';
		$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

		$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : null;
		$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : null;
		$user_name = isset($_GET['user_name']) ? $_GET['user_name'] : null;
		$clerk_user_id = isset($_GET['clerk_user_id']) ? $_GET['clerk_user_id'] : null; // Si está, es consulta para usuario
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;

		$where = [];
		$params = [];
		if ($from_date) {
			$where[] = "s.created_at >= ?";
			$params[] = $from_date;
		}
		if ($to_date) {
			$where[] = "s.created_at <= ?";
			$params[] = $to_date;
		}
		if ($user_name) {
			$where[] = "(u.name LIKE ? OR u.last_name LIKE ?)";
			$params[] = "%$user_name%";
			$params[] = "%$user_name%";
		}
		if ($clerk_user_id) {
			$where[] = "s.clerk_user_id = ?";
			$params[] = $clerk_user_id;
		}
        // --- Búsqueda global ---
        if ($search) {
            // Busca en usuario, apellido, producto, fecha, y monto exacto
            $where[] = "(u.name LIKE ? OR u.last_name LIKE ? OR s.created_at LIKE ?)";
            $params[] = "%$search%"; // Nombre
            $params[] = "%$search%"; // Apellido
            $params[] = "%$search%"; // Fecha
        }

		$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

		$sql = "SELECT s.*, u.name as user_name, u.last_name as user_last_name,
                (SELECT SUM(si.quantity) FROM sales_items si WHERE si.sale_id = s.id) as cantidad
				FROM sales s
				LEFT JOIN users u ON s.clerk_user_id = u.clerk_user_id
				$whereSql
				ORDER BY $order_by $order
				LIMIT $limit OFFSET $offset";
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Traer los productos para cada venta
		foreach ($sales as &$sale) {
			$stmt2 = $db->prepare("SELECT * FROM sales_items WHERE sale_id = ?");
			$stmt2->execute([$sale['id']]);
			$sale['products'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		}

		echo json_encode($sales);
        exit;

    case 'POST':
        // Registrar venta nueva
        $input = json_decode(file_get_contents('php://input'), true);
        $clerk_user_id = $input['clerk_user_id'] ?? null;
        $cart_id = $input['cart_id'] ?? null;
        if($clerk_user_id && $cart_id){
            $db->beginTransaction();
            try{
                // Obtener el carrito
                $stmt = $db->prepare("SELECT * FROM carts WHERE id = ? AND clerk_user_id = ?");
                $stmt->execute([$cart_id, $clerk_user_id]);
                $cart = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$cart) {
                    throw new Exception('Carrito no encontrado');
                }

                // Obtener los items 
                $stmt = $db->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
                $stmt ->execute([$cart_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if(count($items)==0){
                    throw new Exception('El carrito esta vacio');
                }

                // Validar stock suficiente
                foreach ($items as $item) {
                    $stmtp = $db->prepare("SELECT stock FROM products WHERE id = ?");
                    $stmtp->execute([$item['product_id']]);
                    $prod = $stmtp->fetch(PDO::FETCH_ASSOC);
                    if (!$prod || $prod['stock'] < $item['quantity']) {
                        throw new Exception('Sin stock para el producto: '.$item['product_id']);
                    }
                }

                // Registrar la venta
                $stmt = $db->prepare("INSERT INTO sales (clerk_user_id, total_amount) VALUES (?, ?)");
                $stmt->execute([$clerk_user_id, $cart['total_amount']]);
                $sale_id = $db->lastInsertId();
    
                // Registrar los items y descontar stock 
                foreach ($items as $item) {
                    $product_id = $item['product_id'];
                    $quantity = $item['quantity'];

                    $stmtp = $db->prepare("SELECT price, stock FROM products WHERE id = ?");
                    $stmtp->execute([$product_id]);
                    $prod = $stmtp->fetch(PDO::FETCH_ASSOC);
                    $price = $prod ? $prod['price'] : 0;

                    // Descontar stock
                    $nuevoStock = $prod['stock'] - $quantity;
                    $db->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$nuevoStock, $product_id]);

                    // Guardar item de venta 
                    $stmt2 = $db->prepare("INSERT INTO sales_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt2->execute([$sale_id, $product_id, $quantity, $price]);
                }
                // Vaciar carrito
                $db->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cart_id]);
                $db->prepare("UPDATE carts SET total_amount = 0, updated_at = NOW() WHERE id = ?")->execute([$cart_id]);
                $db->commit();
                echo json_encode(['success' => true, 'sale_id' => $sale_id]);
            }catch (Exception $e){
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }else{
            echo json_encode(['succes' => false, 'error' => 'Faltan datos']);
        }
        break;

    case 'DELETE':
        // Eliminar una venta (por si lo necesito)
        if (isset($_GET['id'])) {
            $sale_id = $_GET['id'];
            $db->prepare("DELETE FROM sales_items WHERE sale_id = ?")->execute([$sale_id]);
            $db->prepare("DELETE FROM sales WHERE id = ?")->execute([$sale_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Falta id']);
        }
        break;
}







