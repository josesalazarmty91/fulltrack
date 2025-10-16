<?php
// api/assignments.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGetAssignments($conn, $_GET);
        break;
    case 'POST':
        handleSetAssignment($conn, $input);
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

/**
 * Obtiene todas las unidades y el nombre del operador asignado, con filtros opcionales.
 */
function handleGetAssignments($conn, $params) {
    $sql = "SELECT u.id as unit_id, u.unit_number, c.name as company_name, u.assigned_operator_id, o.name as assigned_operator_name
            FROM units u
            JOIN companies c ON u.company_id = c.id
            LEFT JOIN operators o ON u.assigned_operator_id = o.id";

    $whereClauses = [];
    $bindTypes = '';
    $bindValues = [];

    if (!empty($params['unitNumber'])) {
        $whereClauses[] = "u.unit_number LIKE ?";
        $bindTypes .= 's';
        $bindValues[] = '%' . $params['unitNumber'] . '%';
    }
    if (!empty($params['companyName'])) {
        $whereClauses[] = "c.name = ?";
        $bindTypes .= 's';
        $bindValues[] = $params['companyName'];
    }
    if (!empty($params['operatorName'])) {
        $whereClauses[] = "o.name LIKE ?";
        $bindTypes .= 's';
        $bindValues[] = '%' . $params['operatorName'] . '%';
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(' AND ', $whereClauses);
    }
    
    $sql .= " ORDER BY c.name, u.unit_number ASC";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al preparar la consulta: " . $conn->error]);
        return;
    }

    if (!empty($bindValues)) {
        $stmt->bind_param($bindTypes, ...$bindValues);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $assignments = $result->fetch_all(MYSQLI_ASSOC);
        http_response_code(200);
        echo json_encode(["success" => true, "data" => $assignments]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al obtener las asignaciones: " . $conn->error]);
    }
    $stmt->close();
}

/**
 * Asigna un operador a una unidad, con validación para evitar duplicados.
 */
function handleSetAssignment($conn, $data) {
    $unitId = $data['unitId'] ?? null;
    $operatorId = $data['operatorId'] ?? null;

    if (empty($unitId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "El ID de la unidad es requerido."]);
        return;
    }
    
    $operatorValue = (empty($operatorId) || $operatorId === 'null') ? NULL : $operatorId;

    // --- VALIDACIÓN: Verificar si el operador ya está asignado a OTRA unidad ---
    if ($operatorValue !== NULL) {
        $stmt_check = $conn->prepare("SELECT u.unit_number FROM units u WHERE u.assigned_operator_id = ? AND u.id != ?");
        $stmt_check->bind_param("ii", $operatorValue, $unitId);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($row = $result_check->fetch_assoc()) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Este operador ya está asignado a la unidad " . $row['unit_number'] . "."]);
            $stmt_check->close();
            return;
        }
        $stmt_check->close();
    }
    // --- FIN DE LA VALIDACIÓN ---

    $stmt = $conn->prepare("UPDATE units SET assigned_operator_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $operatorValue, $unitId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Asignación actualizada correctamente."]);
        } else {
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

