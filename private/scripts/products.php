<?php
// Habilita CORS para que Angular pueda acceder
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Conexión segura usando archivo privado
require_once __DIR__ . '/../../private/config/db_connection.php';

$method=$_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($producto);
            } else if(isset($_GET['search'])){
                // Búsqueda parcial por name, brand y category
                $search = '%' . $_GET['search'] . '%';
                $stmt = $db->prepare("SELECT * FROM products WHERE name LIKE ? OR brand LIKE ? OR category LIKE ? LIMIT 10");
                $stmt->execute([$search, $search, $search]);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($products);
            }else{
                $stmt = $db->query("SELECT * FROM products");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($products);  
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $db->prepare("INSERT INTO products (id, name, price, brand, description, category, stock, img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['id'],
                $data['name'],
                $data['price'],
                $data['brand'],
                $data['description'],
                $data['category'],
                $data['stock'],
                $data['img']
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $db->prepare("
            UPDATE products 
            SET name = ?, description = ?, img = ?, price = ?, brand = ?, category = ?, stock = ?
            WHERE id = ? ");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['img'],
                $data['price'],
                $data['brand'],
                $data['category'],
                $data['stock'],
                $_GET['id']
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            $productId= $_GET['id'];
            // 1. Poner el stock en cero (no borrar el producto)
            $stmt = $db->prepare("UPDATE products SET stock = 0 WHERE id = ?");
            $stmt->execute([$productId]);

            // 2. Eliminar el producto de todos los carritos
            $stmt = $db->prepare("DELETE FROM cart_items WHERE product_id = ?");
            $stmt->execute([$productId]);
            
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
