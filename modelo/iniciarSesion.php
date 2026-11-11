<?php

session_start();

// Indica que este archivo responderá al navegador con datos JSON.
header("Content-Type: application/json; charset=utf-8");

try{
     // Solo permite iniciar sesión enviando los datos mediante POST.
     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        //Saltar a catch y mostrar el mensaje error
        throw new Exception("Método HTTP no permitido");
    }

    // Lee y convierte el JSON que envía login.js con fetch.
    $datos=json_decode(file_get_contents('php://input'), true);

     if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Datos inválidos");
    }

    // Obtiene el correo y la contraseña enviados desde el formulario.
    $email = $datos['email'] ??"";
    $panswoard = $datos['panswoard'] ?? "";

    if(!is_string($email)|| !is_string($panswoard) || $email ==="" || $panswoard ===""){
        http_response_code(400);
        throw new Exception("Email o contraseña invalidados");
    }

    // Carga la conexión y busca al asociado por su correo.
    require_once __DIR__ .'/../config/conexion.php';
    $conexion = (new Conexion())->conectar();

    $consulta =$conexion->prepare("SELECT * FROM asociados WHERE email = :email");
    $consulta->execute([':email' => $email]);
    $asociado =$consulta->fetch();

    // Comprueba que exista el usuario y valida la contraseña con su hash.
    if (!$asociado || !password_verify($panswoard, $asociado['panswoard'])) {
    http_response_code(401);
    throw new Exception("Correo o contraseña incorrectos");
}

    // Guarda los datos necesarios del usuario en la sesión del servidor.
    $_SESSION["rol"] = $asociado['rol'];
    $_SESSION["documentos"] = $asociado['documento'];

    // Devuelve al navegador el resultado y los datos para el panel.
    echo json_encode([
        "success" => true,
        "rol" => $_SESSION["rol"],
        "nombre" => $asociado["nombres"]." ". $asociado["apellidos"],
    ]);
// Devuelve los errores de conexión o consulta en formato JSON.
}catch(PDOException $e){ 
   $errorMessage = "Error: " . $e->getMessage();

echo json_encode([
    "success" => false,
    "mensaje" => $errorMessage
]);
// Devuelve en JSON los errores de validación o autenticación.
} catch (Exception $e){
    echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
}
