<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo acepta solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Solo un administrador puede registrar préstamos.
if (!isset($_SESSION["rol"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "mensaje" => "Inicia sesión primero"]);
    exit;
}

if (strtolower($_SESSION["rol"]) !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un administrador puede registrar préstamos"]);
    exit;
}

// Lee y valida el JSON enviado por el controlador JavaScript.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
    exit;
}

$documento = $datos["documentoAsociado"] ?? "";
$fechaPrestamo = $datos["fechaPrestamo"] ?? null;
$valorPrestamo = $datos["valorPrestamo"] ?? null;
$tasaInteres = $datos["tasaInteres"] ?? null;

$numeroCuotas = filter_var(
    $datos["numeroCuotas"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

// Comprueba que el documento contenga solo números.
if (!is_string($documento) || !preg_match('/^\d+$/', trim($documento))) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "El documento del asociado no es válido"]);
    exit;
}

// Comprueba que la fecha sea una fecha real con formato año-mes-día.
$fechaValida = is_string($fechaPrestamo)
    ? DateTime::createFromFormat("!Y-m-d", $fechaPrestamo)
    : false;

$fechaCorrecta = $fechaValida &&
    $fechaValida->format("Y-m-d") === $fechaPrestamo;

// Valida el monto, la tasa y el número de cuotas.
if (
    !$fechaCorrecta ||
    !is_numeric($valorPrestamo) ||
    (float)$valorPrestamo <= 0 ||
    !is_numeric($tasaInteres) ||
    (float)$tasaInteres < 0 ||
    (float)$tasaInteres > 999.99 ||
    (float)$tasaInteres !== round((float)$tasaInteres, 2) ||
    $numeroCuotas === false
) {
    http_response_code(400);
    echo json_encode(["success" => false, "mensaje" => "Revisa la fecha, el monto, la tasa y las cuotas"]);
    exit;
}

try {
    require_once __DIR__ . "/../config/conexion.php";
    $conexion = (new Conexion())->conectar();

    // Busca el ID interno del asociado usando su documento.
    $consultaAsociado = $conexion->prepare(
        "SELECT idAsociado FROM asociados WHERE documento = :documento"
    );
    $consultaAsociado->execute([":documento" => trim($documento)]);
    $idAsociado = $consultaAsociado->fetchColumn();

    if (!$idAsociado) {
        http_response_code(404);
        echo json_encode(["success" => false, "mensaje" => "No existe un asociado con ese documento"]);
        exit;
    }

    // Cuenta los préstamos vigentes y suma sus montos originales.
    $consultaVigentes = $conexion->prepare(
        "SELECT
            COUNT(*) AS cantidadVigentes,
            COALESCE(SUM(prestamos.valorPrestamo), 0) AS sumaVigente
         FROM prestamos
         LEFT JOIN (
            SELECT idPrestamo, COUNT(*) AS cuotasPagadas
            FROM pagos_cuotas
            GROUP BY idPrestamo
         ) AS pagos ON pagos.idPrestamo = prestamos.idPrestamo
         WHERE prestamos.idAsociado = :idAsociado
           AND COALESCE(pagos.cuotasPagadas, 0) < prestamos.numeroCuotas"
    );
    $consultaVigentes->execute([":idAsociado" => $idAsociado]);
    $prestamosVigentes = $consultaVigentes->fetch();

    if ((int)$prestamosVigentes["cantidadVigentes"] >= 3) {
        http_response_code(409);
        echo json_encode(["success" => false, "mensaje" => "El asociado ya tiene 3 préstamos vigentes"]);
        exit;
    }

    if ((float)$prestamosVigentes["sumaVigente"] + (float)$valorPrestamo > 80000000) {
        http_response_code(409);
        echo json_encode(["success" => false, "mensaje" => "La suma de los préstamos vigentes superaría los 80 millones"]);
        exit;
    }

    // Guarda el préstamo con los nombres de columnas de la tabla.
    $consultaPrestamo = $conexion->prepare(
        "INSERT INTO prestamos
            (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado)
         VALUES
            (:valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo, :idAsociado)"
    );

    $consultaPrestamo->execute([
        ":valorPrestamo" => number_format((float)$valorPrestamo, 2, ".", ""),
        ":tasaInteres" => number_format((float)$tasaInteres, 2, ".", ""),
        ":numeroCuotas" => $numeroCuotas,
        ":fechaPrestamo" => $fechaPrestamo,
        ":idAsociado" => $idAsociado
    ]);

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "mensaje" => "Préstamo guardado correctamente"
    ]);
} catch (PDOException $error) {
    error_log("Error al guardar el préstamo: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "No se pudo guardar el préstamo"]);
}