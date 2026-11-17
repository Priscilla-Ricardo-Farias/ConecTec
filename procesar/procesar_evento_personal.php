<?php
// procesar_evento_personal.php
//
// Eventos que cada persona agrega a SU calendario (calendario.php), por
// ejemplo desde un mensaje del chat que dice una fecha:
//
//   - agregar → crea el evento (o lo actualiza si ya se había agregado ese mismo mensaje)
//   - quitar  → borra un evento propio

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
	header('Location: ../calendario.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];
$accion     = $_POST['accion'] ?? '';

/* =========================================================
   QUITAR (desde el calendario)
   ========================================================= */
if ($accion === 'quitar') {
	$id_evento = (int) ($_POST['id_evento_personal'] ?? 0);

	if ($id_evento > 0) {
		// Se filtra por id_usuario: nadie borra eventos ajenos.
		$stmt = $conexion->prepare('DELETE FROM evento_personal WHERE id_evento_personal = ? AND id_usuario = ?');
		$stmt->bind_param('ii', $id_evento, $id_usuario);
		$stmt->execute();
		$stmt->close();
	}

	$anio = (int) ($_POST['anio'] ?? 0);
	$mes  = (int) ($_POST['mes'] ?? 0);
	$vuelta = ($anio >= 2000 && $anio <= 2100 && $mes >= 1 && $mes <= 12) ? '?anio=' . $anio . '&mes=' . $mes : '';

	header('Location: ../calendario.php' . $vuelta);
	exit;
}

/* =========================================================
   AGREGAR (desde el chat)
   ========================================================= */
if ($accion === 'agregar') {
	$id_mensaje      = (int) ($_POST['id_mensaje'] ?? 0);
	$id_conversacion = (int) ($_POST['id_conversacion'] ?? 0);
	$titulo          = mb_substr(trim($_POST['titulo'] ?? ''), 0, 150);
	$fecha           = trim($_POST['fecha'] ?? '');
	$hora            = trim($_POST['hora'] ?? '');

	$volver_chat = $id_conversacion > 0 ? '../chat.php?id=' . $id_conversacion : '../calendario.php';
	$separador   = strpos($volver_chat, '?') === false ? '?' : '&';

	$f_obj = DateTime::createFromFormat('Y-m-d', $fecha);
	$fecha_valida = $f_obj && $f_obj->format('Y-m-d') === $fecha;
	$hora_valida  = $hora === '' || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora);

	if ($titulo === '' || !$fecha_valida || !$hora_valida) {
		header('Location: ' . $volver_chat . $separador . 'cal=error');
		exit;
	}

	// Si viene de un mensaje, tiene que ser de una conversación en la que participo.
	$id_mensaje_db = null;
	if ($id_mensaje > 0) {
		$stmt = $conexion->prepare('SELECT id_conversacion FROM mensaje WHERE id_mensaje = ?');
		$stmt->bind_param('i', $id_mensaje);
		$stmt->execute();
		$m = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if (!$m || !pertenece_a_conversacion($conexion, (int) $m['id_conversacion'], $id_usuario)) {
			header('Location: ../mensajes.php');
			exit;
		}
		$id_mensaje_db = $id_mensaje;
	}

	$hora_db = $hora === '' ? null : $hora . ':00';

	try {
		// Si ese mismo mensaje ya estaba en el calendario, se actualiza en vez de duplicarlo.
		$stmt = $conexion->prepare(
			'INSERT INTO evento_personal (id_usuario, titulo, fecha, hora_inicio, id_mensaje)
			 VALUES (?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), fecha = VALUES(fecha), hora_inicio = VALUES(hora_inicio)'
		);
		$stmt->bind_param('isssi', $id_usuario, $titulo, $fecha, $hora_db, $id_mensaje_db);
		$stmt->execute();
		$stmt->close();
	} catch (mysqli_sql_exception $e) {
		// por ejemplo, si todavía no se corrió sql/migracion_cambios_21oct.sql
		header('Location: ' . $volver_chat . $separador . 'cal=error');
		exit;
	}

	// Se vuelve al chat con el aviso y el mes del evento para el enlace "Ver en el calendario".
	header('Location: ' . $volver_chat . $separador . 'cal=ok&cal_anio=' . $f_obj->format('Y') . '&cal_mes=' . (int) $f_obj->format('n'));
	exit;
}

header('Location: ../calendario.php');
exit;
