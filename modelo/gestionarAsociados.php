<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo permite listar, crear, editar o eliminar mediante estos métodos.
$metodo = $_SERVER["REQUEST_METHOD"];
if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Protege la gestión de asociados para que únicamente la use un administrador.
if (strtolower($_SESSION["rol"] ?? "") !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un administrador puede gestionar asociados"]);
    exit;
}

// Lee el JSON que envía la vista.
function leerDatosAsociado()
{
    $datos = json_decode(file_get_contents("php://input"), true);
    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Los datos enviados no son válidos");
    }

    return $datos;
}

// Valida y organiza los datos antes de enviarlos al modelo.
function validarDatosAsociado($datos, $requiereContrasena)
{
    $asociado = [
        "documento" => trim((string)($datos["documento"] ?? "")),
        "nombres" => trim((string)($datos["nombres"] ?? "")),
        "apellidos" => trim((string)($datos["apellidos"] ?? "")),
        "fechaNacimiento" => $datos["fechaNacimiento"] ?? "",
        "genero" => $datos["genero"] ?? "",
        "direccionResidencia" => trim((string)($datos["direccionResidencia"] ?? "")),
        "ciudad" => trim((string)($datos["ciudad"] ?? "")),
        "telefono" => trim((string)($datos["telefono"] ?? "")),
        "email" => trim((string)($datos["email"] ?? "")),
        "panswoard" => (string)($datos["panswoard"] ?? "")
    ];

    foreach (["documento", "nombres", "apellidos", "fechaNacimiento", "genero", "direccionResidencia", "ciudad", "telefono", "email"] as $campo) {
        if ($asociado[$campo] === "") {
            http_response_code(400);
            throw new Exception("Completa todos los datos obligatorios");
        }
    }

    $fecha = is_string($asociado["fechaNacimiento"])
        ? DateTime::createFromFormat("!Y-m-d", $asociado["fechaNacimiento"])
        : false;

    if (!preg_match('/^\d+$/', $asociado["documento"]) ||
        !$fecha || $fecha->format("Y-m-d") !== $asociado["fechaNacimiento"] ||
        !in_array($asociado["genero"], ["Masculino", "Femenino", "Otro"], true) ||
        !filter_var($asociado["email"], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("Revisa el documento, fecha, género y correo electrónico");
    }

    if (($requiereContrasena && mb_strlen($asociado["panswoard"]) < 8) ||
        (!$requiereContrasena && $asociado["panswoard"] !== "" && mb_strlen($asociado["panswoard"]) < 8)) {
        http_response_code(400);
        throw new Exception("La contraseña debe tener mínimo 8 caracteres");
    }

    return $asociado;
}

try {
    require_once __DIR__ . "/asociados.php";
    $modeloAsociado = new Asociado([]);

    // GET devuelve la lista de asociados sin contraseñas.
    if ($metodo === "GET") {
        echo json_encode([
            "success" => true,
            "asociados" => $modeloAsociado->listarAsociados()
        ]);
        exit;
    }

    $datos = leerDatosAsociado();

    if ($metodo === "POST") {
        $datosAsociado = validarDatosAsociado($datos, true);
        $nuevoAsociado = new Asociado($datosAsociado);
        $nuevoAsociado->guardarAsociado();

        http_response_code(201);
        echo json_encode(["success" => true, "mensaje" => "Asociado creado correctamente"]);
        exit;
    }

    $idAsociado = filter_var(
        $datos["idAsociado"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($idAsociado === false) {
        http_response_code(400);
        throw new Exception("El identificador del asociado no es válido");
    }

    if ($metodo === "PUT") {
        $datosAsociado = validarDatosAsociado($datos, false);
        if (!$modeloAsociado->editarAsociado($idAsociado, $datosAsociado)) {
            http_response_code(404);
            throw new Exception("No se encontró el asociado");
        }

        echo json_encode(["success" => true, "mensaje" => "Asociado actualizado correctamente"]);
        exit;
    }

    if (!$modeloAsociado->eliminarAsociado($idAsociado)) {
        http_response_code(404);
        throw new Exception("No se encontró el asociado");
    }

    echo json_encode(["success" => true, "mensaje" => "Asociado eliminado correctamente"]);
} catch (PDOException $error) {
    error_log("Error al gestionar asociados: " . $error->getMessage());

    if ($error->getCode() === "23000") {
        http_response_code(409);
        $mensaje = $metodo === "DELETE"
            ? "No se puede eliminar el asociado porque tiene registros relacionados"
            : "El documento o correo ya está registrado";
    } else {
        http_response_code(500);
        $mensaje = "Ocurrió un error en la base de datos";
    }

    echo json_encode(["success" => false, "mensaje" => $mensaje]);
} catch (Exception $error) {
    echo json_encode(["success" => false, "mensaje" => $error->getMessage()]);
}
