<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo acepta solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// El usuario debe tener sesión y pertenecer a un rol permitido.
if (!isset($_SESSION["rol"], $_SESSION["documentos"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión primero"]);
    exit;
}

$rolUsuario = strtolower($_SESSION["rol"]);

if (!in_array($rolUsuario, ["admin", "asociado"], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "No tienes permiso para registrar pagos"]);
    exit;
}

// Lee los datos JSON enviados por pagos.js.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
    exit;
}

$documento = $rolUsuario === "asociado" ? (string)$_SESSION["documentos"] : ($datos["documentoAsociado"] ?? "");
$fechaPago = $datos["fechaPago"] ?? null;
$valorCuota = $datos["valorCuota"] ?? null;

// Comprueba que el ID del préstamo sea un entero positivo.
$idPrestamo = filter_var(
    $datos["idPrestamo"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

// Comprueba que el documento contenga solo números.
if (!is_string($documento) || !preg_match('/^\d+$/', trim($documento))) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "El documento no es válido"]);
    exit;
}

// Comprueba que la fecha exista y use el formato año-mes-día.
$fechaValida = is_string($fechaPago)
    ? DateTime::createFromFormat("!Y-m-d", $fechaPago)
    : false;

$fechaCorrecta = $fechaValida &&
    $fechaValida->format("Y-m-d") === $fechaPago;

// Valida el ID del préstamo, la fecha y el valor del pago.
if (
    $idPrestamo === false ||
    !$fechaCorrecta ||
    !is_numeric($valorCuota) ||
    (float)$valorCuota <= 0 ||
    (float)$valorCuota > 9999999999.99 ||
    (float)$valorCuota !== round((float)$valorCuota, 2)
) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Revisa el préstamo, la fecha y el valor"]);
    exit;
}

try {
    // Registra el pago mediante el modelo Pago.
    require_once __DIR__ . "/pago.php";
    $pago = new Pago();
    $resultado = $pago->registrar($documento, $idPrestamo, $fechaPago, $valorCuota);

    http_response_code($resultado["status"]);
    echo json_encode([
        "success" => $resultado["status"] === 201,
        "mensaje" => $resultado["mensaje"]
    ]);
} catch (PDOException $error) {
    error_log("Error al guardar el pago: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "mensaje" => "No se pudo guardar el pago"
    ]);
}
