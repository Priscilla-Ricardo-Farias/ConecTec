<?php
// procesar_admin_tecnico.php
//
// Cambia tecnico.estado_verificacion según lo que decida el admin en
// admin_tecnicos.php.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/conexion.php';

$id_tecnico = (int) ($_POST['id_tecnico'] ?? 0);
$accion     = $_POST['accion'] ?? '';

$acciones_validas = [
	'aprobar'  => 'verificado',
	'rechazar' => 'rechazado',
];

if ($id_tecnico <= 0 || !isset($acciones_validas[$accion])) {
	header('Location: admin_tecnicos.php');
	exit;
}

$nuevo_estado = $acciones_validas[$accion];

$stmt = $conexion->prepare('UPDATE tecnico SET estado_verificacion = ? WHERE id_tecnico = ?');
$stmt->bind_param('si', $nuevo_estado, $id_tecnico);
$stmt->execute();
$stmt->close();

$destino = $_POST['volver_a'] ?? 'admin_tecnicos.php';
$destino = in_array($destino, ['verificaciones.php', 'panel_admin.php', 'admin_tecnicos.php'], true) ? $destino : 'admin_tecnicos.php';

header('Location: ' . $destino . '?actualizado=1');
exit;
