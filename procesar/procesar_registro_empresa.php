<?php
// procesar_registro_empresa.php
//
// Recibe los datos del formulario de registro_empresa.php y crea
// una cuenta de tipo "empresa" en la base de datos real.
//
// La cuenta de empresa no tiene un "nombre de persona" separado:
// el nombre de la empresa es el nombre de la cuenta (usuario.nombre)
// y es lo que se muestra en el header, en las publicaciones, etc.

session_start();
require __DIR__ . '/../conexion.php';

$nombre_empresa  = trim($_POST['nombre_empresa'] ?? '');
$cuit            = trim($_POST['cuit'] ?? '');
$correo          = trim($_POST['correo'] ?? '');
$clave           = $_POST['clave'] ?? '';
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';

if ($nombre_empresa === '' || $cuit === '' || $correo === '' || $clave === '' || $fecha_nacimiento === '') {
	header('Location: ../registro_empresa.php?error=campos_incompletos');
	exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
	header('Location: ../registro_empresa.php?error=correo_invalido');
	exit;
}

// El CUIT tiene que venir con el formato XX-XXXXXXXX-X (11 dígitos, 13
// caracteres en total) para que el admin lo pueda verificar después.
if (!preg_match('/^\d{2}-\d{8}-\d{1}$/', $cuit)) {
	header('Location: ../registro_empresa.php?error=cuit_invalido');
	exit;
}

// Chequear que el correo no esté usado ya
$stmt = $conexion->prepare('SELECT id_usuario FROM usuario WHERE email = ?');
$stmt->bind_param('s', $correo);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
	$stmt->close();
	header('Location: ../registro_empresa.php?error=correo_registrado');
	exit;
}
$stmt->close();

// Chequear que el CUIT no esté ya registrado por otra empresa
$stmt = $conexion->prepare('SELECT id_empleador FROM empleador WHERE cuit = ?');
$stmt->bind_param('s', $cuit);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
	$stmt->close();
	header('Location: ../registro_empresa.php?error=cuit_registrado');
	exit;
}
$stmt->close();

$clave_hash = password_hash($clave, PASSWORD_DEFAULT);

$conexion->begin_transaction();

try {
	// 1) Insertar en usuario (el nombre de la cuenta es el nombre de la empresa)
	$stmt = $conexion->prepare('INSERT INTO usuario (nombre, email, contrasena, fecha_nacimiento) VALUES (?, ?, ?, ?)');
	$stmt->bind_param('ssss', $nombre_empresa, $correo, $clave_hash, $fecha_nacimiento);
	$stmt->execute();
	$id_usuario = $conexion->insert_id;
	$stmt->close();

	// 2) Insertar en empleador (verificada queda en 0 por defecto, hasta que el admin la apruebe)
	$stmt = $conexion->prepare('INSERT INTO empleador (id_usuario, cuit, empresa) VALUES (?, ?, ?)');
	$stmt->bind_param('iss', $id_usuario, $cuit, $nombre_empresa);
	$stmt->execute();
	$stmt->close();

	$conexion->commit();

} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	header('Location: ../registro_empresa.php?error=error_servidor');
	exit;
}

$_SESSION['usuario_id']     = $id_usuario;
$_SESSION['usuario_nombre'] = $nombre_empresa;
$_SESSION['usuario_correo'] = $correo;
$_SESSION['usuario_tipo']   = 'empresa';
$_SESSION['nombre_empresa'] = $nombre_empresa;

header('Location: ../feed.php');
exit;
