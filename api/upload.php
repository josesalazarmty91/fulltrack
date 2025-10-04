<?php
// api/upload.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

// Verificar que se haya subido un archivo
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMessage = 'Error desconocido al subir el archivo.';
    if (isset($_FILES['photo']['error'])) {
        switch ($_FILES['photo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'El archivo es demasiado grande.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'El archivo se subió solo parcialmente.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'No se subió ningún archivo.';
                break;
        }
    }
    echo json_encode(["success" => false, "message" => $errorMessage]);
    exit();
}

// --- Validación del archivo ---
$file = $_FILES['photo'];
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

// Validar tamaño
if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "El archivo excede el tamaño máximo permitido de 5 MB."]);
    exit();
}

// Validar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Tipo de archivo no permitido. Sube solo imágenes JPG, PNG o GIF."]);
    exit();
}

// --- Guardar el archivo ---
$uploadDir = 'uploads/';
// Crear el directorio si no existe
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo crear el directorio de subida."]);
        exit();
    }
}

// Generar un nombre de archivo único
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg'; // Usar jpg como extensión por defecto
$uniqueFileName = uniqid('photo_', true) . '.' . $fileExtension;
$uploadFilePath = $uploadDir . $uniqueFileName;

// Mover el archivo subido al directorio de destino
if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
    http_response_code(201);
    echo json_encode(["success" => true, "filePath" => $uploadFilePath]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al guardar el archivo en el servidor."]);
}
?>
