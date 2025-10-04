<?php
// api/operators.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGetOperators($conn, $_GET); // Pass GET params for optional filtering
        break;
    case 'POST':
        handleAddOperator($conn, $input);
        break;
    case 'PUT':
        handleUpdateOperator($conn, $input);
        break;
    case 'DELETE':
        handleDeleteOperator($conn, $_GET);
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

function handleGetOperators($conn, $params) {
    $companyName = $params['company'] ?? '';

    $sql = "SELECT o.id, o.name, c.name as company FROM operators o JOIN companies c ON o.company_id = c.id";
    $bindParams = [];
    $bindTypes = "";

    if (!empty($companyName)) {
        $sql .= " WHERE c.name = ?";
        $bindParams[] = $companyName;
        $bindTypes .= "s";
    }
    $sql .= " ORDER BY o.name ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($bindParams)) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $operators = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $operators[] = $row;
        }
    }
    http_response_code(200);
    echo json_encode(["success" => true, "data" => $operators]);
    $stmt->close();
}

function handleAddOperator($conn, $data) {
    $operatorName = $data['name'] ?? '';
    $companyName = $data['company'] ?? '';

    if (empty($operatorName) || empty($companyName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Nombre del operador y empresa son requeridos."]);
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

    // Verificar si el operador ya existe para esa empresa
    $stmt = $conn->prepare("SELECT id FROM operators WHERE name = ? AND company_id = ?");
    $stmt->bind_param("si", $operatorName, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El operador '" . $operatorName . "' ya existe para esta empresa."]);
        $stmt->close();
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO operators (name, company_id) VALUES (?, ?)");
    $stmt->bind_param("si", $operatorName, $companyId);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Operador agregado exitosamente.", "id" => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al agregar operador: " . $stmt->error]);
    }
    $stmt->close();
}

function handleUpdateOperator($conn, $data) {
    $id = $data['id'] ?? '';
    $operatorName = $data['name'] ?? '';
    $companyName = $data['company'] ?? '';

    if (empty($id) || empty($operatorName) || empty($companyName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID, nombre del operador y empresa son requeridos."]);
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

    // Verificar si el nuevo nombre de operador ya existe para otra empresa (excepto el que estamos editando)
    $stmt = $conn->prepare("SELECT id FROM operators WHERE name = ? AND company_id = ? AND id != ?");
    $stmt->bind_param("sii", $operatorName, $companyId, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El operador '" . $operatorName . "' ya está en uso para esta empresa."]);
        $stmt->close();
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE operators SET name = ?, company_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $operatorName, $companyId, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Operador actualizado exitosamente."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Operador no encontrado o no se realizaron cambios."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al actualizar operador: " . $stmt->error]);
    }
    $stmt->close();
}

function handleDeleteOperator($conn, $data) {
    $id = $data['id'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID de operador es requerido."]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM operators WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Operador eliminado exitosamente."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Operador no encontrado."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al eliminar operador: " . $stmt->error]);
    }
    $stmt->close();
}
?>