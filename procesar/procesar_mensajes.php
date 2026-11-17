<?php
// procesar_mensaje.php
//
// Recibe el formulario de chat.php e inserta el mensaje. Antes de
// guardar nada, verifica que el usuario logueado sea uno de los dos
// participantes de esa conversación (si no, alguien está tratando de
// escribir en una conversación ajena con solo cambiar el id).

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_mensajes.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_notificaciones.php';

exigir_abono_empresa($conexion, '../');

$id_conversacion = (int) ($_POST['id_conversacion'] ?? 0);
$contenido       = trim($_POST['contenido'] ?? '');

if ($id_conversacion <= 0 || $contenido === '') {
	header('Location: ../mensajes.php');
	exit;
}

if (!pertenece_a_conversacion($conexion, $id_conversacion, $_SESSION['usuario_id'])) {
	header('Location: ../mensajes.php');
	exit;
}

$stmt = $conexion->prepare('INSERT INTO mensaje (id_conversacion, id_usuario, contenido) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $id_conversacion, $_SESSION['usuario_id'], $contenido);
$stmt->execute();
$stmt->close();

// Avisa a la otra persona de la conversación (si tiene "Mensajes" activado)
$stmt = $conexion->prepare('SELECT id_usuario FROM usuario_conversacion WHERE id_conversacion = ? AND id_usuario <> ? LIMIT 1');
$stmt->bind_param('ii', $id_conversacion, $_SESSION['usuario_id']);
$stmt->execute();
$otro = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($otro) {
	notificar_mensaje_nuevo($conexion, (int) $otro['id_usuario'], (int) $_SESSION['usuario_id']);
}

header('Location: ../chat.php?id=' . $id_conversacion);
exit;