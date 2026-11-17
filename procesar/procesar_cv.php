<?php
// procesar_cv.php
//
// Actualiza o elimina el CV del técnico logueado. Lo llaman los botones
// "Actualizar CV" y "Eliminar" del perfil propio (perfil.php). La subida
// desde Ajustes > Privacidad sigue funcionando igual por procesar_ajustes.php.
//
// Campo "accion":
//   - actualizar: guarda el archivo nuevo (cv_archivo) y borra el anterior
//   - eliminar:   deja cv_archivo en NULL y borra el archivo del disco

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: perfil.php');
	exit;
}

require_once __DIR__ . '/conexion.php';

$id_usuario_propio = (int) $_SESSION['usuario_id'];
$accion            = $_POST['accion'] ?? '';
$carpeta_cv        = __DIR__ . '/uploads/cv/';

// Solo las cuentas que tienen fila en "tecnico" tienen CV
$stmt = $conexion->prepare('SELECT cv_archivo FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $id_usuario_propio);
$stmt->execute();
$tecnico = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tecnico) {
	header('Location: perfil.php');
	exit;
}

$cv_anterior = $tecnico['cv_archivo'];

// Borra un CV de la carpeta uploads/cv (basename evita salirse de la carpeta)
function borrar_archivo_cv($carpeta, $nombre) {
	if (!$nombre) {
		return;
	}
	$ruta = $carpeta . basename($nombre);
	if (is_file($ruta)) {
		unlink($ruta);
	}
}

if ($accion === 'actualizar') {

	if (!isset($_FILES['cv_archivo']) || $_FILES['cv_archivo']['error'] !== UPLOAD_ERR_OK) {
		header('Location: perfil.php?cv=error');
		exit;
	}

	$extension = strtolower(pathinfo($_FILES['cv_archivo']['name'], PATHINFO_EXTENSION));
	if (!in_array($extension, ['pdf', 'doc', 'docx'], true)) {
		header('Location: perfil.php?cv=formato');
		exit;
	}

	if (!is_dir($carpeta_cv)) {
		mkdir($carpeta_cv, 0755, true);
	}

	$nombre_nuevo = 'cv_' . uniqid('', true) . '.' . $extension;

	if (!move_uploaded_file($_FILES['cv_archivo']['tmp_name'], $carpeta_cv . $nombre_nuevo)) {
		header('Location: perfil.php?cv=error');
		exit;
	}

	$stmt = $conexion->prepare('UPDATE tecnico SET cv_archivo = ? WHERE id_usuario = ?');
	$stmt->bind_param('si', $nombre_nuevo, $id_usuario_propio);
	$guardado = $stmt->execute();
	$stmt->close();

	if (!$guardado) {
		borrar_archivo_cv($carpeta_cv, $nombre_nuevo);
		header('Location: perfil.php?cv=error');
		exit;
	}

	borrar_archivo_cv($carpeta_cv, $cv_anterior);
	header('Location: perfil.php?cv=actualizado');
	exit;
}

if ($accion === 'eliminar') {

	$stmt = $conexion->prepare('UPDATE tecnico SET cv_archivo = NULL WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario_propio);
	$guardado = $stmt->execute();
	$stmt->close();

	if (!$guardado) {
		header('Location: perfil.php?cv=error');
		exit;
	}

	borrar_archivo_cv($carpeta_cv, $cv_anterior);
	header('Location: perfil.php?cv=eliminado');
	exit;
}

header('Location: perfil.php');
exit;