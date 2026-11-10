<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

try{
     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        //Saltar a catch y mostrar el mensaje error
        throw new Exception("Método HTTP no permitido");
    }

    $datos=json_decode(file_get_contents('php://input'), true);

     if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Datos inválidos");
    }

    $email = $datos['email'] ??"";
    $panswoard = $datos['panswoard'] ?? "";

    if(!is_string($email)|| !is_string($panswoard) || $email ==="" || $panswoard ===""){
        http_response_code(400);
        throw new Exception("Email o contraseña invalidados");
    }

    require_once __DIR__ .'/../config/conexion.php';
    $conexion = (new Conexion())->conectar();

    $consulta =$conexion->prepare("SELECT * FROM asociados WHERE email = :email");
    $consulta->execute([':email' => $email]);
    $asociado =$consulta->fetch();

    if (!$asociado || !password_verify($panswoard, $asociado['panswoard'])) {
    http_response_code(401);
    throw new Exception("Correo o contraseña incorrectos");
}

    $_SESSION["rol"] = $asociado['rol'];
    $_SESSION["documentos"] = $asociado['documento'];

    echo json_encode([
        "success" => true,
        "rol" => $_SESSION["rol"],
        "nombre" => $asociado["nombres"]." ". $asociado["apellidos"],
    ]);
}catch(PDOException $e){ 
   $errorMessage = "Error: " . $e->getMessage();

echo json_encode([
    "success" => false,
    "mensaje" => $errorMessage
]);
} catch (Exception $e){
    echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
}