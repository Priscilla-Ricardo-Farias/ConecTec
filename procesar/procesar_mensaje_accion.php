<?php
// procesar_mensaje_accion.php
//
// Opciones de un mensaje del chat (se abren manteniendo apretado el mensaje,
// como en WhatsApp):
//
//   - ocultar        → "Eliminar para mí": el mensaje deja de verse solo para
//                      quien lo borra. Sirve para cualquier mensaje de la conversación.
//   - eliminar_todos → "Eliminar para todos": solo el autor puede. El mensaje queda
//                      como "Se eliminó este mensaje" para los dos y se borra su texto.
//   - eliminar_chat  → "Eliminar chat": oculta TODOS los mensajes de la conversación
//                      para quien lo pide. La otra persona sigue viendo todo, y el chat
//                      vuelve a aparecer solo si llega un mensaje nuevo.

session_start();

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_mensajes.php';

exigir_abono_empresa($conexion, '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../mensajes.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];
$id_mensaje = (int) ($_POST['id_mensaje'] ?? 0);
$accion     = $_POST['accion'] ?? '';

/* =========================================================
   ELIMINAR CHAT (todos los mensajes, solo para mí)
   ========================================================= */
if ($accion === 'eliminar_chat') {
	$id_conversacion = (int) ($_POST['id_conversacion'] ?? 0);

	if ($id_conversacion <= 0 || !pertenece_a_conversacion($conexion, $id_conversacion, $id_usuario)) {
		header('Location: ../mensajes.php');
		exit;
	}

	try {
		$stmt = $conexion->prepare('INSERT IGNORE INTO mensaje_oculto (id_mensaje, id_usuario) SELECT id_mensaje, ? FROM mensaje WHERE id_conversacion = ?');
		$stmt->bind_param('ii', $id_usuario, $id_conversacion);
		$stmt->execute();
		$stmt->close();
	} catch (mysqli_sql_exception $e) {
		header('Location: ../chat.php?id=' . $id_conversacion . '&msg=error');
		exit;
	}

	header('Location: ../mensajes.php?chat=eliminado');
	exit;
}

if ($id_mensaje <= 0 || !in_array($accion, ['ocultar', 'eliminar_todos'], true)) {
	header('Location: ../mensajes.php');
	exit;
}

// El mensaje tiene que existir y ser de una conversación en la que participo.
$stmt = $conexion->prepare('SELECT id_conversacion, id_usuario FROM mensaje WHERE id_mensaje = ?');
$stmt->bind_param('i', $id_mensaje);
$stmt->execute();
$mensaje = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mensaje || !pertenece_a_conversacion($conexion, (int) $mensaje['id_conversacion'], $id_usuario)) {
	header('Location: ../mensajes.php');
	exit;
}

$id_conversacion = (int) $mensaje['id_conversacion'];

try {
	if ($accion === 'ocultar') {
		$stmt = $conexion->prepare('INSERT IGNORE INTO mensaje_oculto (id_mensaje, id_usuario) VALUES (?, ?)');
		$stmt->bind_param('ii', $id_mensaje, $id_usuario);
		$stmt->execute();
		$stmt->close();
	} else {
		// Solo el autor puede borrarlo para todos.
		if ((int) $mensaje['id_usuario'] !== $id_usuario) {
			header('Location: ../chat.php?id=' . $id_conversacion);
			exit;
		}

		$stmt = $conexion->prepare("UPDATE mensaje SET eliminado_para_todos = 1, contenido = '' WHERE id_mensaje = ? AND id_usuario = ?");
		$stmt->bind_param('ii', $id_mensaje, $id_usuario);
		$stmt->execute();
		$stmt->close();
	}
} catch (mysqli_sql_exception $e) {
	// por ejemplo, si todavía no se corrió sql/migracion_cambios_21oct.sql
	header('Location: ../chat.php?id=' . $id_conversacion . '&msg=error');
	exit;
}

header('Location: ../chat.php?id=' . $id_conversacion);
exit;
