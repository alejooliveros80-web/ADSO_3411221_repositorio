<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo acepta solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// El asociado debe haber iniciado sesión.
if (!isset($_SESSION["rol"], $_SESSION["documentos"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión primero"]);
    exit;
}

// Este formulario es para solicitudes de asociados.
if (strtolower($_SESSION["rol"]) !== "asociado") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un asociado puede solicitar un préstamo"]);
    exit;
}

// Lee y valida el JSON enviado por el controlador JavaScript.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
    exit;
}

$documento = (string)($_SESSION["documentos"] ?? "");
$fechaPrestamo = $datos["fechaPrestamo"] ?? null;
$valorPrestamo = $datos["valorPrestamo"] ?? null;
$tasaInteres = $datos["tasaInteres"] ?? null;

$numeroCuotas = filter_var(
    $datos["numeroCuotas"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

// Comprueba que el documento contenga solo números.
if (!is_string($documento) || !preg_match('/^\d+$/', trim($documento))) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "El documento del asociado no es válido"]);
    exit;
}

// Comprueba que la fecha sea una fecha real con formato año-mes-día.
$fechaValida = is_string($fechaPrestamo)
    ? DateTime::createFromFormat("!Y-m-d", $fechaPrestamo)
    : false;

$fechaCorrecta = $fechaValida &&
    $fechaValida->format("Y-m-d") === $fechaPrestamo;

// Valida el monto, la tasa y el número de cuotas.
if (
    !$fechaCorrecta ||
    !is_numeric($valorPrestamo) ||
    (float)$valorPrestamo <= 0 ||
    !is_numeric($tasaInteres) ||
    (float)$tasaInteres < 0 ||
    (float)$tasaInteres > 999.99 ||
    (float)$tasaInteres !== round((float)$tasaInteres, 2) ||
    $numeroCuotas === false
) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Revisa la fecha, el monto, la tasa y las cuotas"]);
    exit;
}

try {
    // Guarda la solicitud mediante el modelo Prestamo.
    require_once __DIR__ . "/prestamo.php";
    $prestamo = new Prestamo();
    $resultado = $prestamo->guardarSolicitud($documento, [
        "valorPrestamo" => $valorPrestamo,
        "tasaInteres" => $tasaInteres,
        "numeroCuotas" => $numeroCuotas,
        "fechaPrestamo" => $fechaPrestamo
    ]);

    http_response_code($resultado["status"]);
    if ($resultado["status"] !== 201) {
        echo json_encode(["success" => false, "mensaje" => $resultado["mensaje"]]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "mensaje" => "Solicitud enviada para aprobación",
        "idPrestamo" => $resultado["idPrestamo"]
    ]);
} catch (PDOException $error) {
    error_log("Error al guardar el préstamo: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "No se pudo guardar el préstamo"]);
}
