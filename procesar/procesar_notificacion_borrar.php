<?php
// procesar_notificacion_borrar.php
//
// Borra notificaciones del usuario logueado (solo las propias):
//   - id_notificacion=N  → borra esa notificación
//   - alcance=leidas     → borra las leídas que no están fijadas (botón "Borrar leídas")
//
// Si el pedido viene del JavaScript de la página (fetch) responde JSON y
// no recarga; sin JavaScript, vuelve a la lista.

session_start();

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';

$id_usuario      = (int) $_SESSION['usuario_id'];
$id_notificacion = (int) ($_POST['id_notificacion'] ?? 0);
$alcance         = $_POST['alcance'] ?? '';
$borradas        = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if ($id_notificacion > 0) {
		$stmt = $conexion->prepare('DELETE FROM notificacion WHERE id_notificacion = ? AND id_usuario = ?');
		$stmt->bind_param('ii', $id_notificacion, $id_usuario);
		$stmt->execute();
		$borradas = $stmt->affected_rows;
		$stmt->close();
	} elseif ($alcance === 'leidas') {
		$stmt = $conexion->prepare('DELETE FROM notificacion WHERE id_usuario = ? AND leida = 1 AND favorito = 0 AND (visible_desde IS NULL OR visible_desde <= NOW())');
		$stmt->bind_param('i', $id_usuario);
		$stmt->execute();
		$borradas = $stmt->affected_rows;
		$stmt->close();
	}
}

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => $borradas > 0, 'borradas' => (int) $borradas]);
	exit;
}

header('Location: ../notificaciones.php');
exit;
