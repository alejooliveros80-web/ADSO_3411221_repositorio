<?php 

class conexion
{
    private $host = "localhost";
    private $db_name = "clubPrueba";
    private $username = "root";
    private $password = "";

    public function conectar()
    {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";

        return new PDO($dsn, $this->username, $this->password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    }
}