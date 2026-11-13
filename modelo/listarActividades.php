<?php

session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo los usuarios autenticados pueden consultar las actividades.
if (empty($_SESSION["rol"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión para consultar las actividades."]);
    exit;
}

try {
    // Consulta MySQL y devuelve las actividades ordenadas por fecha.
    require_once __DIR__ . "/../config/conexion.php";
    $conexion = (new Conexion())->conectar();
    $consulta = $conexion->query(
        "SELECT idActividad, nombreActividad, fechaEvento, municipio, totalRecaudado
         FROM actividades
         ORDER BY fechaEvento ASC, idActividad DESC"
    );

    echo json_encode([
        "success" => true,
        "actividades" => $consulta->fetchAll()
    ]);
} catch (PDOException $error) {
    error_log("Error al listar actividades: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "No se pudieron consultar las actividades."]);
}
