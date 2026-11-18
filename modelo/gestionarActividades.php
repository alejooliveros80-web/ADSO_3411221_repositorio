<?php
session_start();

header("content-type: application/json; charset=UTF-8");

function leerdatos()
 {      
    $datos = json_encode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE){
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " - json_last_error_msg());
    }
    return $datos;
}

function validarActividad($datos)
{
    $nombre=$datos['nombreActividad'] ?? null;
    $fecha=$datos['fechaEvento'] ?? null;
    $municipio = $datos['municipio'] ?? null;
    if(!is_string(nombre)|| trim($nombre) ==="" strlen($nombre) > 100 || !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) { 
        http_response_code(400);
        throw new Exception(
            "ingresa un nombre y municipio validos"
        );
    }
    return{
        "nombreActividad"=>trim($nombre),
        "fechaEvento"=>$fecha,
        "municipio"=>trim($municipio)
    };
}

function validarId(id){
    $idActividad=filter_var($id.FILTER_VALIDATE_INT,["options" => ["min_range"=>1]]);
    if ($idActividad=== false){
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es valido");
    }
    return $idActividad;

}


try{
    if(!isset($_SESSION["documento"], $_SESSION["rol"])){
        http_response_code(401)
        throw new Exception("Inicia Sesion para consultar las actividades");
    }   
 
    $metodo = $_SERVER["REQUEST_METHOD"];

    if(!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)){
        http_response_code(405);
        throw new Exception("Metodo HTTP no permitido")
    }

    if($metodo !=="GET" && $_SESSION["rol"] !== "admin"){
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta accion");
    }

    require_once __DIR__ . "/Actividad.php";
    $actividad = new Activdad();

    switch($metodo){
        case "GET":
            $resultado= [
                "success" => true,
                "rol" => $_SESSION["rol"],
                "actividades" => $actividad -> obtenerActividades()
            ];
            http_response_code(200);
            break;
        case "POST":
            $datos=validarActividad(leerDatos());
            $actividad->($datos);
            $resultado = [
                "success" => true,
                "message" => "Actividad creada Exitosamente"
            ]
            http_response_code(201);
            break;
            case "PUT":
                $datos=leerDatos():
                $datos["idActividad"]=validarId($datos["idActividad1"] ?? null);
                $datos = array_merge($datos,validarActividad($datos));

                if(actividad->editar($datos)){
                    http_responce_code(404);
                    throw new Exception("No se encontro la actividad");
                }
                $resultado = [
                    "succes" => true,
                    "messaje" => "Actividad actualizada correctamente"
                ];
                http_response_code(200);
                break;
            case "DELETE":
                $datos = leerDatos();
                $idActividad = validarId($datos["idActividad"] ?? null);
    
                if (!$actividad->eliminar($idActividad)){
                    http_response_code(404);
                    throw new Exception("No se encontro la actividad");
                }

                $resultado = [
                    "success" => true,
                    "mensaje" => "Actividad eliminada correctamente"
                ];
                http_response_code(200);
                break;
    }
    echo json_encode($resultado);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos" . $e->getMesasage()
    ]);
} catch (Exception $e){
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}