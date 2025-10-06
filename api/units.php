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
 * Maneja la obtención de unidades.
 * Si se pide una sola unidad, primero verifica y actualiza su estado de mantenimiento.
 */
function handleGetUnits($conn, $params) {
    $companyName = $params['company'] ?? '';
    $unitId = $params['id'] ?? '';

    // --- LÓGICA DE ACTUALIZACIÓN DE ESTADO DE MANTENIMIENTO ---
    if (!empty($unitId)) {
        // 1. Obtener los datos de mantenimiento de la unidad
        $stmt = $conn->prepare("SELECT km_ultimo_mantenimiento, intervalo_mantenimiento_km FROM units WHERE id = ?");
        $stmt->bind_param("i", $unitId);
        $stmt->execute();
        $unitMaint = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($unitMaint) {
            // 2. Obtener el KM más alto registrado para esa unidad
            $stmt = $conn->prepare("SELECT MAX(km_fin) as max_km FROM registros_entrada WHERE unit_id = ?");
            $stmt->bind_param("i", $unitId);
            $stmt->execute();
            $lastReg = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $currentKm = $lastReg['max_km'] ?? 0;

            // 3. Comparar y actualizar si es necesario
            if ($currentKm > 0 && ($currentKm - $unitMaint['km_ultimo_mantenimiento']) > $unitMaint['intervalo_mantenimiento_km']) {
                $stmt = $conn->prepare("UPDATE units SET estado_mantenimiento = 'BLOQUEADO' WHERE id = ?");
                $stmt->bind_param("i", $unitId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    // --- FIN DE LA LÓGICA DE ACTUALIZACIÓN ---


    $sql = "SELECT u.id, u.unit_number, c.name as company, u.assigned_operator_id, o.name as assigned_operator_name, u.estado_mantenimiento, u.intervalo_mantenimiento_km
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
    if (!empty($unitId)) {
        $data = $result->fetch_assoc();
    } else {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    http_response_code(200);
    echo json_encode(["success" => true, "data" => $data]);
    $stmt->close();
}

// ... Las funciones handleAddUnit, handleUpdateUnit, handleDeleteUnit permanecen igual ...
function handleAddUnit($conn, $data) {
    $unitNumber = $data['unitNumber'] ?? '';
    $companyName = $data['company'] ?? '';

    if (empty($unitNumber) || empty($companyName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Número de unidad y empresa son requeridos."]);
        return;
    }

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

    $stmt = $conn->prepare("SELECT id FROM units WHERE unit_number = ? AND company_id = ?");
    $stmt->bind_param("si", $unitNumber, $companyId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409); 
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

    $stmt = $conn->prepare("SELECT id FROM units WHERE unit_number = ? AND company_id = ? AND id != ?");
    $stmt->bind_param("sii", $unitNumber, $companyId, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
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
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "No se realizaron cambios."]);
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
