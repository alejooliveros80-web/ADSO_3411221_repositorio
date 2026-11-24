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

public function eliminar($idActividad)
{
    $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
    $consulta = $this->conexion->prepare($sql);
    $consulta->execute([":idActividad" => $idActividad]);
    return $consulta->rowCount() > 0; //Retorna true si se elimino alguna fila
}

public function editar($datos){
    $sql = "UPDATE actividades SET nombreActividad = :nombreActividad, municipio = :municipio, fechaEvento = :fechaEvento, totalRecaudado = :totalRecaudado WHERE idActividad = :idActividad";
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
    
    $verificar = $this->conexion->prepare("SELECT 1 FROM actividades WHERE idActividad = :idActividad");
    $verificar->execute([":idActividad" => $datos['idActividad']]);

    return (bool)$verificar->fetchColumn(); // Retorna true si el registro existe, false si no
}
}