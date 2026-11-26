<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Solo permite consultar o decidir solicitudes con GET y PUT.
$metodo = $_SERVER["REQUEST_METHOD"];

if (!in_array($metodo, ["GET", "PUT"], true)) {
    http_response_code(405);
    echo json_encode(["success" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Solo un administrador puede consultar y decidir solicitudes.
if (!isset($_SESSION["rol"]) || strtolower($_SESSION["rol"]) !== "admin") {
    http_response_code(403);
    echo json_encode(["success" => false, "mensaje" => "Solo un administrador puede gestionar solicitudes"]);
    exit;
}

try {
    require_once __DIR__ . "/../config/conexion.php";
    $conexion = (new Conexion())->conectar();

    // GET devuelve las solicitudes que todavía están pendientes.
    if ($metodo === "GET") {
        $consulta = $conexion->query(
            "SELECT
                prestamos.idPrestamo,
                asociados.nombres,
                asociados.apellidos,
                asociados.documento,
                prestamos.valorPrestamo,
                prestamos.tasaInteres,
                prestamos.numeroCuotas,
                prestamos.fechaPrestamo
             FROM prestamos
             INNER JOIN asociados
                 ON asociados.idAsociado = prestamos.idAsociado
             WHERE prestamos.estado = 'Pendiente'
             ORDER BY prestamos.fechaPrestamo ASC"
        );

        echo json_encode([
            "success" => true,
            "solicitudes" => $consulta->fetchAll()
        ]);
        exit;
    }

    // PUT recibe el préstamo y la decisión del administrador.
    $datos = json_decode(file_get_contents("php://input"), true);

    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["success" => false, "mensaje" => "Datos inválidos"]);
        exit;
    }

    $idPrestamo = filter_var(
        $datos["idPrestamo"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    $decision = $datos["decision"] ?? "";

    if ($idPrestamo === false || !in_array($decision, ["Aprobado", "Rechazado"], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "mensaje" => "El préstamo o la decisión no son válidos"]);
        exit;
    }

    $conexion->beginTransaction();

    // Solo permite decidir un préstamo que aún siga pendiente.
    $consultaSolicitud = $conexion->prepare(
        "SELECT idAsociado, valorPrestamo
         FROM prestamos
         WHERE idPrestamo = :idPrestamo
           AND estado = 'Pendiente'
         FOR UPDATE"
    );
    $consultaSolicitud->execute([":idPrestamo" => $idPrestamo]);
    $solicitud = $consultaSolicitud->fetch();

    if (!$solicitud) {
        $conexion->rollBack();
        http_response_code(404);
        echo json_encode(["success" => false, "mensaje" => "No existe una solicitud pendiente con ese ID"]);
        exit;
    }

    // Antes de aprobar, verifica los préstamos aprobados y vigentes del asociado.
    if ($decision === "Aprobado") {
        $bloquearAsociado = $conexion->prepare(
            "SELECT idAsociado
             FROM asociados
             WHERE idAsociado = :idAsociado
             FOR UPDATE"
        );
        $bloquearAsociado->execute([":idAsociado" => $solicitud["idAsociado"]]);

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
               AND prestamos.estado = 'Aprobado'
               AND COALESCE(pagos.cuotasPagadas, 0) < prestamos.numeroCuotas"
        );
        $consultaVigentes->execute([":idAsociado" => $solicitud["idAsociado"]]);
        $prestamosVigentes = $consultaVigentes->fetch();

        if ((int)$prestamosVigentes["cantidadVigentes"] >= 3) {
            $conexion->rollBack();
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "mensaje" => "No se puede aprobar: el asociado ya tiene 3 préstamos vigentes"
            ]);
            exit;
        }

        if ((float)$prestamosVigentes["sumaVigente"] + (float)$solicitud["valorPrestamo"] > 80000000) {
            $conexion->rollBack();
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "mensaje" => "No se puede aprobar: la suma superaría los 80 millones"
            ]);
            exit;
        }
    }

    // Guarda la decisión del administrador.
    $actualizar = $conexion->prepare(
        "UPDATE prestamos
         SET estado = :estado
         WHERE idPrestamo = :idPrestamo
           AND estado = 'Pendiente'"
    );
    $actualizar->execute([
        ":estado" => $decision,
        ":idPrestamo" => $idPrestamo
    ]);

    $conexion->commit();

    echo json_encode([
        "success" => true,
        "mensaje" => $decision === "Aprobado"
            ? "Solicitud aprobada"
            : "Solicitud rechazada"
    ]);
} catch (PDOException $error) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error al gestionar la solicitud de préstamo: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "mensaje" => "Error en la base de datos"]);
}