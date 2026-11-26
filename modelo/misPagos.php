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
    require_once __DIR__ . "/../config/conexion.php";
    $conexion = (new Conexion())->conectar();

    // Busca el ID del asociado a partir del documento de su sesión.
    $documento = (string)$_SESSION["documentos"];
    $consultaAsociado = $conexion->prepare(
        "SELECT idAsociado
         FROM asociados
         WHERE documento = :documento"
    );
    $consultaAsociado->execute([":documento" => $documento]);
    $idAsociado = $consultaAsociado->fetchColumn();

    if ($idAsociado === false) {
        http_response_code(404);
        echo json_encode(["success" => false, "mensaje" => "No se encontró el asociado"]);
        exit;
    }

    // Consulta las solicitudes y préstamos del asociado.
    $consultaPrestamos = $conexion->prepare(
        "SELECT idPrestamo, valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, estado
         FROM prestamos
         WHERE idAsociado = :idAsociado
         ORDER BY fechaPrestamo DESC, idPrestamo DESC"
    );
    $consultaPrestamos->execute([":idAsociado" => $idAsociado]);

    // Consulta únicamente los pagos pertenecientes a sus préstamos.
    $consultaPagos = $conexion->prepare(
        "SELECT pagos_cuotas.idPago, pagos_cuotas.valorCuota,
                pagos_cuotas.fechaPago, pagos_cuotas.idPrestamo
         FROM pagos_cuotas
         INNER JOIN prestamos
             ON prestamos.idPrestamo = pagos_cuotas.idPrestamo
         WHERE prestamos.idAsociado = :idAsociado
         ORDER BY pagos_cuotas.fechaPago DESC, pagos_cuotas.idPago DESC"
    );
    $consultaPagos->execute([":idAsociado" => $idAsociado]);

    echo json_encode([
        "success" => true,
        "prestamos" => $consultaPrestamos->fetchAll(),
        "pagos" => $consultaPagos->fetchAll()
    ]);
} catch (PDOException $error) {
    error_log("Error al consultar los pagos del asociado: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "Error en la base de datos"]);
}