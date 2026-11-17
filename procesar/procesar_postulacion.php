<?php
// procesar_postulacion.php
//
// Recibe el botón "Postularme" de feed.php. Solo puede postularse una
// cuenta de tipo técnico, a un trabajo que siga "abierto", y no puede
// postularse dos veces al mismo trabajo (lo protege también el UNIQUE
// KEY de la tabla postulacion, por si dos pestañas mandan el POST a
// la vez).

session_start();
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_especialidad.php';
require_once __DIR__ . '/../includes/funciones_notificaciones.php';
exigir_empresa_habilitada($conexion, '../');

if (($_SESSION['usuario_tipo'] ?? '') !== 'tecnico') {
	header('Location: ../feed.php');
	exit;
}

$id_trabajo = (int) ($_POST['id_trabajo'] ?? 0);

if ($id_trabajo <= 0) {
	header('Location: ../feed.php');
	exit;
}

// Busca el id_tecnico del usuario logueado (sus especialidades se consultan más abajo)
$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila) {
	header('Location: ../feed.php');
	exit;
}
$id_tecnico = $fila['id_tecnico'];

// Verifica que el trabajo exista y siga abierto
$stmt = $conexion->prepare('SELECT estado, categoria FROM trabajo WHERE id_trabajo = ?');
$stmt->bind_param('i', $id_trabajo);
$stmt->execute();
$trabajo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trabajo || $trabajo['estado'] !== 'abierto') {
	header('Location: ../feed.php?error=trabajo_no_disponible');
	exit;
}

// Un técnico solo puede postularse a trabajos de alguna de SUS especialidades
// (puede tener varias). Se valida acá (y no solo en feed.php) porque el botón
// se puede forzar mandando el POST a mano.
if (!tecnico_tiene_especialidad($conexion, $id_tecnico, $trabajo['categoria'])) {
	header('Location: ../feed.php?filtro=todas&error=otra_especialidad');
	exit;
}

// Inserta la postulación (el UNIQUE KEY de la tabla evita que se
// postule dos veces al mismo trabajo; si ya se había postulado antes,
// mysqli tira una excepción acá y simplemente lo mandamos de vuelta).
$postulado_ahora = false;

try {
	$stmt = $conexion->prepare('INSERT INTO postulacion (id_tecnico, id_trabajo) VALUES (?, ?)');
	$stmt->bind_param('ii', $id_tecnico, $id_trabajo);
	// Según la versión de PHP, un duplicado tira excepción (8.1+) o
	// devuelve false (8.0): las dos cosas quedan cubiertas.
	$postulado_ahora = $stmt->execute() && $stmt->affected_rows === 1;
	$stmt->close();
} catch (mysqli_sql_exception $e) {
	// Ya estaba postulado (u otro error de integridad); no rompemos la página.
}

// Solo si la postulación es nueva: avisa al dueño del trabajo (recibida)
// y le confirma al técnico (enviada). No se repite si ya estaba postulado.
if ($postulado_ahora) {
	notificar_postulacion($conexion, $id_trabajo, (int) $_SESSION['usuario_id']);
}

header('Location: ../feed.php?postulado=1');
exit;