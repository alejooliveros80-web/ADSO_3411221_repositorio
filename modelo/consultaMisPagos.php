<?php

require_once __DIR__ . '/../config/conexion.php';

// Agrupa las consultas de préstamos y pagos de un asociado.
class ConsultaMisPagos
{
    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    // Busca el ID del asociado usando su documento.
    public function obtenerIdAsociado($documento)
    {
        $sql = "SELECT idAsociado
                FROM asociados
                WHERE documento = :documento";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":documento" => $documento]);

        return $consulta->fetchColumn();
    }

    // Obtiene las solicitudes y préstamos junto con el saldo pendiente.
    public function obtenerPrestamos($idAsociado)
    {
        $sql = "SELECT prestamos.idPrestamo, prestamos.valorPrestamo,
                       prestamos.tasaInteres, prestamos.numeroCuotas,
                       prestamos.fechaPrestamo, prestamos.estado,
                       GREATEST(
                           prestamos.valorPrestamo - COALESCE(pagos.totalPagado, 0),
                           0
                       ) AS saldoPendiente
                FROM prestamos
                LEFT JOIN (
                    SELECT idPrestamo, SUM(valorCuota) AS totalPagado
                    FROM pagos_cuotas
                    GROUP BY idPrestamo
                ) AS pagos ON pagos.idPrestamo = prestamos.idPrestamo
                WHERE prestamos.idAsociado = :idAsociado
                ORDER BY prestamos.fechaPrestamo DESC, prestamos.idPrestamo DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":idAsociado" => $idAsociado]);

        return $consulta->fetchAll();
    }

    // Obtiene el historial de pagos de los préstamos del asociado.
    public function obtenerPagos($idAsociado)
    {
        $sql = "SELECT pagos_cuotas.idPago, pagos_cuotas.valorCuota,
                       pagos_cuotas.fechaPago, pagos_cuotas.idPrestamo
                FROM pagos_cuotas
                INNER JOIN prestamos
                    ON prestamos.idPrestamo = pagos_cuotas.idPrestamo
                WHERE prestamos.idAsociado = :idAsociado
                ORDER BY pagos_cuotas.fechaPago DESC, pagos_cuotas.idPago DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":idAsociado" => $idAsociado]);

        return $consulta->fetchAll();
    }
}
