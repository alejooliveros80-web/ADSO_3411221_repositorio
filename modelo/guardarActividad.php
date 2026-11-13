<?php

session_start();
header("Content-Type: application/json; charset=utf-8");

// Acepta solo solicitudes POST del formulario de registro.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido."]);
    exit;
}

// Solo un administrador puede crear actividades.
if (strtolower($_SESSION["rol"] ?? "") !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Debes iniciar sesión como administrador."]);
    exit;
}

// Lee el JSON enviado desde el formulario con fetch.
$datos = json_decode(file_get_contents("php://input"), true);
if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Los datos enviados no son válidos."]);
    exit;
}

// Limpia y valida los valores que requiere la tabla actividades.
$nombreActividad = trim($datos["nombreActividad"] ?? "");
$fechaEvento = trim($datos["fechaEvento"] ?? "");
$municipio = trim($datos["municipio"] ?? "");
$totalRecaudado = $datos["totalRecaudado"] ?? null;

$fechaValida = DateTime::createFromFormat("!Y-m-d", $fechaEvento);
$fechaCorrecta = $fechaValida && $fechaValida->format("Y-m-d") === $fechaEvento;

if ($nombreActividad === "" || strlen($nombreActividad) > 100 ||
    !$fechaCorrecta || $municipio === "" || strlen($municipio) > 50 ||
    !is_numeric($totalRecaudado) || (float)$totalRecaudado < 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Revisa el nombre, la fecha, el municipio y el total recaudado."]);
    exit;
}

try {
    // Abre la conexión y guarda la actividad con una consulta preparada.
    require_once __DIR__ . "/../config/conexion.php";
    $conexion = (new Conexion())->conectar();
    $consulta = $conexion->prepare(
        "INSERT INTO actividades (nombreActividad, fechaEvento, municipio, totalRecaudado)
         VALUES (:nombreActividad, :fechaEvento, :municipio, :totalRecaudado)"
    );
    $consulta->execute([
        ":nombreActividad" => $nombreActividad,
        ":fechaEvento" => $fechaEvento,
        ":municipio" => $municipio,
        ":totalRecaudado" => number_format((float)$totalRecaudado, 2, ".", "")
    ]);

    // Confirma que MySQL guardó la actividad.
    echo json_encode([
        "success" => true,
        "mensaje" => "Actividad guardada correctamente.",
        "idActividad" => (int)$conexion->lastInsertId()
    ]);
} catch (PDOException $error) {
    error_log("Error al guardar actividad: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "No se pudo guardar la actividad en la base de datos."]);
}
