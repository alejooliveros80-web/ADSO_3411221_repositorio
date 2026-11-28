<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

if (!isset($_SESSION["documentos"], $_SESSION["rol"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión primero"]);
    exit;
}

if (strtolower($_SESSION["rol"]) !== "asociado") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo los asociados pueden inscribirse"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
    exit;
}

$idActividad = filter_var(
    $datos["idActividad"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($idActividad === false) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Actividad no válida"]);
    exit;
}

try {
    // Registra la inscripción mediante el modelo InscripcionActividad.
    require_once __DIR__ . "/inscripcionActividad.php";
    $inscripcion = new InscripcionActividad();
    $resultado = $inscripcion->inscribir($idActividad, $_SESSION["documentos"]);

    http_response_code($resultado["status"]);
    if ($resultado["status"] !== 201) {
        echo json_encode(["success" => false, "mensaje" => $resultado["mensaje"]]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "mensaje" => $resultado["mensaje"]
    ]);
} catch (PDOException $error) {
    if ($error->getCode() === "23000") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "mensaje" => "Ya estás inscrito en esa actividad"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "mensaje" => "Ocurrió un error al guardar la inscripción"
        ]);
    }
}
