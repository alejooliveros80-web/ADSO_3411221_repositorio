<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/asociados.php';


try { 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    // Leer el contenido JSON enviado por fetch
    $json = file_get_contents('php://input');
    $datos = json_decode($json, true);

    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Datos inválidos");
    }

    $camposRequeridos = ["documento", "nombre", "apellido", "fechaNacimiento", "genero", "direccion", "ciudad", "telefono", "email", "panswoard"];

    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            http_response_code(400);
            throw new Exception("Falta el campo: " . $campo);
        }
    }
    
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("El campo 'email' no es válido.");
    }

    $asociado = new Asociado($datos);
    $resultado = $asociado->guardarAsociado();

    if ($resultado) {
        http_response_code(201);
        echo json_encode(["success" => true, "mensaje" => "Asociado guardado exitosamente"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "mensaje" => "Error al guardar"]);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(["success" => false, "mensaje" => "El documento ya está registrado"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
}