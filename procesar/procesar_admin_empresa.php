<?php
// procesar_admin_empresa.php
//
// Cambia empleador.verificada según lo que decida el admin en
// verificaciones.php / panel_admin.php. A diferencia de tecnico, que
// tiene un ENUM pendiente/verificado/rechazado, empleador solo tiene
// un booleano "verificada" (0/1) en el schema actual — así que
// "rechazar" simplemente la deja/pone en 0 (sigue sin poder operar
// como empresa hasta que se apruebe). Si en algún momento hace falta
// distinguir "nunca revisada" de "rechazada explícitamente", habría
// que sumar una columna como en tecnico.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/conexion.php';

$id_empleador = (int) ($_POST['id_empleador'] ?? 0);
$accion       = $_POST['accion'] ?? '';

$acciones_validas = [
	'aprobar'  => 1,
	'rechazar' => 0,
];

if ($id_empleador <= 0 || !isset($acciones_validas[$accion])) {
	header('Location: verificaciones.php');
	exit;
}

$nuevo_estado = $acciones_validas[$accion];

$stmt = $conexion->prepare('UPDATE empleador SET verificada = ? WHERE id_empleador = ?');
$stmt->bind_param('ii', $nuevo_estado, $id_empleador);
$stmt->execute();
$stmt->close();

$destino = $_POST['volver_a'] ?? 'verificaciones.php';
$destino = in_array($destino, ['verificaciones.php', 'panel_admin.php'], true) ? $destino : 'verificaciones.php';

header('Location: ' . $destino . '?actualizado=1');
exit;
