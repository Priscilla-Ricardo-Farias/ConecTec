<?php
// procesar_admin_usuario.php
//
// Recibe las acciones del panel admin_usuarios.php. Solo cambia
// usuario.estado_cuenta — nunca borra la fila, para no perder el
// historial de publicaciones, postulaciones y calificaciones
// asociadas a ese usuario.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/conexion.php';

$id_usuario = (int) ($_POST['id_usuario'] ?? 0);
$accion     = $_POST['accion'] ?? '';

$acciones_validas = [
	'suspender' => 'suspendida',
	'reactivar' => 'activa',
	'eliminar'  => 'eliminada',
];

if ($id_usuario <= 0 || !isset($acciones_validas[$accion])) {
	header('Location: admin_usuarios.php');
	exit;
}

$nuevo_estado = $acciones_validas[$accion];

$stmt = $conexion->prepare('UPDATE usuario SET estado_cuenta = ? WHERE id_usuario = ?');
$stmt->bind_param('si', $nuevo_estado, $id_usuario);
$stmt->execute();
$stmt->close();

$destino = $_POST['volver_a'] ?? 'admin_usuarios.php';
$destino = in_array($destino, ['admin_usuarios.php', 'admin_tecnicos.php', 'admin_empresas.php'], true) ? $destino : 'admin_usuarios.php';

header('Location: ' . $destino . '?actualizado=1');
exit;
