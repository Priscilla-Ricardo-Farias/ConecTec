<?php
// procesar_login.php
//
// Recibe los datos del formulario de login.php. Busca el correo en
// la base de datos y valida la contraseña con password_verify()
// contra el hash guardado (nunca en texto plano).
//
// Nota: login.php pide "nombre" y "correo" además de la clave, pero
// para identificar al usuario alcanza con el correo (es el campo
// UNIQUE en la tabla usuario) — el nombre no se usa para buscar, así
// que si en algún momento querés simplificar el form, se le puede
// sacar el campo "nombre" sin romper nada de esto.

session_start();
require __DIR__ . '/../conexion.php';

$correo = trim($_POST['correo'] ?? '');
$clave  = $_POST['clave'] ?? '';

if ($correo === '' || $clave === '') {
	header('Location: ../login.php?error=campos_incompletos');
	exit;
}

$stmt = $conexion->prepare('SELECT id_usuario, nombre, contrasena, estado_cuenta FROM usuario WHERE email = ?');
$stmt->bind_param('s', $correo);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario || !password_verify($clave, $usuario['contrasena'])) {
	header('Location: ../login.php?error=credenciales_invalidas');
	exit;
}

if ($usuario['estado_cuenta'] === 'eliminada') {
	header('Location: ../login.php?error=cuenta_eliminada');
	exit;
}

if ($usuario['estado_cuenta'] === 'suspendida') {
	header('Location: ../login.php?error=cuenta_suspendida');
	exit;
}

/* =========================================================
   AVERIGUAR EL TIPO DE CUENTA
   Se fija en qué tabla tiene fila este id_usuario: cliente, tecnico
   o empleador. Se consultan las tres porque, a diferencia del resto
   del proyecto, el login no sabe de antemano qué tipo de cuenta es.
   ========================================================= */

$tipo_cuenta    = 'cliente';
$nombre_empresa = null;

$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $usuario['id_usuario']);
$stmt->execute();
$es_tecnico = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($es_tecnico) {
	$tipo_cuenta = 'tecnico';
} else {
	$stmt = $conexion->prepare('SELECT empresa FROM empleador WHERE id_usuario = ?');
	$stmt->bind_param('i', $usuario['id_usuario']);
	$stmt->execute();
	$es_empleador = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($es_empleador) {
		$tipo_cuenta    = 'empresa';
		$nombre_empresa = $es_empleador['empresa'];
	}
	// Si no es técnico ni empleador, se queda como 'cliente' (default).
}

$_SESSION['usuario_id']     = $usuario['id_usuario'];
$_SESSION['usuario_nombre'] = $usuario['nombre'];
$_SESSION['usuario_correo'] = $correo;
$_SESSION['usuario_tipo']   = $tipo_cuenta;

if ($nombre_empresa !== null) {
	$_SESSION['nombre_empresa'] = $nombre_empresa;
}

header('Location: ../feed.php');
exit;