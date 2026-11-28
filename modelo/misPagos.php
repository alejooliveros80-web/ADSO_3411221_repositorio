<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Esta consulta solo se realiza mediante GET.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Comprueba que el usuario asociado haya iniciado sesión.
if (!isset($_SESSION["rol"], $_SESSION["documentos"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión primero"]);
    exit;
}

if (strtolower($_SESSION["rol"]) !== "asociado") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un asociado puede consultar esta información"]);
    exit;
}

try {
    require_once __DIR__ . "/consultaMisPagos.php";
    $misPagos = new ConsultaMisPagos();

    // Busca el ID del asociado a partir del documento de su sesión.
    $documento = (string)$_SESSION["documentos"];
    $idAsociado = $misPagos->obtenerIdAsociado($documento);

    if ($idAsociado === false) {
        http_response_code(404);
        echo json_encode(["success" => false, "mensaje" => "No se encontró el asociado"]);
        exit;
    }

    // Obtiene los préstamos y el historial mediante los métodos de la clase.
    $prestamos = $misPagos->obtenerPrestamos($idAsociado);
    $pagos = $misPagos->obtenerPagos($idAsociado);

    echo json_encode([
        "success" => true,
        "prestamos" => $prestamos,
        "pagos" => $pagos
    ]);
} catch (PDOException $error) {
    error_log("Error al consultar los pagos del asociado: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "Error en la base de datos"]);
}
