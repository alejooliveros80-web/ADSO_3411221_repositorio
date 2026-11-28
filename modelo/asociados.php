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
        $this->panswoard = isset($datos['panswoard'])
            ? password_hash($datos['panswoard'], PASSWORD_DEFAULT)
            : null;

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

    // Busca una cuenta por correo para validar el inicio de sesión.
    public function obtenerPorEmail($email)
    {
        $sql = "SELECT * FROM asociados WHERE email = :email";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":email" => $email]);

        return $consulta->fetch();
    }

    // Lista los asociados sin incluir contraseñas ni cuentas de administrador.
    public function listarAsociados()
    {
        $sql = "SELECT idAsociado, documento, nombres, apellidos,
                       fechaNacimiento, genero, direccionResidencia,
                       ciudad, telefono, email
                FROM asociados
                WHERE LOWER(rol) = 'asociado'
                ORDER BY apellidos ASC, nombres ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();

        return $consulta->fetchAll();
    }

    // Actualiza los datos personales y cambia la contraseña solo si se envió una nueva.
    public function editarAsociado($idAsociado, $datos)
    {
        $campos = "documento = :documento,
                   nombres = :nombres,
                   apellidos = :apellidos,
                   fechaNacimiento = :fechaNacimiento,
                   genero = :genero,
                   direccionResidencia = :direccionResidencia,
                   ciudad = :ciudad,
                   telefono = :telefono,
                   email = :email";

        if (!empty($datos["panswoard"])) {
            $campos .= ", panswoard = :panswoard";
        }

        $sql = "UPDATE asociados SET $campos
                WHERE idAsociado = :idAsociado
                  AND LOWER(rol) = 'asociado'";
        $consulta = $this->conexion->prepare($sql);

        $parametros = [
            ":documento" => $datos["documento"],
            ":nombres" => $datos["nombres"],
            ":apellidos" => $datos["apellidos"],
            ":fechaNacimiento" => $datos["fechaNacimiento"],
            ":genero" => $datos["genero"],
            ":direccionResidencia" => $datos["direccionResidencia"],
            ":ciudad" => $datos["ciudad"],
            ":telefono" => $datos["telefono"],
            ":email" => $datos["email"],
            ":idAsociado" => $idAsociado
        ];

        if (!empty($datos["panswoard"])) {
            $parametros[":panswoard"] = password_hash($datos["panswoard"], PASSWORD_DEFAULT);
        }

        $consulta->execute($parametros);

        // Comprueba que el asociado exista incluso si los datos enviados eran iguales.
        if ($consulta->rowCount() > 0) {
            return true;
        }

        $verificar = $this->conexion->prepare(
            "SELECT 1 FROM asociados
             WHERE idAsociado = :idAsociado
               AND LOWER(rol) = 'asociado'"
        );
        $verificar->execute([":idAsociado" => $idAsociado]);

        return (bool)$verificar->fetchColumn();
    }

    // Elimina solamente cuentas de asociado, nunca cuentas de administrador.
    public function eliminarAsociado($idAsociado)
    {
        $sql = "DELETE FROM asociados
                WHERE idAsociado = :idAsociado
                  AND LOWER(rol) = 'asociado'";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":idAsociado" => $idAsociado]);

        return $consulta->rowCount() > 0;
    }
}
