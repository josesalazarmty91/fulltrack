<?php
// api/units.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGetUnits($conn, $_GET);
        break;
    case 'POST':
        handleAddUnit($conn, $input);
        break;
    case 'PUT':
        handleUpdateUnit($conn, $input);
        break;
    case 'DELETE':
        handleDeleteUnit($conn, $_GET);
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

/**
 * MODIFICADO: Ahora también devuelve el operador asignado.
 * Maneja la obtención de unidades, aplicando filtros por empresa o por ID de unidad.
 */
function handleGetUnits($conn, $params) {
    $companyName = $params['company'] ?? '';
    $unitId = $params['id'] ?? '';

    // MODIFICADO: Se añade LEFT JOIN para obtener el nombre del operador asignado
    $sql = "SELECT u.id, u.unit_number, c.name as company, u.assigned_operator_id, o.name as assigned_operator_name
            FROM units u 
            JOIN companies c ON u.company_id = c.id
            LEFT JOIN operators o ON u.assigned_operator_id = o.id";
    
    $bindParams = [];
    $bindTypes = "";

    if (!empty($companyName)) {
        $sql .= " WHERE c.name = ?";
        $bindParams[] = $companyName;
        $bindTypes .= "s";
    } elseif (!empty($unitId)) {
        $sql .= " WHERE u.id = ?";
        $bindParams[] = $unitId;
        $bindTypes .= "i";
    }
    
    $sql .= " ORDER BY u.unit_number ASC";

    $stmt = $conn->prepare($sql);

    if (!empty($bindParams)) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    // Si se busca por ID, devolver un solo objeto. Si no, un array.
    if (!empty($unitId)) {
        $data = $result->fetch_assoc();
    } else {
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    }
    
    http_response_code(200);
    echo json_encode(["success" => true, "data" => $data]);
    $stmt->close();
}


function handleAddUnit($conn, $data) {
    $unitNumber = $data['unitNumber'] ?? '';
    $companyName = $data['company'] ?? '';

    if (empty($unitNumber) || empty($companyName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Número de unidad y empresa son requeridos."]);
        return;
    }

    // Obtener company_id
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = ?");
    $stmt->bind_param("s", $companyName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Empresa no encontrada."]);
        $stmt->close();
        return;
    }
    $companyId = $result->fetch_assoc()['id'];
    $stmt->close();

    // Verificar si la unidad ya existe para esa empresa
    $stmt = $conn->prepare("SELECT id FROM units WHERE unit_number = ? AND company_id = ?");
    $stmt->bind_param("si", $unitNumber, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(["success" => false, "message" => "La unidad " . $unitNumber . " ya existe para esta empresa."]);
        $stmt->close();
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO units (unit_number, company_id) VALUES (?, ?)");
    $stmt->bind_param("si", $unitNumber, $companyId);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Unidad agregada exitosamente.", "id" => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al agregar unidad: " . $stmt->error]);
    }
    $stmt->close();
}

function handleUpdateUnit($conn, $data) {
    $id = $data['id'] ?? '';
    $unitNumber = $data['unitNumber'] ?? '';
    $companyName = $data['company'] ?? '';

    if (empty($id) || empty($unitNumber) || empty($companyName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID, número de unidad y empresa son requeridos."]);
        return;
    }

    // Obtener company_id
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = ?");
    $stmt->bind_param("s", $companyName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Empresa no encontrada."]);
        $stmt->close();
        return;
    }
    $companyId = $result->fetch_assoc()['id'];
    $stmt->close();

    // Verificar si el nuevo número de unidad ya existe para otra unidad (excepto la que estamos editando)
    $stmt = $conn->prepare("SELECT id FROM units WHERE unit_number = ? AND company_id = ? AND id != ?");
    $stmt->bind_param("sii", $unitNumber, $companyId, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El número de unidad " . $unitNumber . " ya está en uso para esta empresa."]);
        $stmt->close();
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE units SET unit_number = ?, company_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $unitNumber, $companyId, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Unidad actualizada exitosamente."]);
        } else {
            http_response_code(404); // Not Found or No Change
            echo json_encode(["success" => false, "message" => "Unidad no encontrada o no se realizaron cambios."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al actualizar unidad: " . $stmt->error]);
    }
    $stmt->close();
}

function handleDeleteUnit($conn, $data) {
    $id = $data['id'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID de unidad es requerido."]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM units WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Unidad eliminada exitosamente."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Unidad no encontrada."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al eliminar unidad: " . $stmt->error]);
    }
    $stmt->close();
}
?>
