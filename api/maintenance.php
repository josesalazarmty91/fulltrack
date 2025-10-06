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
        }
        break;
    case 'POST':
        if ($action === 'register_service') {
            registerService($conn, $input);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

function getUnitsForMaintenance($conn) {
    $sql = "SELECT id, unit_number, intervalo_mantenimiento_km, estado_mantenimiento FROM units ORDER BY unit_number ASC";
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
        // 1. Insertar en el historial de mantenimientos
        $stmt1 = $conn->prepare("INSERT INTO mantenimientos (unit_id, fecha, kilometraje_servicio, tipo_mantenimiento, notas) VALUES (?, ?, ?, ?, ?)");
        $stmt1->bind_param("isdss", $unitId, $fecha, $kilometraje, $tipo, $notas);
        $stmt1->execute();
        $stmt1->close();

        // 2. Actualizar la tabla de unidades
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

$conn->close();
?>
