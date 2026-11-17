<?php
// procesar_registro_usuario.php
//
// Recibe los datos del formulario de registro_usuario.php y crea
// una cuenta de tipo "cliente" en la base de datos real.

session_start();
require __DIR__ . '/../conexion.php';

$nombre           = trim($_POST['nombre'] ?? '');
$correo           = trim($_POST['correo'] ?? '');
$clave            = $_POST['clave'] ?? '';
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';

if ($nombre === '' || $correo === '' || $clave === '' || $fecha_nacimiento === '') {
	header('Location: ../registro_usuario.php?error=campos_incompletos');
	exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
	header('Location: ../registro_usuario.php?error=correo_invalido');
	exit;
}

// Chequear que el correo no esté usado ya
$stmt = $conexion->prepare('SELECT id_usuario FROM usuario WHERE email = ?');
$stmt->bind_param('s', $correo);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
	$stmt->close();
	header('Location: ../registro_usuario.php?error=correo_registrado');
	exit;
}
$stmt->close();

$clave_hash = password_hash($clave, PASSWORD_DEFAULT);

$conexion->begin_transaction();

try {
	// 1) Insertar en usuario (apellido queda NULL: ningún form lo pide todavía)
	$stmt = $conexion->prepare('INSERT INTO usuario (nombre, email, contrasena, fecha_nacimiento) VALUES (?, ?, ?, ?)');
	$stmt->bind_param('ssss', $nombre, $correo, $clave_hash, $fecha_nacimiento);
	$stmt->execute();
	$id_usuario = $conexion->insert_id;
	$stmt->close();

	// 2) Insertar en cliente, apuntando al usuario recién creado
	$stmt = $conexion->prepare('INSERT INTO cliente (id_usuario) VALUES (?)');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$stmt->close();

	$conexion->commit();

} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	header('Location: ../registro_usuario.php?error=error_servidor');
	exit;
}

$_SESSION['usuario_id']     = $id_usuario;
$_SESSION['usuario_nombre'] = $nombre;
$_SESSION['usuario_correo'] = $correo;
$_SESSION['usuario_tipo']   = 'cliente';

header('Location: ../feed.php');
exit;