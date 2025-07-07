<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require_once __DIR__ . '/../../private/config/db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];

function userHasPurchased($db, $clerk_user_id, $product_id) {
    $sql = "SELECT 1
            FROM sales s
            JOIN sales_items si ON s.id = si.sale_id
            WHERE s.clerk_user_id = ? AND si.product_id = ?
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$clerk_user_id, $product_id]);
    return (bool) $stmt->fetchColumn();
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Obtener una review específica
                $stmt = $db->prepare("
					SELECT r.*, u.name, u.last_name
					FROM reviews r
					LEFT JOIN users u ON r.clerk_user_id = u.clerk_user_id
					WHERE r.id = ?
				");
                $stmt->execute([$_GET['id']]);
                $review = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($review);
            } else if (isset($_GET['product_id'])) {
                // Todas las reviews de un producto (ordenadas por fecha)
                $stmt = $db->prepare("
					SELECT r.*, u.name, u.last_name
					FROM reviews r
					LEFT JOIN users u ON r.clerk_user_id = u.clerk_user_id
					WHERE r.product_id = ?
					ORDER BY r.created_at DESC
				");
                $stmt->execute([$_GET['product_id']]);
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($reviews);
            } else {
                // Todas las reviews
                $stmt = $db->query("
					SELECT r.*, u.name, u.last_name
					FROM reviews r
					LEFT JOIN users u ON r.clerk_user_id = u.clerk_user_id
					ORDER BY r.created_at DESC
				");
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($reviews);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            // Validar compra
            if (!userHasPurchased($db, $data['clerk_user_id'], $data['product_id'])) {
                echo json_encode(['success' => false, 'error' => 'Sólo usuarios que hayan comprado este producto pueden dejar review.']);
                break;
            }
            // Validar no duplicar review por usuario+producto
            $stmt = $db->prepare("SELECT id FROM reviews WHERE product_id = ? AND clerk_user_id = ?");
            $stmt->execute([$data['product_id'], $data['clerk_user_id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Ya dejaste una review para este producto.']);
                break;
            }
            $stmt = $db->prepare("INSERT INTO reviews (product_id, clerk_user_id, rating, title, content, is_verified_purchase) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                $data['product_id'],
                $data['clerk_user_id'],
                $data['rating'],
                isset($data['title']) ? $data['title']: '', // Lo mando vacio per las dudas
                $data['content']
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            // Validar que el usuario sea dueño de la review
            $stmt = $db->prepare("SELECT * FROM reviews WHERE id = ? AND clerk_user_id = ?");
            $stmt->execute([$_GET['id'], $data['clerk_user_id']]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                break;
            }
            $stmt = $db->prepare("UPDATE reviews SET rating = ?, title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([
                $data['rating'],
                isset($data['title']) ? $data['title']: '', // Aca tambien lo mando vacio si no esta seteado
                $data['content'],
                $_GET['id']
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            $data = json_decode(file_get_contents("php://input"), true);
			$isAdmin = isset($_GET['admin']) && $_GET['admin'] == '1';
            // Validar que el usuario sea dueño de la review
            if(!$isAdmin){
				$stmt = $db->prepare("SELECT * FROM reviews WHERE id = ? AND clerk_user_id = ?");
				$stmt->execute([$_GET['id'], $data['clerk_user_id']]);
				if (!$stmt->fetch()) {
					echo json_encode(['success' => false, 'error' => 'No autorizado']);
					break;
				}
			}
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'OPTIONS':
            http_response_code(200);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la consulta: ' . $e->getMessage()]);
} catch (Exception $e){
    echo json_encode(['success' => false, 'error' => 'Error: '.$e->getMessage()]);
}
?>
