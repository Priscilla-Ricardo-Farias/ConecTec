<?php
// procesar/procesar_calificacion.php
//
// Recibe el formulario del popup "Por favor, califique al técnico" (que se
// muestra en feed.php, ver includes/calificaciones_pendientes.php).
//
// Solo puede calificar el DUEÑO del trabajo (el cliente o la empresa que lo
// publicó), y solo cuando:
//   - el trabajo está "en_progreso" (o sea, ya tiene un técnico aceptado),
//   - el día del trabajo ya pasó (fecha_trabajo < hoy),
//   - todavía no fue calificado (lo protege también el UNIQUE de
//     calificacion.id_trabajo, por si se manda el form dos veces).
//
// Al guardar: inserta la calificación, la asocia a quien calificó
// (calificacion_cliente o calificacion_empleador), recalcula el promedio
// del técnico (el que se ve en su perfil como estrellas) y cierra el trabajo.

session_start();
require __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../feed.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];
$id_trabajo = (int) ($_POST['id_trabajo'] ?? 0);
$puntaje    = (int) ($_POST['puntaje'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');

if ($id_trabajo <= 0 || $puntaje < 1 || $puntaje > 5) {
	header('Location: ../feed.php?error=calificacion_invalida');
	exit;
}

// Comentario opcional, máximo 500 caracteres
if (mb_strlen($comentario) > 500) {
	$comentario = mb_substr($comentario, 0, 500);
}
$comentario = ($comentario === '') ? null : $comentario;

/* =========================================================
   TRAER EL TRABAJO + EL TÉCNICO ACEPTADO + QUIÉN LO PUBLICÓ
   ========================================================= */
$stmt = $conexion->prepare(
	"SELECT tr.estado AS estado_trabajo,
	        (tr.fecha_trabajo IS NOT NULL AND tr.fecha_trabajo < CURDATE()) AS ya_paso,
	        p.id_tecnico,
	        c.id_cliente,   c.id_usuario   AS id_usuario_cliente,
	        emp.id_empleador, emp.id_usuario AS id_usuario_empleador,
	        (SELECT COUNT(*) FROM calificacion ca WHERE ca.id_trabajo = tr.id_trabajo) AS ya_calificado
	 FROM trabajo tr
	 JOIN postulacion p ON p.id_trabajo = tr.id_trabajo AND p.estado = 'aceptada'

	 LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
	 LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente

	 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
	 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador

	 WHERE tr.id_trabajo = ?"
);
$stmt->bind_param('i', $id_trabajo);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila) {
	header('Location: ../feed.php');
	exit;
}

// Solo el dueño de la publicación puede calificar
$es_dueno =
	$id_usuario === (int) ($fila['id_usuario_cliente'] ?? 0) ||
	$id_usuario === (int) ($fila['id_usuario_empleador'] ?? 0);

if (!$es_dueno) {
	header('Location: ../feed.php');
	exit;
}

// Tiene que estar en progreso, con el día ya pasado y sin calificar
if ($fila['estado_trabajo'] !== 'en_progreso' || !(int) $fila['ya_paso'] || (int) $fila['ya_calificado'] > 0) {
	header('Location: ../feed.php');
	exit;
}

$id_tecnico = (int) $fila['id_tecnico'];

/* =========================================================
   GUARDAR
   ========================================================= */
$conexion->begin_transaction();

try {
	$stmt = $conexion->prepare(
		'INSERT INTO calificacion (id_tecnico, id_trabajo, puntaje, comentario) VALUES (?, ?, ?, ?)'
	);
	$stmt->bind_param('iiis', $id_tecnico, $id_trabajo, $puntaje, $comentario);
	$stmt->execute();
	$id_calificacion = $conexion->insert_id;
	$stmt->close();

	// Quién calificó: un cliente o un empleador
	if (!empty($fila['id_cliente'])) {
		$stmt = $conexion->prepare('INSERT INTO calificacion_cliente (id_cliente, id_calificacion) VALUES (?, ?)');
		$stmt->bind_param('ii', $fila['id_cliente'], $id_calificacion);
	} else {
		$stmt = $conexion->prepare('INSERT INTO calificacion_empleador (id_empleador, id_calificacion) VALUES (?, ?)');
		$stmt->bind_param('ii', $fila['id_empleador'], $id_calificacion);
	}
	$stmt->execute();
	$stmt->close();

	// Recalcula el promedio del técnico (de 1 a 5). En el perfil se
	// muestra como estrellas con el número (ej: 4,5).
	$stmt = $conexion->prepare(
		'UPDATE tecnico
		 SET promedio_calificacion = (SELECT AVG(puntaje) FROM calificacion WHERE id_tecnico = ?)
		 WHERE id_tecnico = ?'
	);
	$stmt->bind_param('ii', $id_tecnico, $id_tecnico);
	$stmt->execute();
	$stmt->close();

	// El trabajo ya se hizo y se calificó: se cierra
	$stmt = $conexion->prepare("UPDATE trabajo SET estado = 'cerrado' WHERE id_trabajo = ?");
	$stmt->bind_param('i', $id_trabajo);
	$stmt->execute();
	$stmt->close();

	$conexion->commit();

} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	error_log('procesar_calificacion: ' . $e->getMessage());
	header('Location: ../feed.php?error=error_servidor');
	exit;
}

header('Location: ../feed.php?calificado=1');
exit;
