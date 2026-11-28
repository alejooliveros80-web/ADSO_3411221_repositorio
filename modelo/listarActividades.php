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
    // Usa la clase Actividad para consultar los registros.
    require_once __DIR__ . "/actividad.php";
    $actividad = new Actividad();

    echo json_encode([
        "success" => true,
        "actividades" => $actividad->listar()
    ]);
} catch (PDOException $error) {
    error_log("Error al listar actividades: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "No se pudieron consultar las actividades."]);
}
