<?php
// procesar_cambiar_a_tecnico.php
//
// Recibe el certificado y los datos de cambiar_a_tecnico.php. Si está todo
// completo, guarda el certificado, crea la fila de la cuenta en "tecnico"
// (con su primera especialidad) y cambia el tipo de cuenta a "tecnico".
//
// Antes esto solo cambiaba el tipo en la sesión: al cerrar sesión y volver a
// entrar, la cuenta seguía siendo de cliente, porque el login mira si existe
// la fila en "tecnico". Ahora se guarda de verdad.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

if (($_SESSION['usuario_tipo'] ?? 'cliente') !== 'cliente') {
	header('Location: ../ajustes.php#cuenta');
	exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_especialidad.php';

$id_usuario         = (int) $_SESSION['usuario_id'];
$tipo_tecnico       = $_POST['tipo_tecnico'] ?? '';
$tipo_tecnico_otro  = trim($_POST['tipo_tecnico_otro'] ?? '');
$fecha_graduacion   = (int) ($_POST['fecha_graduacion'] ?? 0);

$campos_incompletos = $tipo_tecnico === '' || $fecha_graduacion <= 0
	|| ($tipo_tecnico === 'otro' && $tipo_tecnico_otro === '');

$archivo_subido = isset($_FILES['certificado_archivo']) && $_FILES['certificado_archivo']['error'] === UPLOAD_ERR_OK;

if ($campos_incompletos || !$archivo_subido) {
	header('Location: ../cambiar_a_tecnico.php?error=1');
	exit;
}

// Si ya tiene fila en "tecnico" (por ejemplo, mandó el formulario dos veces), no se duplica.
$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$ya_es_tecnico = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($ya_es_tecnico) {
	$_SESSION['usuario_tipo'] = 'tecnico';
	header('Location: ../ajustes.php?guardado=1#cuenta');
	exit;
}

/* =========================================================
   GUARDAR EL CERTIFICADO (mismo criterio que
   procesar_registro_tecnico.php)
   ========================================================= */

$carpeta_destino = __DIR__ . '/../uploads/certificados/';
if (!is_dir($carpeta_destino)) {
	mkdir($carpeta_destino, 0755, true);
}

$extension      = strtolower(pathinfo($_FILES['certificado_archivo']['name'], PATHINFO_EXTENSION));
$nombre_archivo = uniqid('cert_', true) . '.' . $extension;
$ruta_destino   = $carpeta_destino . $nombre_archivo;

if (!move_uploaded_file($_FILES['certificado_archivo']['tmp_name'], $ruta_destino)) {
	header('Location: ../cambiar_a_tecnico.php?error=1');
	exit;
}

$especialidad_elegida = $tipo_tecnico === 'otro' ? $tipo_tecnico_otro : $tipo_tecnico;
$especialidad         = mb_substr(etiqueta_especialidad($especialidad_elegida), 0, 100);

$conexion->begin_transaction();

try {
	$stmt = $conexion->prepare('INSERT INTO tecnico (id_usuario, especialidad, certificado_archivo, fecha_graduacion) VALUES (?, ?, ?, ?)');
	$stmt->bind_param('issi', $id_usuario, $especialidad, $nombre_archivo, $fecha_graduacion);
	$stmt->execute();
	$id_tecnico = $conexion->insert_id;
	$stmt->close();

	// Su primera especialidad (después puede sumar más desde Ajustes).
	try {
		guardar_especialidades_tecnico($conexion, $id_tecnico, [$especialidad_elegida], false);
	} catch (Throwable $e) {
		// si todavía no se corrió la migración, la principal igual queda en tecnico.especialidad
	}

	$conexion->commit();
} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	@unlink($ruta_destino);
	header('Location: ../cambiar_a_tecnico.php?error=1');
	exit;
}

$_SESSION['usuario_tipo'] = 'tecnico';

header('Location: ../ajustes.php?guardado=1#cuenta');
exit;
