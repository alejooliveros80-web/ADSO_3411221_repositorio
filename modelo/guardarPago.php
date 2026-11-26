<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo acepta solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Solo un administrador puede registrar pagos.
if (!isset($_SESSION["rol"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión primero"]);
    exit;
}

if (strtolower($_SESSION["rol"]) !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un administrador puede registrar pagos"]);
    exit;
}

// Lee los datos JSON enviados por pagos.js.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
    exit;
}

$documento = $datos["documentoAsociado"] ?? "";
$fechaPago = $datos["fechaPago"] ?? null;
$valorCuota = $datos["valorCuota"] ?? null;

// Comprueba que el ID del préstamo sea un entero positivo.
$idPrestamo = filter_var(
    $datos["idPrestamo"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

// Comprueba que el documento contenga solo números.
if (!is_string($documento) || !preg_match('/^\d+$/', trim($documento))) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "El documento no es válido"]);
    exit;
}

// Comprueba que la fecha exista y use el formato año-mes-día.
$fechaValida = is_string($fechaPago)
    ? DateTime::createFromFormat("!Y-m-d", $fechaPago)
    : false;

$fechaCorrecta = $fechaValida &&
    $fechaValida->format("Y-m-d") === $fechaPago;

// Valida el ID del préstamo, la fecha y el valor del pago.
if (
    $idPrestamo === false ||
    !$fechaCorrecta ||
    !is_numeric($valorCuota) ||
    (float)$valorCuota <= 0 ||
    (float)$valorCuota > 9999999999.99 ||
    (float)$valorCuota !== round((float)$valorCuota, 2)
) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Revisa el préstamo, la fecha y el valor"]);
    exit;
}

try {
    require_once __DIR__ . "/../config/conexion.php";
    $conexion = (new Conexion())->conectar();
    $conexion->beginTransaction();

    // Comprueba que el préstamo exista y pertenezca al asociado indicado.
    $consultaPrestamo = $conexion->prepare(
        "SELECT prestamos.numeroCuotas
         FROM prestamos
         INNER JOIN asociados
             ON asociados.idAsociado = prestamos.idAsociado
         WHERE prestamos.idPrestamo = :idPrestamo
           AND asociados.documento = :documento
           AND prestamos.estado = 'Aprobado'
         FOR UPDATE"
    );

    $consultaPrestamo->execute([
        ":idPrestamo" => $idPrestamo,
        ":documento" => trim($documento)
    ]);

    $numeroCuotas = $consultaPrestamo->fetchColumn();

    if ($numeroCuotas === false) {
        $conexion->rollBack();
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "mensaje" => "No existe un préstamo aprobado para el asociado indicado"
        ]);
        exit;
    }

    // Cuenta cuántas cuotas ya se registraron para ese préstamo.
    $consultaPagos = $conexion->prepare(
        "SELECT COUNT(*) FROM pagos_cuotas WHERE idPrestamo = :idPrestamo"
    );
    $consultaPagos->execute([":idPrestamo" => $idPrestamo]);
    $cuotasPagadas = (int)$consultaPagos->fetchColumn();

    if ($cuotasPagadas >= (int)$numeroCuotas) {
        $conexion->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "mensaje" => "El préstamo ya tiene registradas todas sus cuotas"
        ]);
        exit;
    }

    // Guarda el pago usando las columnas de la tabla pagos_cuotas.
    $consultaGuardar = $conexion->prepare(
        "INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
         VALUES (:valorCuota, :fechaPago, :idPrestamo)"
    );

    $consultaGuardar->execute([
        ":valorCuota" => number_format((float)$valorCuota, 2, ".", ""),
        ":fechaPago" => $fechaPago,
        ":idPrestamo" => $idPrestamo
    ]);

    $conexion->commit();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "mensaje" => "Pago registrado correctamente"
    ]);
} catch (PDOException $error) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error al guardar el pago: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "mensaje" => "No se pudo guardar el pago"
    ]);
}