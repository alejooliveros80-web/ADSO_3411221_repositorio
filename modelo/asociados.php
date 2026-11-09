<?php
require_once __DIR__ . '/../config/conexion.php';

class Asociado {
    private $documento;
    private $nombres;
    private $apellidos;
    private $fechaNacimiento;
    private $genero;
    private $direccionResidencia;
    private $ciudad;
    private $telefono;
    private $email;
    private $conexion;
    private $panswoard;

    public function __construct($datos) {
        $this->documento           = $datos['documento'] ?? '';
        $this->nombres             = $datos['nombres'] ?? $datos['nombre'] ?? '';
        $this->apellidos           = $datos['apellidos'] ?? $datos['apellido'] ?? '';
        $this->fechaNacimiento     = $datos['fechaNacimiento'] ?? '';
        $this->genero              = $datos['genero'] ?? '';
        $this->direccionResidencia = $datos['direccionResidencia'] ?? $datos['direccion'] ?? '';
        $this->ciudad              = $datos['ciudad'] ?? $datos['cuidad'] ?? '';
        $this->telefono            = $datos['telefono'] ?? '';
        $this->email               = $datos['email'] ?? '';
        $this->panswoard = password_hash($datos['panswoard'], PASSWORD_DEFAULT);

        $conexionBD = new Conexion();
        $this->conexion = $conexionBD->conectar();
    }

    public function guardarAsociado() {
        $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, telefono, email, panswoard) 
                VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direccionResidencia, :ciudad, :telefono, :email, :panswoard)";

        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':documento'           => $this->documento,
            ':nombres'             => $this->nombres,
            ':apellidos'           => $this->apellidos,
            ':fechaNacimiento'     => $this->fechaNacimiento,
            ':genero'              => $this->genero,
            ':direccionResidencia' => $this->direccionResidencia,
            ':ciudad'              => $this->ciudad,
            ':telefono'            => $this->telefono,
            ':email'      => $this->email,
            ':panswoard'  => $this->panswoard
        ]);
    }
}