<?php
// api/registrations.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'config.php'; // Incluye la configuración de la base de datos

$method = $_SERVER['REQUEST_METHOD'];

// El switch determina si se está pidiendo datos (GET) o enviando datos (POST)
switch ($method) {
    case 'GET':
        // NUEVA LÓGICA: Verificar si se está pidiendo el último KM para una unidad
        if (isset($_GET['lastKmForUnit'])) {
            handleGetLastKmForUnit($conn, $_GET['lastKmForUnit']);
        } else {
            // Lógica existente para obtener la bitácora
            handleGetRegistrations($conn, $_GET);
        }
        break;
    case 'POST':
        // Llama a la función para agregar un nuevo registro
        $input = json_decode(file_get_contents('php://input'), true);
        handleAddRegistration($conn, $input);
        break;
    default:
        http_response_code(405); // Método no permitido
        echo json_encode(["success" => false, "message" => "Método no permitido."]);
        break;
}

/**
 * NUEVA FUNCIÓN: Obtiene el km_fin del último registro para una unidad específica.
 * @param mysqli $conn Conexión a la base de datos.
 * @param string $unitNumber El número de la unidad a consultar.
 */
function handleGetLastKmForUnit($conn, $unitNumber) {
    // 1. Obtener el ID de la unidad a partir de su número
    $stmt = $conn->prepare("SELECT id FROM units WHERE unit_number = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al preparar la consulta de unidad."]);
        return;
    }
    $stmt->bind_param("s", $unitNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No se encontró la unidad, pero no es un error fatal.
        // Puede ser el primer registro de esta unidad.
        echo json_encode(["success" => true, "lastKmFin" => null]);
        $stmt->close();
        return;
    }
    
    $unit = $result->fetch_assoc();
    $unitId = $unit['id'];
    $stmt->close();

    // 2. Buscar el último registro para esa unidad y obtener el km_fin
    $stmt = $conn->prepare("SELECT km_fin FROM registros_entrada WHERE unit_id = ? ORDER BY timestamp DESC LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al preparar la consulta de registro."]);
        return;
    }
    $stmt->bind_param("i", $unitId);
    $stmt->execute();
    $result = $stmt->get_result();

    $lastKmFin = null;
    if ($row = $result->fetch_assoc()) {
        $lastKmFin = $row['km_fin'];
    }

    http_response_code(200);
    echo json_encode(["success" => true, "lastKmFin" => $lastKmFin]);
    $stmt->close();
}


/**
 * Maneja la obtención de registros, aplicando los filtros de manera correcta.
 * @param mysqli $conn Conexión a la base de datos.
 * @param array $params Parámetros GET para filtrar.
 */
function handleGetRegistrations($conn, $params) {
    // Consulta SQL base con los JOINs y el nuevo campo 'photo_path'
    $sql = "SELECT r.id, r.bitacora_number, r.km_hubodometro, r.km_inicio, r.km_fin, r.km_recorridos,
                   r.litros_diesel, r.litros_auto, r.litros_urea, r.seals, r.litros_totalizador, r.photo_path,
                   r.timestamp, c.name as company, u.unit_number, o.name as operator_name
            FROM registros_entrada r
            JOIN companies c ON r.company_id = c.id
            JOIN units u ON r.unit_id = u.id
            JOIN operators o ON r.operator_id = o.id
            WHERE 1=1"; // Cláusula base para añadir filtros fácilmente

    $types = '';
    $values = [];

    // Construir la consulta dinámicamente según los filtros recibidos
    if (!empty($params['unitNumber'])) {
        $sql .= " AND u.unit_number = ?";
        $types .= 's';
        $values[] = $params['unitNumber'];
    }
    if (!empty($params['startDate'])) {
        $sql .= " AND r.timestamp >= ?";
        $types .= 's';
        $values[] = $params['startDate'] . ' 00:00:00';
    }
    if (!empty($params['endDate'])) {
        $sql .= " AND r.timestamp <= ?";
        $types .= 's';
        $values[] = $params['endDate'] . ' 23:59:59';
    }

    $sql .= " ORDER BY r.timestamp DESC";

    try {
        $stmt = $conn->prepare($sql);

        // Si hay parámetros, los vinculamos de forma segura
        if ($types) {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        
        $registrations = $result->fetch_all(MYSQLI_ASSOC);

        http_response_code(200);
        echo json_encode(["success" => true, "data" => $registrations]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error en la consulta: " . $e->getMessage()]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Maneja la adición de un nuevo registro de entrada.
 * @param mysqli $conn Conexión a la base de datos.
 * @param array $data Datos del registro a añadir.
 */
function handleAddRegistration($conn, $data) {
    // Recoger datos del JSON recibido
    $companyName = $data['company'] ?? '';
    $unitNumber = $data['unitNumber'] ?? '';
    $operatorName = $data['operatorName'] ?? '';
    $bitacoraNumber = $data['bitacoraNumber'] ?? null;
    $kmHubodometro = $data['kmHubodometro'] ?? 0;
    $kmInicio = $data['kmInicio'] ?? 0;
    $kmFin = $data['kmFin'] ?? 0;
    $kmRecorridos = $data['kmRecorridos'] ?? 0;
    $litrosDiesel = $data['litrosTotales'] ?? 0;
    $litrosAuto = $data['litrosAuto'] ?? 0;
    $litrosUrea = $data['litrosUrea'] ?? 0;
    $seals = json_encode($data['seals'] ?? []);
    $litrosTotalizador = $data['litrosTotalizador'] ?? 0;
    $photoPath = $data['photoPath'] ?? null; // <-- NUEVO CAMPO
    $timestamp = date('Y-m-d H:i:s');

    // Función auxiliar para obtener IDs a partir de nombres
    function getIdFromName($conn, $table, $column, $name) {
        $stmt = $conn->prepare("SELECT id FROM {$table} WHERE {$column} = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['id'];
        }
        $stmt->close();
        return null;
    }

    // Obtener los IDs correspondientes a los nombres
    $companyId = getIdFromName($conn, 'companies', 'name', $companyName);
    $unitId = getIdFromName($conn, 'units', 'unit_number', $unitNumber);
    $operatorId = getIdFromName($conn, 'operators', 'name', $operatorName);

    // Verificar que todos los IDs se encontraron
    if (!$companyId || !$unitId || !$operatorId) {
        http_response_code(404);
        $missing = [];
        if (!$companyId) $missing[] = "Empresa '$companyName'";
        if (!$unitId) $missing[] = "Unidad '$unitNumber'";
        if (!$operatorId) $missing[] = "Operador '$operatorName'";
        echo json_encode(["success" => false, "message" => "No se encontraron los siguientes datos: " . implode(', ', $missing) . ". Asegúrate de que estén registrados."]);
        return;
    }

    // Insertar el nuevo registro en la tabla con la columna 'photo_path'
    $stmt = $conn->prepare("INSERT INTO registros_entrada (company_id, unit_id, operator_id, bitacora_number, km_hubodometro, km_inicio, km_fin, km_recorridos, litros_diesel, litros_auto, litros_urea, seals, litros_totalizador, photo_path, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // El tipo para bitacora_number es 's' (string) para mayor flexibilidad
    $stmt->bind_param("iiisddddddsdsss", $companyId, $unitId, $operatorId, $bitacoraNumber, $kmHubodometro, $kmInicio, $kmFin, $kmRecorridos, $litrosDiesel, $litrosAuto, $litrosUrea, $seals, $litrosTotalizador, $photoPath, $timestamp);

    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode(["success" => true, "message" => "Registro guardado exitosamente."]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Error al guardar el registro: " . $stmt->error]);
    }
    $stmt->close();
}

$conn->close();
?>
