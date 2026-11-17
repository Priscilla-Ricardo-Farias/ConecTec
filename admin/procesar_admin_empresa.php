<?php
// procesar_admin_empresa.php
//
// Aprobar / rechazar una empresa desde verificaciones.php o
// panel_admin.php.
//
//   - APROBAR  → empleador.verificada = 1. Junto con el abono pago,
//                es lo que habilita a la empresa a usar la plataforma
//                (ver exigir_empresa_habilitada()).
//   - RECHAZAR → antes solo dejaba verificada en 0, que es el mismo
//                valor que "todavía sin revisar": la empresa seguía
//                apareciendo como pendiente para siempre. Ahora,
//                además, se da de baja la cuenta (usuario.estado_cuenta
//                = 'eliminada'): sale de la lista de pendientes y no
//                puede iniciar sesión. Queda visible en Empresas con el
//                estado "Eliminada"; si el admin la restaura, vuelve a
//                pendientes para revisarla de nuevo.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$id_empleador = (int) ($_POST['id_empleador'] ?? 0);
$accion       = $_POST['accion'] ?? '';

$destino = $_POST['volver_a'] ?? 'verificaciones.php';
$destino = in_array($destino, ['verificaciones.php', 'panel_admin.php'], true) ? $destino : 'verificaciones.php';

if ($id_empleador <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
	header('Location: verificaciones.php');
	exit;
}

try {
	$conexion->begin_transaction();

	if ($accion === 'aprobar') {
		$stmt = $conexion->prepare('UPDATE empleador SET verificada = 1 WHERE id_empleador = ?');
		$stmt->bind_param('i', $id_empleador);
		$stmt->execute();
		$stmt->close();
	} else {
		$stmt = $conexion->prepare('UPDATE empleador SET verificada = 0 WHERE id_empleador = ?');
		$stmt->bind_param('i', $id_empleador);
		$stmt->execute();
		$stmt->close();

		$stmt = $conexion->prepare(
			"UPDATE usuario u
			 JOIN empleador e ON e.id_usuario = u.id_usuario
			 SET u.estado_cuenta = 'eliminada'
			 WHERE e.id_empleador = ?"
		);
		$stmt->bind_param('i', $id_empleador);
		$stmt->execute();
		$stmt->close();
	}

	$conexion->commit();
} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	header('Location: ' . $destino . '?error=bd');
	exit;
}

header('Location: ' . $destino . '?actualizado=1');
exit;
