<?php

require_once __DIR__ . '/../config/conexion.php';

// Agrupa las operaciones para inscribir un asociado en una actividad.
class InscripcionActividad
{
    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    // Verifica que existan la actividad y el asociado y registra la inscripción.
    public function inscribir($idActividad, $documento)
    {
        $this->conexion->beginTransaction();

        try {
            // Bloquea la actividad para no inscribir a nadie mientras se distribuye el recaudo.
            $consultaActividad = $this->conexion->prepare(
                "SELECT idActividad
                 FROM actividades
                 WHERE idActividad = :idActividad
                   AND fechaDistribucion IS NULL
                 FOR UPDATE"
            );
            $consultaActividad->execute([":idActividad" => $idActividad]);

            if (!$consultaActividad->fetchColumn()) {
                $this->conexion->rollBack();
                return ["status" => 404, "mensaje" => "La actividad no existe o ya cerró sus inscripciones"];
            }

            $consultaAsociado = $this->conexion->prepare(
                "SELECT idAsociado FROM asociados WHERE documento = :documento"
            );
            $consultaAsociado->execute([":documento" => $documento]);
            $idAsociado = $consultaAsociado->fetchColumn();

            if ($idAsociado === false) {
                $this->conexion->rollBack();
                return ["status" => 404, "mensaje" => "No se encontró el asociado"];
            }

            $consulta = $this->conexion->prepare(
                "INSERT INTO actividades_asociado (idActividad, idAsociado)
                 VALUES (:idActividad, :idAsociado)"
            );
            $consulta->execute([
                ":idActividad" => $idActividad,
                ":idAsociado" => $idAsociado
            ]);

            $this->conexion->commit();

            return ["status" => 201, "mensaje" => "Te inscribiste correctamente"];
        } catch (PDOException $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            throw $error;
        }
    }
}
