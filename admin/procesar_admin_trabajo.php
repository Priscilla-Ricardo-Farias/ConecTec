<?php
// procesar_admin_trabajo.php
//
// Borra una publicación. A diferencia de procesar_admin_usuario.php,
// acá SÍ se hace un DELETE real (no un cambio de estado): el pedido
// fue "eliminarla" cuando ya está terminada, vieja o es inapropiada,
// y no hay motivo para conservar una publicación descartada. Las
// filas relacionadas (trabajo_cliente, trabajo_empleador,
// postulacion) se van solas por el ON DELETE CASCADE del schema.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$id_trabajo = (int) ($_POST['id_trabajo'] ?? 0);
$accion     = $_POST['accion'] ?? '';

if ($id_trabajo <= 0 || $accion !== 'eliminar') {
	header('Location: admin_trabajos.php');
	exit;
}

try {
	$stmt = $conexion->prepare('DELETE FROM trabajo WHERE id_trabajo = ?');
	$stmt->bind_param('i', $id_trabajo);
	$stmt->execute();
	$stmt->close();
} catch (mysqli_sql_exception $e) {
	header('Location: admin_trabajos.php?error=bd');
	exit;
}

header('Location: admin_trabajos.php?actualizado=1');
exit;
