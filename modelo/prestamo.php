<?php

require_once __DIR__ . '/../config/conexion.php';

// Agrupa las consultas y operaciones de préstamos.
class Prestamo
{
    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    // Guarda la solicitud inicial de un asociado.
    public function guardarSolicitud($documento, $datos)
    {
        $consultaAsociado = $this->conexion->prepare(
            "SELECT idAsociado FROM asociados WHERE documento = :documento"
        );
        $consultaAsociado->execute([":documento" => trim($documento)]);
        $idAsociado = $consultaAsociado->fetchColumn();

        if ($idAsociado === false) {
            return ["status" => 404, "mensaje" => "No existe un asociado con ese documento"];
        }

        $sql = "INSERT INTO prestamos
                    (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado, estado)
                VALUES
                    (:valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo, :idAsociado, :estado)";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ":valorPrestamo" => number_format((float)$datos["valorPrestamo"], 2, ".", ""),
            ":tasaInteres" => number_format((float)$datos["tasaInteres"], 2, ".", ""),
            ":numeroCuotas" => $datos["numeroCuotas"],
            ":fechaPrestamo" => $datos["fechaPrestamo"],
            ":idAsociado" => $idAsociado,
            ":estado" => "Pendiente"
        ]);

        return ["status" => 201, "idPrestamo" => (int)$this->conexion->lastInsertId()];
    }

    // Devuelve las solicitudes que esperan la decisión del administrador.
    public function obtenerSolicitudesPendientes()
    {
        $sql = "SELECT prestamos.idPrestamo, asociados.nombres,
                       asociados.apellidos, asociados.documento,
                       prestamos.valorPrestamo, prestamos.tasaInteres,
                       prestamos.numeroCuotas, prestamos.fechaPrestamo
                FROM prestamos
                INNER JOIN asociados ON asociados.idAsociado = prestamos.idAsociado
                WHERE prestamos.estado = 'Pendiente'
                ORDER BY prestamos.fechaPrestamo ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();

        return $consulta->fetchAll();
    }

    // Aprueba o rechaza una solicitud y aplica los límites del club.
    public function decidirSolicitud($idPrestamo, $decision)
    {
        $this->conexion->beginTransaction();

        try {
            $consultaSolicitud = $this->conexion->prepare(
                "SELECT idAsociado, valorPrestamo
                 FROM prestamos
                 WHERE idPrestamo = :idPrestamo
                   AND estado = 'Pendiente'
                 FOR UPDATE"
            );
            $consultaSolicitud->execute([":idPrestamo" => $idPrestamo]);
            $solicitud = $consultaSolicitud->fetch();

            if (!$solicitud) {
                $this->conexion->rollBack();
                return ["status" => 404, "mensaje" => "No existe una solicitud pendiente con ese ID"];
            }

            if ($decision === "Aprobado") {
                $bloquearAsociado = $this->conexion->prepare(
                    "SELECT idAsociado
                     FROM asociados
                     WHERE idAsociado = :idAsociado
                     FOR UPDATE"
                );
                $bloquearAsociado->execute([":idAsociado" => $solicitud["idAsociado"]]);

                $consultaVigentes = $this->conexion->prepare(
                    "SELECT
                        COUNT(CASE
                            WHEN prestamos.valorPrestamo - COALESCE(pagos.totalPagado, 0) > 0
                            THEN 1
                        END) AS cantidadVigentes,
                        COALESCE(SUM(GREATEST(
                            prestamos.valorPrestamo - COALESCE(pagos.totalPagado, 0), 0
                        )), 0) AS sumaVigente
                     FROM prestamos
                     LEFT JOIN (
                        SELECT idPrestamo, SUM(valorCuota) AS totalPagado
                        FROM pagos_cuotas
                        GROUP BY idPrestamo
                     ) AS pagos ON pagos.idPrestamo = prestamos.idPrestamo
                     WHERE prestamos.idAsociado = :idAsociado
                       AND prestamos.estado = 'Aprobado'"
                );
                $consultaVigentes->execute([":idAsociado" => $solicitud["idAsociado"]]);
                $prestamosVigentes = $consultaVigentes->fetch();

                if ((int)$prestamosVigentes["cantidadVigentes"] >= 3) {
                    $this->conexion->rollBack();
                    return ["status" => 409, "mensaje" => "No se puede aprobar: el asociado ya tiene 3 préstamos vigentes"];
                }

                if ((float)$prestamosVigentes["sumaVigente"] + (float)$solicitud["valorPrestamo"] > 80000000) {
                    $this->conexion->rollBack();
                    return ["status" => 409, "mensaje" => "No se puede aprobar: la suma superaría los 80 millones"];
                }
            }

            $actualizar = $this->conexion->prepare(
                "UPDATE prestamos
                 SET estado = :estado
                 WHERE idPrestamo = :idPrestamo
                   AND estado = 'Pendiente'"
            );
            $actualizar->execute([
                ":estado" => $decision,
                ":idPrestamo" => $idPrestamo
            ]);

            $this->conexion->commit();

            return [
                "status" => 200,
                "mensaje" => $decision === "Aprobado" ? "Solicitud aprobada" : "Solicitud rechazada"
            ];
        } catch (PDOException $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            throw $error;
        }
    }
}
