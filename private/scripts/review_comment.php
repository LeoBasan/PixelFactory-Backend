<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");

require_once __DIR__ . '/../../private/config/db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Comentarios de una review específica
            if (isset($_GET['review_id'])) {
                $stmt = $db->prepare("
					SELECT c.*, u.name, u.last_name
					FROM review_comments c
					LEFT JOIN users u ON c.clerk_user_id = u.clerk_user_id
					WHERE c.review_id = ?
					ORDER BY c.created_at ASC
				");
                $stmt->execute([$_GET['review_id']]);
                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($comments);
            } else {
                // Todos los comentarios
                $stmt = $db->query("
					SELECT c.*, u.name, u.last_name
					FROM review_comments c
					LEFT JOIN users u ON c.clerk_user_id = u.clerk_user_id
					ORDER BY c.created_at DESC
				");
                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($comments);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            // Validar presencia de campos obligatorios
            if (empty($data['review_id']) || empty($data['clerk_user_id']) || empty($data['content'])) {
                echo json_encode(['success' => false, 'error' => 'Campos obligatorios faltantes']);
                break;
            }
            // Validar longitud razonable del comentario (opcional)
            if (strlen($data['content']) > 1000) {
                echo json_encode(['success' => false, 'error' => 'Comentario demasiado largo']);
                break;
            }
            // Insertar comentario
            $stmt = $db->prepare("INSERT INTO review_comments (review_id, clerk_user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['review_id'],
                $data['clerk_user_id'],
                $data['content']
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            $data = json_decode(file_get_contents("php://input"), true);
			$isAdmin = isset($_GET['admin']) && $_GET['admin'] == '1';
            // Validar que el usuario sea dueño del comentario (o admin, si agregás ese sistema)
            if(!isAdmin){
				$stmt = $db->prepare("SELECT * FROM review_comments WHERE id = ? AND clerk_user_id = ?");
				$stmt->execute([$_GET['id'], $data['clerk_user_id']]);
				if (!$stmt->fetch()) {
					echo json_encode(['success' => false, 'error' => 'No autorizado']);
					break;
				}
			}
            $stmt = $db->prepare("DELETE FROM review_comments WHERE id = ?");
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
