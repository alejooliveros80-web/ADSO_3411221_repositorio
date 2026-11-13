<?php
require_once __DIR__ . '/../config/conexion.php';

class Actividad
{
    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        // Asignamos la conexión a la propiedad privada correctamente
        $this->conexion = $conexionDB->conectar();
    }

    public function obtenerActividades()
    {
        $sql = "SELECT * FROM actividades ORDER BY fechaEvento ASC";

        // Preparamos la consulta usando la propiedad de la clase
        $consulta = $this->conexion->prepare($sql);

        // Ejecutamos la consulta sobre la variable $consulta
        $consulta->execute();

        // Retornamos los resultados
        return $consulta->fetchAll();
    }

    public function guardar($datos)
    {
        $sql = "INSERT INTO actividades
        (nombreActividad, fechaEvento, municipio, totalRecaudado)
        VALUES (:nombreActividad, :fechaEvento, :municipio, :totalRecaudado)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
                ":nombreActividad" => $datos['nombreActividad'],
                ":fechaEvento" => $datos['fechaEvento'],
                ":municipio" => $datos['municipio']
                ":totalRecaudado" => $datos["totalRecaudado"]
            ]);
    }

    public function eliminar($idActividad) {
        $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idActividad' => $idActividad]);
        return $consulta->rowCount() > 0; // Retorna true si se elimino alguna fila
    }

public function editar($datos){
    $sql = "UPDATE actividades SET nombreActividad = :nombreActividad, fechaEvento = :fechaEvento, municipio = :municipio, totalRecaudado = :totalRecaudado WHERE idActividad = :idActividad";
    $consulta = $this->conexion->prepare($sql);
    return $consulta->execute([
        ":nombreActividad" => $datos['nombreActividad'],
        ":fechaEvento" => $datos['fechaEvento'],
        ":municipio" => $datos['municipio'],
        ":totalRecaudado" => $datos['totalRecaudado'],
        ":idActividad" => $datos['idActividad']

]);

if ($consulta -> rowCount() > 0){
    return true;

}

}
}