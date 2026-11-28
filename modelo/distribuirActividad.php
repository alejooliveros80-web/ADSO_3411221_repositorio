<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo un administrador puede cerrar una actividad y repartir el recaudo.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

if (strtolower($_SESSION["rol"] ?? "") !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un administrador puede distribuir el recaudo"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);
$idActividad = filter_var(
    is_array($datos) ? ($datos["idActividad"] ?? null) : null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($idActividad === false) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "El identificador de la actividad no es válido"]);
    exit;
}

try {
    // El modelo calcula y guarda las partes dentro de una transacción.
    require_once __DIR__ . "/actividad.php";
    $actividad = new Actividad();
    $resultado = $actividad->distribuirRecaudo($idActividad);

    http_response_code($resultado["status"]);
    echo json_encode([
        "success" => $resultado["status"] === 200,
        "mensaje" => $resultado["mensaje"],
        "valorClub" => $resultado["valorClub"] ?? null,
        "valorAsociados" => $resultado["valorAsociados"] ?? null,
        "cantidadAsociados" => $resultado["cantidadAsociados"] ?? null,
        "repartoAsociados" => $resultado["repartoAsociados"] ?? []
    ]);
} catch (PDOException $error) {
    error_log("Error al distribuir el recaudo de la actividad: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "No se pudo distribuir el recaudo"]);
}
