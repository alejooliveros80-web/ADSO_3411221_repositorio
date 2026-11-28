<?php

require_once __DIR__ . '/../config/conexion.php';

// Agrupa las operaciones para registrar y consultar pagos de préstamos.
class Pago
{
    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    // Registra un abono sin permitir superar el saldo del préstamo aprobado.
    public function registrar($documento, $idPrestamo, $fechaPago, $valorCuota)
    {
        $this->conexion->beginTransaction();

        try {
            $consultaPrestamo = $this->conexion->prepare(
                "SELECT prestamos.valorPrestamo
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
            $valorPrestamo = $consultaPrestamo->fetchColumn();

            if ($valorPrestamo === false) {
                $this->conexion->rollBack();
                return [
                    "status" => 404,
                    "mensaje" => "No existe un préstamo aprobado para el asociado indicado"
                ];
            }

            $consultaPagos = $this->conexion->prepare(
                "SELECT COALESCE(SUM(valorCuota), 0)
                 FROM pagos_cuotas
                 WHERE idPrestamo = :idPrestamo"
            );
            $consultaPagos->execute([":idPrestamo" => $idPrestamo]);
            $totalPagado = (float)$consultaPagos->fetchColumn();
            $saldoPendiente = (float)$valorPrestamo - $totalPagado;

            if ($saldoPendiente <= 0) {
                $this->conexion->rollBack();
                return ["status" => 409, "mensaje" => "El préstamo ya está pagado"];
            }

            if ((float)$valorCuota > $saldoPendiente) {
                $this->conexion->rollBack();
                return ["status" => 409, "mensaje" => "El abono supera el saldo pendiente"];
            }

            $consultaGuardar = $this->conexion->prepare(
                "INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
                 VALUES (:valorCuota, :fechaPago, :idPrestamo)"
            );
            $consultaGuardar->execute([
                ":valorCuota" => number_format((float)$valorCuota, 2, ".", ""),
                ":fechaPago" => $fechaPago,
                ":idPrestamo" => $idPrestamo
            ]);

            $this->conexion->commit();

            return ["status" => 201, "mensaje" => "Pago registrado correctamente"];
        } catch (PDOException $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            throw $error;
        }
    }
}
