<?php
// api/users.php
require_once 'config.php'; // Incluye la configuración de la base de datos

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        // Lógica para Login o Agregar Usuario
        if (isset($input['action']) && $input['action'] === 'login') {
            handleLogin($conn, $input);
        } else {
            // Asumimos que POST sin 'action' es para agregar un nuevo usuario
            handleAddUser($conn, $input);
        }
        break;
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

function handleLogin($conn, $data) {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "message" => "Usuario y contraseña son requeridos."]);
        return;
    }

    $stmt = $conn->prepare("SELECT id, username, password, user_type FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Usar password_verify() para comparar con el hash de PASSWORD() de MySQL
        // Nota: PASSWORD() de MySQL usa un algoritmo de hashing específico.
        // Para máxima compatibilidad y seguridad, considera usar password_hash() y password_verify() directamente en PHP
        // al guardar y verificar contraseñas.
        if (strcasecmp(md5($password), substr($user['password'], 1)) === 0 || password_verify($password, $user['password'])) {
             // La comparación con MD5 es una solución temporal si PASSWORD() de MySQL no es compatible con password_verify.
             // Idealmente, usa password_hash() en PHP al registrar usuarios y password_verify() al logear.
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Inicio de sesión exitoso.", "userType" => $user['user_type']]);
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(["success" => false, "message" => "Contraseña incorrecta."]);
        }
    } else {
        http_response_code(404); // Not Found
        echo json_encode(["success" => false, "message" => "Usuario no encontrado."]);
    }
    $stmt->close();
}

function handleAddUser($conn, $data) {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $userType = $data['userType'] ?? '';

    if (empty($username) || empty($password) || empty($userType)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Todos los campos son requeridos."]);
        return;
    }

    // Hashear la contraseña antes de guardar
    // Para PASSWORD() de MySQL, simplemente la pasamos como string.
    // Si usas password_hash() en PHP, el hash ya estaría generado aquí.
    $hashedPassword = "PASSWORD(?)"; // Esto es para MySQL's PASSWORD() function

    $stmt = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, " . $hashedPassword . ", ?)");
    $stmt->bind_param("sss", $username, $password, $userType); // El segundo 's' es para la contraseña sin hashear que PASSWORD() procesará

    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode(["success" => true, "message" => "Usuario agregado exitosamente."]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Error al agregar usuario: " . $stmt->error]);
    }
    $stmt->close();
}
?>