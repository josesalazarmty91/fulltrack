<?php
// api/assignments.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // Obtiene una lista de todas las unidades con su operador asignado (si lo tienen)
        handleGetAssignments($conn);
        break;
    case 'POST':
        // Crea o actualiza una asignación
        handleSetAssignment($conn, $input);
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

/**
 * Obtiene todas las unidades y el nombre del operador asignado.
 */
function handleGetAssignments($conn) {
    $sql = "SELECT u.id as unit_id, u.unit_number, c.name as company_name, u.assigned_operator_id, o.name as assigned_operator_name
            FROM units u
            JOIN companies c ON u.company_id = c.id
            LEFT JOIN operators o ON u.assigned_operator_id = o.id
            ORDER BY c.name, u.unit_number ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $assignments = $result->fetch_all(MYSQLI_ASSOC);
        http_response_code(200);
        echo json_encode(["success" => true, "data" => $assignments]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al obtener las asignaciones: " . $conn->error]);
    }
}

/**
 * Asigna un operador a una unidad.
 */
function handleSetAssignment($conn, $data) {
    $unitId = $data['unitId'] ?? null;
    $operatorId = $data['operatorId'] ?? null;

    if (empty($unitId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "El ID de la unidad es requerido."]);
        return;
    }

    // Si el operatorId es "null" o 0, lo tratamos como una des-asignación.
    $operatorValue = (empty($operatorId) || $operatorId === 'null') ? NULL : $operatorId;

    $stmt = $conn->prepare("UPDATE units SET assigned_operator_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $operatorValue, $unitId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Asignación actualizada correctamente."]);
        } else {
            // No es un error si no se afectaron filas, puede que ya estuviera asignado.
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "No se realizaron cambios en la asignación."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al actualizar la asignación: " . $stmt->error]);
    }
    $stmt->close();
}

$conn->close();
?>
