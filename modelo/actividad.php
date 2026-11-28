<?php

require_once __DIR__ . '/../config/conexion.php';

class Actividad 
{

    private $conexion;

    public function __construct() 
    {
        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();
    }

public function obtenerActividades()
{
    $sql = "SELECT actividades.*,
        (SELECT COUNT(*)
         FROM actividades_asociado
         WHERE actividades_asociado.idActividad = actividades.idActividad)
         AS cantidadInscritos
        FROM actividades
        ORDER BY fechaEvento ASC";
        $consulta = $this->conexion->prepare($sql);
$consulta->execute();
return $consulta->fetchAll();
}

// Lista actividades para el endpoint que no necesita el total de inscritos.
public function listar()
{
    $sql = "SELECT idActividad, nombreActividad, fechaEvento, municipio, totalRecaudado
            FROM actividades
            ORDER BY fechaEvento ASC, idActividad DESC";
    $consulta = $this->conexion->prepare($sql);
    $consulta->execute();

    return $consulta->fetchAll();
}

public function guardar($datos)
{
    $sql = "INSERT INTO actividades (nombreActividad, municipio, fechaEvento, totalRecaudado) 
            VALUES (:nombreActividad, :municipio, :fechaEvento, :totalRecaudado)";
    $consulta = $this->conexion->prepare($sql);
    return $consulta->execute([
        ":nombreActividad" => $datos["nombreActividad"],
        ":municipio" => $datos["municipio"],
        ":fechaEvento" => $datos["fechaEvento"],
        ":totalRecaudado" => $datos["totalRecaudado"]
    ]);
}

// Devuelve el ID de la última actividad guardada.
public function obtenerUltimoId()
{
    return (int)$this->conexion->lastInsertId();
}

// Divide el recaudo una sola vez entre el club y quienes participaron.
public function distribuirRecaudo($idActividad)
{
    $this->conexion->beginTransaction();

    try {
        // Bloquea la actividad para evitar dos repartos simultáneos.
        $consultaActividad = $this->conexion->prepare(
            "SELECT totalRecaudado, valorClub, fechaDistribucion
             FROM actividades
             WHERE idActividad = :idActividad
             FOR UPDATE"
        );
        $consultaActividad->execute([":idActividad" => $idActividad]);
        $actividad = $consultaActividad->fetch();

        if (!$actividad) {
            $this->conexion->rollBack();
            return ["status" => 404, "mensaje" => "No se encontró la actividad"];
        }

        if ($actividad["fechaDistribucion"] !== null) {
            $this->conexion->rollBack();
            return ["status" => 409, "mensaje" => "El recaudo de esta actividad ya fue distribuido"];
        }

        // Obtiene a los asociados en orden para repartir centavos sobrantes de forma estable.
        $consultaParticipantes = $this->conexion->prepare(
            "SELECT asociados.idAsociado, asociados.nombres, asociados.apellidos,
                    asociados.documento
             FROM actividades_asociado
             INNER JOIN asociados
                ON asociados.idAsociado = actividades_asociado.idAsociado
             WHERE actividades_asociado.idActividad = :idActividad
             ORDER BY asociados.idAsociado ASC"
        );
        $consultaParticipantes->execute([":idActividad" => $idActividad]);
        $participantes = $consultaParticipantes->fetchAll();

        if (count($participantes) === 0) {
            $this->conexion->rollBack();
            return ["status" => 409, "mensaje" => "No se puede repartir: la actividad no tiene asociados inscritos"];
        }

        // Trabaja en centavos para que todas las partes sumen exactamente el recaudo.
        $totalCentavos = (int)round((float)$actividad["totalRecaudado"] * 100);
        $valorClubCentavos = (int)round($totalCentavos / 2, 0, PHP_ROUND_HALF_UP);
        $valorAsociadosCentavos = $totalCentavos - $valorClubCentavos;
        $valorBaseCentavos = intdiv($valorAsociadosCentavos, count($participantes));
        $centavosSobrantes = $valorAsociadosCentavos % count($participantes);

        $actualizarParticipacion = $this->conexion->prepare(
            "UPDATE actividades_asociado
             SET valorRepartido = :valorRepartido
             WHERE idActividad = :idActividad
               AND idAsociado = :idAsociado"
        );
        $detalleReparto = [];

        foreach ($participantes as $indice => $participante) {
            $valorParticipante = $valorBaseCentavos + ($indice < $centavosSobrantes ? 1 : 0);
            $actualizarParticipacion->execute([
                ":valorRepartido" => number_format($valorParticipante / 100, 2, ".", ""),
                ":idActividad" => $idActividad,
                ":idAsociado" => $participante["idAsociado"]
            ]);
            $detalleReparto[] = [
                "documento" => $participante["documento"],
                "nombre" => $participante["nombres"] . " " . $participante["apellidos"],
                "valorRepartido" => number_format($valorParticipante / 100, 2, ".", "")
            ];
        }

        $actualizarActividad = $this->conexion->prepare(
            "UPDATE actividades
             SET valorClub = :valorClub, fechaDistribucion = NOW()
             WHERE idActividad = :idActividad"
        );
        $actualizarActividad->execute([
            ":valorClub" => number_format($valorClubCentavos / 100, 2, ".", ""),
            ":idActividad" => $idActividad
        ]);

        $this->conexion->commit();

        return [
            "status" => 200,
            "mensaje" => "Recaudo distribuido correctamente",
            "valorClub" => number_format($valorClubCentavos / 100, 2, ".", ""),
            "valorAsociados" => number_format($valorAsociadosCentavos / 100, 2, ".", ""),
            "cantidadAsociados" => count($participantes),
            "repartoAsociados" => $detalleReparto
        ];
    } catch (PDOException $error) {
        if ($this->conexion->inTransaction()) {
            $this->conexion->rollBack();
        }

        throw $error;
    }
}

public function eliminar($idActividad)
{
    $sql = "DELETE FROM actividades
            WHERE idActividad = :idActividad
              AND fechaDistribucion IS NULL";
    $consulta = $this->conexion->prepare($sql);
    $consulta->execute([":idActividad" => $idActividad]);
    return $consulta->rowCount() > 0; //Retorna true si se elimino alguna fila
}

public function editar($datos){
    $sql = "UPDATE actividades
            SET nombreActividad = :nombreActividad, municipio = :municipio,
                fechaEvento = :fechaEvento, totalRecaudado = :totalRecaudado
            WHERE idActividad = :idActividad
              AND fechaDistribucion IS NULL";
    $consulta = $this->conexion->prepare($sql);
    $ejecutada = $consulta->execute([
        ":nombreActividad" => $datos['nombreActividad'],
        ":municipio" => $datos['municipio'],
        ":fechaEvento" => $datos['fechaEvento'],
        ":totalRecaudado" => $datos['totalRecaudado'],
        ":idActividad" => $datos['idActividad']
    ]);

    if ($consulta->rowCount() > 0) {
        return true; // Retorna true si se actualizó alguna fila
    }
    
    $verificar = $this->conexion->prepare(
        "SELECT 1 FROM actividades
         WHERE idActividad = :idActividad
           AND fechaDistribucion IS NULL"
    );
    $verificar->execute([":idActividad" => $datos['idActividad']]);

    return (bool)$verificar->fetchColumn(); // Retorna true si el registro existe, false si no
}
}
