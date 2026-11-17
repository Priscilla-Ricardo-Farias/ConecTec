<?php
// procesar_login_admin.php
//
// Valida las credenciales contra la tabla "admin" (no "usuario").
// Igual que el login normal, la contraseña se compara con
// password_verify() contra el hash guardado.

session_start();
require __DIR__ . '/conexion.php';

$usuario = trim($_POST['usuario'] ?? '');
$clave   = $_POST['clave'] ?? '';

if ($usuario === '' || $clave === '') {
	header('Location: login_admin.php?error=1');
	exit;
}

$stmt = $conexion->prepare('SELECT id_admin, usuario, contrasena FROM admin WHERE usuario = ?');
$stmt->bind_param('s', $usuario);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || !password_verify($clave, $admin['contrasena'])) {
	header('Location: login_admin.php?error=1');
	exit;
}

$_SESSION['admin_id']      = $admin['id_admin'];
$_SESSION['admin_usuario'] = $admin['usuario'];

header('Location: panel_admin.php');
exit;
