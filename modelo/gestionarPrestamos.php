<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo permite consultar o decidir solicitudes con GET y PUT.
$metodo = $_SERVER["REQUEST_METHOD"];

if (!in_array($metodo, ["GET", "PUT"], true)) {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Solo un administrador puede consultar y decidir solicitudes.
if (!isset($_SESSION["rol"]) || strtolower($_SESSION["rol"]) !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un administrador puede gestionar solicitudes"]);
    exit;
}

try {
    require_once __DIR__ . "/prestamo.php";
    $prestamo = new Prestamo();

    // GET devuelve las solicitudes que todavía están pendientes.
    if ($metodo === "GET") {
        echo json_encode([
            "success" => true,
            "solicitudes" => $prestamo->obtenerSolicitudesPendientes()
        ]);
        exit;
    }

    // PUT recibe el préstamo y la decisión del administrador.
    $datos = json_decode(file_get_contents("php://input"), true);

    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
        exit;
    }

    $idPrestamo = filter_var(
        $datos["idPrestamo"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    $decision = $datos["decision"] ?? "";

    if ($idPrestamo === false || !in_array($decision, ["Aprobado", "Rechazado"], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "mensaje" => "El préstamo o la decisión no son válidos"]);
        exit;
    }

    // El modelo aplica la decisión y las reglas de préstamos vigentes.
    $resultado = $prestamo->decidirSolicitud($idPrestamo, $decision);
    http_response_code($resultado["status"]);
    echo json_encode([
        "success" => $resultado["status"] === 200,
        "mensaje" => $resultado["mensaje"]
    ]);
} catch (PDOException $error) {
    error_log("Error al gestionar la solicitud de préstamo: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "Error en la base de datos"]);
}
