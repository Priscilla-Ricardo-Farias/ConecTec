<?php

$host   = 'localhost';
$bd     = 'conectec';
$user   = 'root';
$clave  = '';   

$conexion = new mysqli($host, $user, $clave, $bd);

if ($conexion->connect_error) {
	die('Error de conexión a la base de datos: ' . $conexion->connect_error);
}

// Fuerza UTF-8 en la conexión, para que tildes y ñ no se rompan
$conexion->set_charset('utf8mb4');