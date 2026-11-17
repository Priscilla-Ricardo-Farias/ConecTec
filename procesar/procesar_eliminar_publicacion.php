<?php
// procesar_eliminar_publicacion.php
//
// Elimina una publicación propia que todavía no se aceptó como trabajo.
// Se borran también sus postulaciones y archivos (las relaciones de la base
// son ON DELETE CASCADE) y se avisa a los técnicos que ya se habían postulado.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_notificaciones.php';
require_once __DIR__ . '/../includes/funciones_publicaciones.php';

exigir_abono_empresa($conexion, '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../feed.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];
$id_trabajo = (int) ($_POST['id_trabajo'] ?? 0);
$pub        = publicacion_propia_editable($conexion, $id_trabajo, $id_usuario);

if (!$pub) {
	header('Location: ../feed.php?error=no_editable');
	exit;
}

// Antes de borrar: los archivos a limpiar y los técnicos a avisar.
$archivos = [];
if (!empty($pub['archivo'])) {
	$archivos[] = $pub['archivo'];
}

$stmt = $conexion->prepare('SELECT archivo FROM trabajo_archivo WHERE id_trabajo = ?');
$stmt->bind_param('i', $id_trabajo);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
	$archivos[] = $fila['archivo'];
}
$stmt->close();

$stmt = $conexion->prepare(
	"SELECT t.id_usuario
	 FROM postulacion p
	 JOIN tecnico t ON t.id_tecnico = p.id_tecnico
	 WHERE p.id_trabajo = ? AND p.estado = 'pendiente'"
);
$stmt->bind_param('i', $id_trabajo);
$stmt->execute();
$postulantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

try {
	// El "AND estado = 'abierto'" evita borrar si justo en este momento se aceptó a alguien.
	$stmt = $conexion->prepare("DELETE FROM trabajo WHERE id_trabajo = ? AND estado = 'abierto'");
	$stmt->bind_param('i', $id_trabajo);
	$stmt->execute();
	$borradas = $stmt->affected_rows;
	$stmt->close();
} catch (mysqli_sql_exception $e) {
	header('Location: ../feed.php?error=no_editable');
	exit;
}

if ($borradas < 1) {
	header('Location: ../feed.php?error=no_editable');
	exit;
}

// Archivos del disco (basename evita salirse de uploads/publicaciones/)
$carpeta = __DIR__ . '/../uploads/publicaciones/';
foreach (array_unique($archivos) as $nombre) {
	$ruta = $carpeta . basename($nombre);
	if (is_file($ruta)) {
		@unlink($ruta);
	}
}

// Aviso a quienes se habían postulado
foreach ($postulantes as $p) {
	crear_notificacion($conexion, (int) $p['id_usuario'], $id_usuario, 'cambio', 'Se eliminó una publicación a la que te postulaste: ' . $pub['titulo']);
}

header('Location: ../feed.php?ok=publicacion_eliminada');
exit;
