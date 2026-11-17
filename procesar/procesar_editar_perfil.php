<?php
// procesar_editar_perfil.php
//
// Recibe el formulario de editar_perfil.php y actualiza los datos
// reales del usuario logueado en la base.
//
//   - Todos:   nombre (o nombre de empresa, si es empresa).
//   - Técnico: además foto de perfil (subir una nueva o quitarla,
//              queda en uploads/perfiles/ y la ruta en
//              usuario.foto_perfil) y descripción (tecnico.descripcion).

session_start();
require_once __DIR__ . '/../conexion.php';

// Que los errores de la base lleguen como excepción (así el try/catch de abajo
// funciona en cualquier versión de PHP y se puede deshacer todo si algo falla).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../editar_perfil.php');
	exit;
}

$id_usuario  = (int) $_SESSION['usuario_id'];
$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$es_empresa  = $tipo_cuenta === 'empresa';

function volver_con_error($codigo) {
	header('Location: ../editar_perfil.php?error=' . $codigo);
	exit;
}

$nombre_empresa = trim($_POST['nombre_empresa'] ?? '');

// En una cuenta de empresa no hay un "nombre de persona" separado: el
// nombre de la cuenta (usuario.nombre) es directamente el nombre de la
// empresa. En una cuenta normal, se usa el campo "nombre" del form.
$nombre = $es_empresa ? $nombre_empresa : trim($_POST['nombre'] ?? '');
$nombre = mb_substr($nombre, 0, 100);

if ($nombre === '') {
	volver_con_error('nombre');
}

// ¿Tiene perfil de técnico? (foto y descripción son solo para ellos)
$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$es_tecnico = $stmt->get_result()->fetch_assoc() !== null;
$stmt->close();

/* =========================================================
   FOTO DE PERFIL (solo técnicos)
   Se valida y se guarda ANTES de tocar la base, para no dejar
   el perfil a medio actualizar si la foto falla.
   ========================================================= */
$carpeta       = __DIR__ . '/../uploads/perfiles/';
$prefijo_ruta  = 'uploads/perfiles/';          // lo que se guarda en usuario.foto_perfil
$tamano_maximo = 5 * 1024 * 1024;              // 5 MB
$tipos_permitidos = [
	'image/jpeg' => 'jpg',
	'image/png'  => 'png',
	'image/webp' => 'webp',
];

$cambiar_foto  = false;   // ¿hay que tocar usuario.foto_perfil?
$ruta_nueva    = null;    // null = quitar la foto
$archivo_nuevo = null;    // ruta en disco de la foto recién guardada (para limpiar si algo falla)

if ($es_tecnico) {

	$foto = $_FILES['foto_perfil'] ?? null;
	$hay_foto_nueva = $foto && $foto['error'] !== UPLOAD_ERR_NO_FILE;

	if ($hay_foto_nueva) {
		// upload_max_filesize de php.ini (2 MB por defecto en XAMPP) también cae acá.
		if (in_array($foto['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
			volver_con_error('grande');
		}
		if ($foto['error'] !== UPLOAD_ERR_OK) {
			volver_con_error('foto');
		}
		if ($foto['size'] > $tamano_maximo) {
			volver_con_error('grande');
		}

		// El tipo se comprueba mirando el contenido del archivo, no su extensión.
		$info = @getimagesize($foto['tmp_name']);
		if ($info === false || !isset($tipos_permitidos[$info['mime']])) {
			volver_con_error('formato');
		}

		if (!is_dir($carpeta)) {
			mkdir($carpeta, 0755, true);
		}

		$nombre_archivo = 'perfil_' . $id_usuario . '_' . uniqid('', true) . '.' . $tipos_permitidos[$info['mime']];
		$archivo_nuevo  = $carpeta . $nombre_archivo;

		if (!move_uploaded_file($foto['tmp_name'], $archivo_nuevo)) {
			volver_con_error('foto');
		}

		$cambiar_foto = true;
		$ruta_nueva   = $prefijo_ruta . $nombre_archivo;

	} elseif (($_POST['quitar_foto'] ?? '0') === '1') {
		$cambiar_foto = true;       // $ruta_nueva sigue en null → se borra la foto
	}
}

/* =========================================================
   GUARDAR
   ========================================================= */

// Foto anterior: se necesita para borrar el archivo viejo después.
$foto_anterior = null;
if ($cambiar_foto) {
	$stmt = $conexion->prepare('SELECT foto_perfil FROM usuario WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$foto_anterior = $fila['foto_perfil'] ?? null;
}

try {
	$conexion->begin_transaction();

	// UPDATE de los datos base (tabla usuario). La fecha de nacimiento no
	// se edita desde este formulario, así que queda como estaba.
	$stmt = $conexion->prepare('UPDATE usuario SET nombre = ? WHERE id_usuario = ?');
	$stmt->bind_param('si', $nombre, $id_usuario);
	$stmt->execute();
	$stmt->close();

	if ($cambiar_foto) {
		$stmt = $conexion->prepare('UPDATE usuario SET foto_perfil = ? WHERE id_usuario = ?');
		$stmt->bind_param('si', $ruta_nueva, $id_usuario);
		$stmt->execute();
		$stmt->close();
	}

	// Si es cuenta de empresa, actualiza también el nombre de la empresa
	if ($es_empresa) {
		$stmt = $conexion->prepare('UPDATE empleador SET empresa = ? WHERE id_usuario = ?');
		$stmt->bind_param('si', $nombre_empresa, $id_usuario);
		$stmt->execute();
		$stmt->close();
	}

	// Descripción del técnico (vacía = se guarda NULL)
	if ($es_tecnico) {
		$descripcion = trim($_POST['descripcion'] ?? '');
		$descripcion = $descripcion === '' ? null : mb_substr($descripcion, 0, 300);

		$stmt = $conexion->prepare('UPDATE tecnico SET descripcion = ? WHERE id_usuario = ?');
		$stmt->bind_param('si', $descripcion, $id_usuario);
		$stmt->execute();
		$stmt->close();
	}

	$conexion->commit();
} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	if ($archivo_nuevo) {
		@unlink($archivo_nuevo);    // no dejar una foto huérfana
	}
	volver_con_error($archivo_nuevo ? 'foto' : 'nombre');
}

// Ya quedó guardado: se borra la foto vieja del disco.
// Solo se tocan archivos de uploads/perfiles/ (basename evita salirse de la carpeta).
if ($cambiar_foto && $foto_anterior && strpos($foto_anterior, $prefijo_ruta) === 0) {
	$ruta_disco = $carpeta . basename($foto_anterior);
	if (is_file($ruta_disco)) {
		@unlink($ruta_disco);
	}
}

// Refleja los cambios en la sesión para que se vean al toque en el resto del sitio
$_SESSION['usuario_nombre'] = $nombre;
if ($es_empresa) {
	$_SESSION['nombre_empresa'] = $nombre_empresa;
}

header('Location: ../ajustes.php?guardado=1#cuenta');
exit;
