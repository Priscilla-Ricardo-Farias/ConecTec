<?php
// procesar_registro_tecnico.php
//
// Recibe los datos del formulario de registro_tecnico.php (incluye
// archivo), guarda el certificado y crea una cuenta de tipo
// "tecnico" en la base de datos real.

session_start();
require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_especialidad.php';

$nombre             = trim($_POST['nombre'] ?? '');
$correo             = trim($_POST['correo'] ?? '');
$clave              = $_POST['clave'] ?? '';
$fecha_nacimiento   = $_POST['fecha_nacimiento'] ?? '';
$tipo_tecnico       = $_POST['tipo_tecnico'] ?? '';
$tipo_tecnico_otro  = trim($_POST['tipo_tecnico_otro'] ?? '');
$fecha_graduacion   = $_POST['fecha_graduacion'] ?? '';

$campos_incompletos = $nombre === '' || $correo === '' || $clave === '' || $fecha_nacimiento === ''
	|| $tipo_tecnico === '' || $fecha_graduacion === ''
	|| ($tipo_tecnico === 'otro' && $tipo_tecnico_otro === '');

$archivo_subido = isset($_FILES['certificado_archivo']) && $_FILES['certificado_archivo']['error'] === UPLOAD_ERR_OK;

if ($campos_incompletos || !$archivo_subido) {
	header('Location: ../registro_tecnico.php?error=campos_incompletos');
	exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
	header('Location: ../registro_tecnico.php?error=correo_invalido');
	exit;
}

// Chequear que el correo no esté usado ya
$stmt = $conexion->prepare('SELECT id_usuario FROM usuario WHERE email = ?');
$stmt->bind_param('s', $correo);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
	$stmt->close();
	header('Location: ../registro_tecnico.php?error=correo_registrado');
	exit;
}
$stmt->close();

/* =========================================================
   GUARDAR EL CERTIFICADO
   ========================================================= */
$carpeta_destino = __DIR__ . '/../uploads/certificados/';
if (!is_dir($carpeta_destino)) {
	mkdir($carpeta_destino, 0755, true);
}

$extension      = pathinfo($_FILES['certificado_archivo']['name'], PATHINFO_EXTENSION);
$nombre_archivo = uniqid('cert_', true) . '.' . $extension;
$ruta_destino   = $carpeta_destino . $nombre_archivo;

move_uploaded_file($_FILES['certificado_archivo']['tmp_name'], $ruta_destino);

// Lo que eligió de la lista, o lo que escribió en "Otro". Si es una variación de una
// especialidad de la lista ("informático/a", "Técnico en Informática"), se guarda como esa.
$especialidad_elegida = $tipo_tecnico === 'otro' ? $tipo_tecnico_otro : $tipo_tecnico;
$especialidad         = mb_substr(etiqueta_especialidad($especialidad_elegida), 0, 100);
$clave_hash   = password_hash($clave, PASSWORD_DEFAULT);

$conexion->begin_transaction();

try {
	// 1) Insertar en usuario
	$stmt = $conexion->prepare('INSERT INTO usuario (nombre, email, contrasena, fecha_nacimiento) VALUES (?, ?, ?, ?)');
	$stmt->bind_param('ssss', $nombre, $correo, $clave_hash, $fecha_nacimiento);
	$stmt->execute();
	$id_usuario = $conexion->insert_id;
	$stmt->close();

	// 2) Insertar en tecnico
	$stmt = $conexion->prepare('INSERT INTO tecnico (id_usuario, especialidad, certificado_archivo, fecha_graduacion) VALUES (?, ?, ?, ?)');
	$stmt->bind_param('issi', $id_usuario, $especialidad, $nombre_archivo, $fecha_graduacion);
	$stmt->execute();
	$id_tecnico = $conexion->insert_id;
	$stmt->close();

	// 3) Su primera especialidad (después puede sumar más desde Ajustes). Si todavía no
	//    se corrió la migración, la principal igual queda en tecnico.especialidad.
	try {
		guardar_especialidades_tecnico($conexion, $id_tecnico, [$especialidad_elegida], false);
	} catch (Throwable $e) {
		// se ignora a propósito
	}

	$conexion->commit();

} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	header('Location: ../registro_tecnico.php?error=error_servidor');
	exit;
}

$_SESSION['usuario_id']     = $id_usuario;
$_SESSION['usuario_nombre'] = $nombre;
$_SESSION['usuario_correo'] = $correo;
$_SESSION['usuario_tipo']   = 'tecnico';

header('Location: ../feed.php');
exit;