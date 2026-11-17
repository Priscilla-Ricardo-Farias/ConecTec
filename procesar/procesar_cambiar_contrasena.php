<?php
// procesar_cambiar_contrasena.php
//
// Recibe el formulario de cambiar_contrasena.php. Verifica la clave
// actual contra el hash guardado con password_verify() antes de
// aceptar el cambio, y guarda la nueva con password_hash().

session_start();
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '../');

$clave_actual           = $_POST['clave_actual'] ?? '';
$clave_nueva             = $_POST['clave_nueva'] ?? '';
$clave_nueva_confirmar   = $_POST['clave_nueva_confirmar'] ?? '';

if ($clave_actual === '' || strlen($clave_nueva) < 6 || $clave_nueva !== $clave_nueva_confirmar) {
	header('Location: ../cambiar_contrasena.php?error=1');
	exit;
}

// Trae el hash guardado del usuario logueado
$stmt = $conexion->prepare('SELECT contrasena FROM usuario WHERE id_usuario = ?');
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario || !password_verify($clave_actual, $usuario['contrasena'])) {
	header('Location: ../cambiar_contrasena.php?error=clave_incorrecta');
	exit;
}

$clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);

$stmt = $conexion->prepare('UPDATE usuario SET contrasena = ? WHERE id_usuario = ?');
$stmt->bind_param('si', $clave_hash, $_SESSION['usuario_id']);
$stmt->execute();
$stmt->close();

header('Location: ../ajustes.php?guardado=1#cuenta');
exit;