<?php
session_start();

header("content-type: application/json; charset=UTF-8");

function leerdatos()
 {      
    $datos = json_decode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE){
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}

function validarActividad($datos)
{
    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = $datos['fechaEvento'] ?? null;
    $municipio = $datos['municipio'] ?? null;
    $fechaValida = is_string($fecha)
    ? DateTime::createFromFormat("!Y-m-d", $fecha)
    : false;
$fechaCorrecta = $fechaValida &&
    $fechaValida->format("Y-m-d") === $fecha;
    if(!is_string($nombre)|| trim($nombre) ==="" || strlen($nombre) > 100 || !$fechaCorrecta || !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) { 
        http_response_code(400);
        throw new Exception(
            "ingresa un nombre, fecha y municipio validos"
        );
    }
    $totalRecaudado = $datos["totalRecaudado"] ?? null;
if (!is_numeric($totalRecaudado) || (float)$totalRecaudado < 0) {
    http_response_code(400);
    throw new Exception("El total recaudado debe ser igual o mayor que cero");
}
    return [
    "nombreActividad" => trim($nombre),
    "fechaEvento" => $fecha,
    "municipio" => trim($municipio),
    "totalRecaudado" => (float)$totalRecaudado
];
}

function validarId($id){
    $idActividad=filter_var($id,FILTER_VALIDATE_INT,["options" => ["min_range"=>1]]);
    if ($idActividad=== false){
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es valido");
    }
    return $idActividad;

}


try{
    if(!isset($_SESSION["documentos"], $_SESSION["rol"])){
        http_response_code(401);
        throw new Exception("Inicia Sesion para consultar las actividades");
    }   
 
    $metodo = $_SERVER["REQUEST_METHOD"];

    if(!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)){
        http_response_code(405);
        throw new Exception("Metodo HTTP no permitido");
    }

    if($metodo !=="GET" && strtolower($_SESSION["rol"]) !== "admin"){
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta accion");
    }

    require_once __DIR__ . "/actividad.php";
    $actividad = new Actividad();

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
            $actividad->guardar($datos);
            $resultado = [
                "success" => true,
                "message" => "Actividad creada Exitosamente"
            ];
            http_response_code(201);
            break;
            case "PUT":
                $datos=leerDatos();
                $datos["idActividad"]=validarId($datos["idActividad"] ?? null);
                $datos = array_merge($datos,validarActividad($datos));

                if (!$actividad->editar($datos)) {
                    http_response_code(404);
                    throw new Exception("No se encontro la actividad");
                }
                $resultado = [
                    "success" => true,
                    "mensaje" => "Actividad actualizada correctamente"
                ];
                http_response_code(200);
                break;
            case "DELETE":
                $datos = leerDatos();
                $idActividad = validarId($datos["idActividad"] ?? null);
    
                if (!$actividad->eliminar($idActividad)) {
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
        "mensaje" => "Error en la base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e){
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}