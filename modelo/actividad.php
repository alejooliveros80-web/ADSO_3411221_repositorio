<?php

require_once __DIR__ . '/../Config/conexion.php';

class Actividad {
    private $conexion;

    public function __construct() {
        $conexion = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }



public function obtenerActividades(){
    $sql = "SELECT * FROM actividades ORDER BY fechaEvento ASC";
    $consulta = $this->conexion->prepare($sql);
    $consulta->execute();
    return $consulta->fetchAll();
}

public function guardar(){
    $sql = "INSERT INTO actividades (nombreActividad, municipio, fechaEvento) VALUES (:nombreActividad, :municipio, :fechaEvento)";
    $consulta = $this->conexion->prepare($sql);
    return $consulta->execute([
        ":nombreActividad" => $this->nombreActividad,
        ":municipio" => $this->municipio,
        ":fechaEvento" => $this->fechaEvento
    ]);
}

public function eliminar($idActividad){
    $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
    $consulta = $this->conexion->prepare($sql);
    $consulta->execute([":idActividad" => $idActividad]);
    return $consulta->rowCount() > 0; //Retorna true si se elimino alguna fila

}

public function editar($datos){
    $sql = "UPDATE actividades SET nombreActividad = :nombreActividad, municipio = :municipio, fechaEvento = :fechaEvento, totalRecaudado = :totalRecaudado WHERE idActividad = :idActividad";
    $consulta = $this->conexion->prepare($sql);
    return $consulta->execute([
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