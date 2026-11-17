<?php
// procesar_editar_publicacion.php
//
// Guarda los cambios de editar_publicacion.php. Antes de tocar nada se vuelve
// a comprobar que la publicación sea del usuario logueado y que todavía no se
// haya aceptado como trabajo (el formulario se puede mandar a mano).

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_especialidad.php';
require_once __DIR__ . '/../includes/funciones_publicaciones.php';

exigir_abono_empresa($conexion, '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../feed.php');
	exit;
}

$id_trabajo = (int) ($_POST['id_trabajo'] ?? 0);
$pub        = publicacion_propia_editable($conexion, $id_trabajo, $_SESSION['usuario_id']);

if (!$pub) {
	header('Location: ../feed.php?error=no_editable');
	exit;
}

$volver_error = '../editar_publicacion.php?id=' . $id_trabajo . '&error=campos_incompletos';

$titulo_trabajo    = trim($_POST['titulo_trabajo'] ?? '');
$tipo_tecnico      = $_POST['tipo_tecnico'] ?? '';
$tipo_tecnico_otro = trim($_POST['tipo_tecnico_otro'] ?? '');
$direccion         = trim($_POST['direccion'] ?? '');

// Mismo criterio que al publicar: la clave de la lista, o lo escrito en "Otro".
$categoria = categoria_para_guardar($tipo_tecnico === 'otro' ? $tipo_tecnico_otro : $tipo_tecnico);

if ($titulo_trabajo === '' || $tipo_tecnico === '' || $direccion === '' || $categoria === ''
	|| ($tipo_tecnico === 'otro' && $tipo_tecnico_otro === '')) {
	header('Location: ' . $volver_error);
	exit;
}

if ($pub['es_empresa']) {
	/* ---------- EMPRESA: título, tipo de técnico, características, dirección ---------- */
	$caracteristicas = trim($_POST['caracteristicas'] ?? '');

	if ($caracteristicas === '') {
		header('Location: ' . $volver_error);
		exit;
	}

	$stmt = $conexion->prepare("UPDATE trabajo SET titulo = ?, caracteristicas = ?, categoria = ?, ubicacion = ? WHERE id_trabajo = ? AND estado = 'abierto'");
	$stmt->bind_param('ssssi', $titulo_trabajo, $caracteristicas, $categoria, $direccion, $id_trabajo);
} else {
	/* ---------- CLIENTE: además descripción y fecha límite (opcional) ---------- */
	$descripcion = trim($_POST['descripcion'] ?? '');
	$fecha       = trim($_POST['fecha'] ?? '');

	if ($descripcion === '') {
		header('Location: ' . $volver_error);
		exit;
	}

	if ($fecha !== '') {
		$f_obj = DateTime::createFromFormat('Y-m-d', $fecha);
		$valida = $f_obj && $f_obj->format('Y-m-d') === $fecha;

		// Una fecha nueva no puede ser del pasado; si dejó la que ya tenía, se respeta.
		if (!$valida || ($fecha < date('Y-m-d') && $fecha !== $pub['fecha_trabajo'])) {
			header('Location: ' . $volver_error);
			exit;
		}
	}

	$fecha_db = $fecha === '' ? null : $fecha;   // sin fecha límite -> NULL

	$stmt = $conexion->prepare("UPDATE trabajo SET titulo = ?, descripcion = ?, categoria = ?, ubicacion = ?, fecha_trabajo = ? WHERE id_trabajo = ? AND estado = 'abierto'");
	$stmt->bind_param('sssssi', $titulo_trabajo, $descripcion, $categoria, $direccion, $fecha_db, $id_trabajo);
}

try {
	$stmt->execute();
	$stmt->close();
} catch (mysqli_sql_exception $e) {
	header('Location: ' . $volver_error);
	exit;
}

header('Location: ../feed.php?ok=publicacion_editada');
exit;
