<?php
// procesar_publicacion.php
//
// Recibe el formulario de publicar.php y crea la fila en "trabajo",
// más la relación con quien publicó (trabajo_cliente o
// trabajo_empleador, según el tipo de cuenta logueada).

session_start();
require __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_notificaciones.php';
require_once __DIR__ . '/../includes/funciones_especialidad.php';

// Las empresas sin abono activo no pueden publicar.
exigir_abono_empresa($conexion, '../');

$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$es_empresa  = $tipo_cuenta === 'empresa';

$titulo_trabajo     = trim($_POST['titulo_trabajo'] ?? '');
$tipo_tecnico       = $_POST['tipo_tecnico'] ?? '';
$tipo_tecnico_otro  = trim($_POST['tipo_tecnico_otro'] ?? '');
$direccion          = trim($_POST['direccion'] ?? '');

// "categoria" en la tabla trabajo es el tipo de técnico elegido (o el
// "otro" especificado a mano). Si lo que escribieron en "Otro" es una
// variación de una especialidad de la lista ("informático/a", "Técnico en
// Informática"...), se guarda la clave de la lista, así los técnicos de esa
// especialidad lo ven igual.
$categoria = categoria_para_guardar($tipo_tecnico === 'otro' ? $tipo_tecnico_otro : $tipo_tecnico);

if ($es_empresa) {

	/* =========================================================
	   PUBLICACIÓN DE EMPRESA
	   ========================================================= */

	$caracteristicas = trim($_POST['caracteristicas'] ?? '');

	$campos_incompletos = $titulo_trabajo === '' || $tipo_tecnico === '' || $caracteristicas === '' || $direccion === ''
		|| ($tipo_tecnico === 'otro' && $tipo_tecnico_otro === '');

	if ($campos_incompletos) {
		header('Location: ../publicar.php?error=campos_incompletos');
		exit;
	}

	// Buscar el id_empleador correspondiente al usuario logueado
	$stmt = $conexion->prepare('SELECT id_empleador FROM empleador WHERE id_usuario = ?');
	$stmt->bind_param('i', $_SESSION['usuario_id']);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$fila) {
		// No debería pasar (la sesión dice "empresa" pero no hay fila
		// en empleador), pero por las dudas no dejamos que rompa.
		header('Location: ../publicar.php?error=cuenta_invalida');
		exit;
	}
	$id_empleador = $fila['id_empleador'];

	$conexion->begin_transaction();

	try {
		$stmt = $conexion->prepare('INSERT INTO trabajo (titulo, caracteristicas, categoria, ubicacion) VALUES (?, ?, ?, ?)');
		$stmt->bind_param('ssss', $titulo_trabajo, $caracteristicas, $categoria, $direccion);
		$stmt->execute();
		$id_trabajo = $conexion->insert_id;
		$stmt->close();

		$stmt = $conexion->prepare('INSERT INTO trabajo_empleador (id_empleador, id_trabajo) VALUES (?, ?)');
		$stmt->bind_param('ii', $id_empleador, $id_trabajo);
		$stmt->execute();
		$stmt->close();

		$conexion->commit();

	} catch (mysqli_sql_exception $e) {
		$conexion->rollback();
		header('Location: ../publicar.php?error=error_servidor');
		exit;
	}

} else {

	/* =========================================================
	   PUBLICACIÓN DE CLIENTE / USUARIO
	   ========================================================= */

	$descripcion = trim($_POST['descripcion'] ?? '');
	$fecha       = $_POST['fecha'] ?? '';

	// La fecha límite es opcional: si no la completan, el trabajo se publica sin fecha
	// (la fecha se acuerda después, al aceptar a un postulante).
	$fecha = trim($fecha);
	$fecha_invalida = false;
	if ($fecha !== '') {
		$f_obj = DateTime::createFromFormat('Y-m-d', $fecha);
		$fecha_invalida = !$f_obj || $f_obj->format('Y-m-d') !== $fecha || $fecha < date('Y-m-d');
	}

	$campos_incompletos = $titulo_trabajo === '' || $tipo_tecnico === '' || $descripcion === '' || $direccion === '' || $fecha_invalida
		|| ($tipo_tecnico === 'otro' && $tipo_tecnico_otro === '');

	// Puede venir una sola foto/video o varios (carrusel). El input se llama
	// publicacion_archivo[] y se normaliza a una lista de archivos válidos.
	$archivos_subidos = [];
	if (isset($_FILES['publicacion_archivo']) && is_array($_FILES['publicacion_archivo']['name'])) {
		$f = $_FILES['publicacion_archivo'];
		foreach ($f['name'] as $i => $nombre_original) {
			if ($f['error'][$i] === UPLOAD_ERR_OK) {
				$archivos_subidos[] = ['tmp' => $f['tmp_name'][$i], 'name' => $nombre_original];
			}
		}
	}
	$archivo_subido = count($archivos_subidos) > 0;

	if ($campos_incompletos || !$archivo_subido) {
		header('Location: ../publicar.php?error=campos_incompletos');
		exit;
	}

	// Buscar el id_cliente correspondiente al usuario logueado
	$stmt = $conexion->prepare('SELECT id_cliente FROM cliente WHERE id_usuario = ?');
	$stmt->bind_param('i', $_SESSION['usuario_id']);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$fila) {
		header('Location: ../publicar.php?error=cuenta_invalida');
		exit;
	}
	$id_cliente = $fila['id_cliente'];

	/* =========================================================
	   GUARDAR LA FOTO O VIDEO
	   ========================================================= */

	$carpeta_destino = __DIR__ . '/../uploads/publicaciones/';
	if (!is_dir($carpeta_destino)) {
		mkdir($carpeta_destino, 0755, true);
	}

	// Máximo 10 archivos por publicación, solo imágenes o videos permitidos.
	$permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'webm', 'mov'];
	$nombres_guardados = [];
	foreach (array_slice($archivos_subidos, 0, 10) as $arch) {
		$extension = strtolower(pathinfo($arch['name'], PATHINFO_EXTENSION));
		if (!in_array($extension, $permitidas, true)) {
			continue;
		}
		$nombre_archivo = uniqid('pub_', true) . '.' . $extension;
		if (move_uploaded_file($arch['tmp'], $carpeta_destino . $nombre_archivo)) {
			$nombres_guardados[] = $nombre_archivo;
		}
	}

	if (empty($nombres_guardados)) {
		header('Location: ../publicar.php?error=archivo_invalido');
		exit;
	}

	// La primera foto queda también en trabajo.archivo como portada
	$nombre_archivo = $nombres_guardados[0];

	$conexion->begin_transaction();

	try {
		$stmt = $conexion->prepare('INSERT INTO trabajo (titulo, descripcion, categoria, ubicacion, archivo, fecha_trabajo) VALUES (?, ?, ?, ?, ?, ?)');
		$fecha_db = $fecha === '' ? null : $fecha;   // sin fecha límite -> NULL
		$stmt->bind_param('ssssss', $titulo_trabajo, $descripcion, $categoria, $direccion, $nombre_archivo, $fecha_db);
		$stmt->execute();
		$id_trabajo = $conexion->insert_id;
		$stmt->close();

		$stmt = $conexion->prepare('INSERT INTO trabajo_cliente (id_cliente, id_trabajo) VALUES (?, ?)');
		$stmt->bind_param('ii', $id_cliente, $id_trabajo);
		$stmt->execute();
		$stmt->close();

		// Una fila por cada foto/video del carrusel, respetando el orden
		$stmt = $conexion->prepare('INSERT INTO trabajo_archivo (id_trabajo, archivo, orden) VALUES (?, ?, ?)');
		foreach ($nombres_guardados as $orden => $nombre) {
			$stmt->bind_param('isi', $id_trabajo, $nombre, $orden);
			$stmt->execute();
		}
		$stmt->close();

		$conexion->commit();

	} catch (mysqli_sql_exception $e) {
		$conexion->rollback();
		header('Location: ../publicar.php?error=error_servidor');
		exit;
	}
}

// Avisa a los técnicos de esa especialidad (al instante si tienen
// Técnico Plus, con demora si no). Si algo falla acá, la publicación
// ya quedó guardada, así que no se corta el flujo.
try {
	notificar_tecnicos_de_trabajo($conexion, $categoria, $titulo_trabajo, (int) $_SESSION['usuario_id']);
} catch (Throwable $e) {
	// se ignora a propósito
}

// Alertas "según preferencias": a quienes tienen apagadas las ofertas pero
// quieren enterarse de lo que coincide con su puesto / zona. Va aparte para
// que si falla una de las dos, la otra igual se envíe.
try {
	notificar_alertas_por_preferencias($conexion, $categoria, $titulo_trabajo, $direccion, (int) $_SESSION['usuario_id']);
} catch (Throwable $e) {
	// se ignora a propósito
}

header('Location: ../feed.php?publicado=1');
exit;