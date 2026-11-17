<?php
// procesar_notificacion_favorito.php
//
// La estrellita de notificaciones.php: fija / desfija una notificación
// (como destacar un mail en Gmail). Solo se puede tocar una notificación
// propia. Si el pedido viene del JavaScript de la página (fetch), responde
// JSON y no recarga; sin JavaScript, vuelve a la lista.

session_start();

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';

$id_usuario      = (int) $_SESSION['usuario_id'];
$id_notificacion = (int) ($_POST['id_notificacion'] ?? 0);
$favorito        = null;
$limite          = false;
const MAX_FIJADAS = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_notificacion > 0) {
	// Máximo MAX_FIJADAS fijadas: para fijar otra hay que soltar una.
	$stmt = $conexion->prepare('SELECT COUNT(*) AS n FROM notificacion WHERE id_usuario = ? AND favorito = 1');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$cantidad = (int) $stmt->get_result()->fetch_assoc()['n'];
	$stmt->close();

	$stmt = $conexion->prepare('SELECT favorito FROM notificacion WHERE id_notificacion = ? AND id_usuario = ?');
	$stmt->bind_param('ii', $id_notificacion, $id_usuario);
	$stmt->execute();
	$actual = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($actual && !$actual['favorito'] && $cantidad >= MAX_FIJADAS) {
		$limite = true;
	} else {
		$stmt = $conexion->prepare('UPDATE notificacion SET favorito = 1 - favorito WHERE id_notificacion = ? AND id_usuario = ?');
		$stmt->bind_param('ii', $id_notificacion, $id_usuario);
		$stmt->execute();
		$stmt->close();
	}

	$stmt = $conexion->prepare('SELECT favorito FROM notificacion WHERE id_notificacion = ? AND id_usuario = ?');
	$stmt->bind_param('ii', $id_notificacion, $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($fila) {
		$favorito = (int) $fila['favorito'];
	}
}

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => $favorito !== null && !$limite, 'favorito' => (int) $favorito, 'limite' => $limite, 'max' => MAX_FIJADAS]);
	exit;
}

// Desde el detalle de una notificación se vuelve al detalle (solo a ese lugar).
$volver = $_POST['volver'] ?? '';
if (preg_match('/^notificaciones\.php\?ver=\d+$/', $volver)) {
	header('Location: ../' . $volver . ($limite ? '&limite=1' : ''));
	exit;
}

header('Location: ../notificaciones.php' . ($limite ? '?limite=1' : ''));
exit;
