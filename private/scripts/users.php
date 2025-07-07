<?php
// Habilita CORS para que Angular pueda acceder
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Conexión segura usando archivo privado
require_once __DIR__ . '/../../private/config/db_connection.php';

$method=$_SERVER['REQUEST_METHOD'];

try{
    switch($method){
        // ----------- Consulta de usuarios -----------
        case 'GET':
            if(isset($_GET['id'])){
                // Busco por id de la db (dudo que lo use, but per las dudas)
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if($user){
                    echo json_encode([
                        'success' => true,
                        'id' => $user['id'],
                        'clerk_user_id' => $user['clerk_user_id'],
                        'address' => $user['address'],
                        'phone_number' => $user['phone_number'],
                        'dni' => $user['dni'],
                        'name' => $user['name'],
                        'last_name' => $user['last_name']
                    ]);
                }else{
                    echo json_encode(null);
                }
            }else if(isset($_GET['clerk_user_id'])){
                // Busco por clerk_user_id 
                $stmt = $db->prepare("SELECT * FROM users WHERE clerk_user_id = ?");
                $stmt->execute([$_GET['clerk_user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if($user){
                    echo json_encode([
                        'success' => true,
                        'id' => $user['id'],
                        'clerk_user_id' => $user['clerk_user_id'],
                        'address' => $user['address'],
                        'phone_number' => $user['phone_number'],
                        'dni' => $user['dni'],
                        'name' => $user['name'],
                        'last_name' => $user['last_name']
                    ]);
                }else{
                    echo json_encode(null);
                }
            }
            // Podria agregar un metodo para traer todos los users, pero muy pesado
            break;

        // ----------- Armo el user -----------
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            // Validaciones mínimas
            if (
                !isset($data['clerk_user_id']) 
            ) {
                echo json_encode(['success' => false, 'error' => 'Faltan datos obligatorios']);
                break;
            }
            // Revisar si ya existe (único por clerk_user_id)
            $stmt = $db->prepare("SELECT id FROM users WHERE clerk_user_id = ?");
            $stmt->execute([$data['clerk_user_id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Usuario ya existe']);
                break;
            }
            $stmt = $db->prepare("INSERT INTO users (clerk_user_id, name, last_name, phone_number, address, dni) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['clerk_user_id'],
                $data['name'] ?? null,
                $data['last_name'] ?? null,
                $data['phone_number'] ?? null,
                $data['address'] ?? null,
                $data['dni'] ?? null
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;
        
        // ----------- Actualizar usuario -----------
        case 'PUT':
            // Por default, busco por clerk_user_id (más seguro)
            parse_str($_SERVER['QUERY_STRING'], $query);
            $clerk_user_id = $query['clerk_user_id'] ?? null;
            if (!$clerk_user_id) {
                echo json_encode(['success' => false, 'error' => 'clerk_user_id requerido']);
                break;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            $stmt = $db->prepare("
                UPDATE users SET 
                    name = ?, 
                    last_name = ?, 
                    phone_number = ?, 
                    address = ?, 
                    dni = ?
                WHERE clerk_user_id = ?
            ");
            $stmt->execute([
                $data['name'] ?? null,
                $data['last_name'] ?? null,
                $data['phone_number'] ?? null,
                $data['address'] ?? null,
                $data['dni'] ?? null,
                $clerk_user_id
            ]);
            echo json_encode(['success' => true]);
            break;
        // ----------- ELIMINAR USUARIO (por id interno) -----------
        case 'DELETE':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            $userId = $_GET['id'];
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
            break;

        // ----------- PRE-OPCIONES -----------
        case 'OPTIONS':
            http_response_code(200);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            break;
    }
}catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la consulta: ' . $e->getMessage()]);
} catch (Exception $e){
    echo json_encode(['success' => false, 'error' => 'Error: '.$e->getMessage()]);
}

?>