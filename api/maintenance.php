<?php
// api/maintenance.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? null;

switch ($method) {
    case 'GET':
        if ($action === 'units_for_maintenance') {
            getUnitsForMaintenance($conn);
        } elseif ($action === 'get_blocked_units') {
            getBlockedUnits($conn);
        }
        break;
    case 'POST':
        if ($action === 'register_service') {
            registerService($conn, $input);
        } elseif ($action === 'generate_token') {
            generateAuthorizationToken($conn, $input);
        } elseif ($action === 'validate_token') { // NUEVO ENDPOINT PARA VALIDAR
            validateAuthorizationToken($conn, $input);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

function getUnitsForMaintenance($conn) {
    $sql = "SELECT id, unit_number, intervalo_mantenimiento_km, estado_mantenimiento FROM units ORDER BY CAST(unit_number AS UNSIGNED) ASC";
    $result = $conn->query($sql);
    $units = $result->fetch_all(MYSQLI_ASSOC);
    http_response_code(200);
    echo json_encode(["success" => true, "data" => $units]);
}

function getBlockedUnits($conn) {
    $sql = "SELECT id, unit_number FROM units WHERE estado_mantenimiento = 'BLOQUEADO' ORDER BY CAST(unit_number AS UNSIGNED) ASC";
    $result = $conn->query($sql);
    $units = $result->fetch_all(MYSQLI_ASSOC);
    http_response_code(200);
    echo json_encode(["success" => true, "data" => $units]);
}

function registerService($conn, $data) {
    $unitId = $data['unitId'] ?? null;
    $kilometraje = $data['kilometraje'] ?? null;
    $fecha = $data['fecha'] ?? null;
    $tipo = $data['tipo'] ?? 'Preventivo';
    $notas = $data['notas'] ?? '';

    if (!$unitId || !$kilometraje || !$fecha) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos requeridos."]);
        return;
    }

    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("INSERT INTO mantenimientos (unit_id, fecha, kilometraje_servicio, tipo_mantenimiento, notas) VALUES (?, ?, ?, ?, ?)");
        $stmt1->bind_param("isdss", $unitId, $fecha, $kilometraje, $tipo, $notas);
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("UPDATE units SET km_ultimo_mantenimiento = ?, estado_mantenimiento = 'OK' WHERE id = ?");
        $stmt2->bind_param("di", $kilometraje, $unitId);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Mantenimiento registrado y unidad actualizada exitosamente."]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error en la transacción: " . $e->getMessage()]);
    }
}

function generateAuthorizationToken($conn, $data) {
    $unitId = $data['unitId'] ?? null;
    $supervisorId = $data['supervisorId'] ?? 1; 

    if (!$unitId) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "El ID de la unidad es requerido."]);
        return;
    }

    $token = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiration = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $conn->prepare("INSERT INTO autorizacion_tokens (token, unit_id, supervisor_id, fecha_expiracion) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siis", $token, $unitId, $supervisorId, $expiration);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Token generado exitosamente.", "token" => $token]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al generar el token: " . $stmt->error]);
    }
    $stmt->close();
}

// --- NUEVA FUNCIÓN PARA VALIDAR EL TOKEN ---
function validateAuthorizationToken($conn, $data) {
    $unitId = $data['unitId'] ?? null;
    $token = $data['token'] ?? null;

    if (!$unitId || !$token) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos para la validación."]);
        return;
    }

    $currentTime = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT id FROM autorizacion_tokens WHERE unit_id = ? AND token = ? AND fecha_expiracion > ? AND usado = 0");
    $stmt->bind_param("iss", $unitId, $token, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $tokenId = $row['id'];
        $stmt->close();

        // Marcar el token como usado para que no se pueda volver a utilizar
        $stmt_update = $conn->prepare("UPDATE autorizacion_tokens SET usado = 1 WHERE id = ?");
        $stmt_update->bind_param("i", $tokenId);
        $stmt_update->execute();
        $stmt_update->close();

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Token válido. Acceso concedido."]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Token inválido, expirado o ya utilizado."]);
        $stmt->close();
    }
}

$conn->close();
?>

