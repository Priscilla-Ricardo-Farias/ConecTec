<?php
// procesar_portfolio.php
//
// Publicaciones de trabajos del perfil del técnico (perfil.php), al
// estilo Instagram: una o varias fotos (ruleta) + descripción opcional. Se guardan en
// "proyecto" (descripción, fecha) y "foto_proyecto" (la imagen), y se
// muestran solo en el perfil.
//
// Campo "accion":
//   - subir:    guarda las fotos en uploads/portfolio/ y crea la publicación
//   - eliminar: borra la publicación (y su archivo) si es del técnico logueado

session_start();

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
exigir_empresa_habilitada($conexion, '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../perfil.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];
$accion     = $_POST['accion'] ?? '';

// Solo las cuentas con fila en "tecnico" tienen portfolio.
$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$tecnico = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tecnico) {
	header('Location: ../perfil.php');
	exit;
}

$id_tecnico      = (int) $tecnico['id_tecnico'];
$carpeta         = __DIR__ . '/../uploads/portfolio/';
$prefijo_ruta    = 'uploads/portfolio/';          // lo que se guarda en foto_proyecto.imagen
$tamano_maximo   = 5 * 1024 * 1024;               // 5 MB
$tipos_permitidos = [
	'image/jpeg' => 'jpg',
	'image/png'  => 'png',
	'image/webp' => 'webp',
];

function volver_perfil($resultado) {
	header('Location: ../perfil.php?portfolio=' . $resultado);
	exit;
}

/* =========================================================
   SUBIR (una o varias fotos por publicación)
   Cada publicación puede tener hasta 10 fotos; en el perfil se ven
   como una ruleta (carrusel). El campo se llama foto[] (también se
   acepta "foto" suelta, como antes).
   ========================================================= */
if ($accion === 'subir') {

	$maximo_fotos = 10;

	// Si las fotos suman más que post_max_size de php.ini, PHP descarta todo el formulario.
	if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
		volver_perfil('grande');
	}

	// Se normaliza a una lista de archivos, venga uno o varios.
	$subidas = [];
	$f = $_FILES['foto'] ?? null;

	if ($f) {
		if (is_array($f['name'])) {
			foreach ($f['name'] as $i => $nombre) {
				if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
					continue;
				}
				$subidas[] = ['tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
			}
		} elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
			$subidas[] = ['tmp_name' => $f['tmp_name'], 'error' => $f['error'], 'size' => $f['size']];
		}
	}

	if (empty($subidas)) {
		volver_perfil('error');
	}
	if (count($subidas) > $maximo_fotos) {
		volver_perfil('limite');
	}

	// Primero se valida TODO: si alguna foto no sirve, no se guarda ninguna.
	$a_guardar = [];
	foreach ($subidas as $foto) {
		// upload_max_filesize de php.ini (2 MB por defecto en XAMPP) también cae acá.
		if (in_array($foto['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $foto['size'] > $tamano_maximo) {
			volver_perfil('grande');
		}
		if ($foto['error'] !== UPLOAD_ERR_OK) {
			volver_perfil('error');
		}

		// El tipo se comprueba mirando el contenido del archivo, no su extensión.
		$info = @getimagesize($foto['tmp_name']);
		if ($info === false || !isset($tipos_permitidos[$info['mime']])) {
			volver_perfil('formato');
		}

		$a_guardar[] = ['tmp_name' => $foto['tmp_name'], 'ext' => $tipos_permitidos[$info['mime']]];
	}

	$descripcion = trim($_POST['descripcion'] ?? '');
	$descripcion = $descripcion === '' ? null : mb_substr($descripcion, 0, 500);

	if (!is_dir($carpeta)) {
		mkdir($carpeta, 0755, true);
	}

	$guardados = [];

	try {
		$conexion->begin_transaction();

		$stmt = $conexion->prepare('INSERT INTO proyecto (id_tecnico, descripcion, fecha_realizacion) VALUES (?, ?, CURDATE())');
		$stmt->bind_param('is', $id_tecnico, $descripcion);
		$stmt->execute();
		$id_proyecto = $conexion->insert_id;
		$stmt->close();

		$stmt = $conexion->prepare('INSERT INTO foto_proyecto (id_proyecto, imagen) VALUES (?, ?)');

		foreach ($a_guardar as $g) {
			$nombre_archivo = 'trab_' . uniqid('', true) . '.' . $g['ext'];

			if (!move_uploaded_file($g['tmp_name'], $carpeta . $nombre_archivo)) {
				throw new RuntimeException('no se pudo guardar la foto');
			}
			$guardados[] = $nombre_archivo;

			$ruta = $prefijo_ruta . $nombre_archivo;
			$stmt->bind_param('is', $id_proyecto, $ruta);
			$stmt->execute();
		}
		$stmt->close();

		$conexion->commit();
	} catch (Throwable $e) {
		$conexion->rollback();
		foreach ($guardados as $nombre_archivo) {
			@unlink($carpeta . $nombre_archivo);
		}
		volver_perfil('error');
	}

	volver_perfil('subido');
}

/* =========================================================
   ELIMINAR
   ========================================================= */
if ($accion === 'eliminar') {

	$id_proyecto = (int) ($_POST['id_proyecto'] ?? 0);
	if ($id_proyecto <= 0) {
		volver_perfil('error');
	}

	// Se busca con el id_tecnico del logueado: nadie borra publicaciones ajenas.
	$stmt = $conexion->prepare(
		'SELECT fp.imagen
		 FROM proyecto p
		 JOIN foto_proyecto fp ON fp.id_proyecto = p.id_proyecto
		 WHERE p.id_proyecto = ? AND p.id_tecnico = ?'
	);
	$stmt->bind_param('ii', $id_proyecto, $id_tecnico);
	$stmt->execute();
	$fotos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	if (empty($fotos)) {
		volver_perfil('error');
	}

	// foto_proyecto se borra sola (ON DELETE CASCADE).
	$stmt = $conexion->prepare('DELETE FROM proyecto WHERE id_proyecto = ? AND id_tecnico = ?');
	$stmt->bind_param('ii', $id_proyecto, $id_tecnico);
	$stmt->execute();
	$stmt->close();

	foreach ($fotos as $f) {
		// Solo se borran archivos de uploads/portfolio/ (basename evita salirse de la carpeta).
		if (strpos($f['imagen'], $prefijo_ruta) === 0) {
			$ruta_disco = $carpeta . basename($f['imagen']);
			if (is_file($ruta_disco)) {
				unlink($ruta_disco);
			}
		}
	}

	volver_perfil('eliminado');
}

volver_perfil('error');
