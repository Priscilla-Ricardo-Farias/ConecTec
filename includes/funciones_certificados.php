<?php
// includes/funciones_certificados.php
//
// Certificados técnicos con revisión del administrador.
//
// Cada certificado es una fila de "tecnico_certificado", vinculada al técnico
// (id_tecnico) y a una de SUS especialidades (clave_especialidad). Estados:
//
//   pendiente → lo envió el técnico y espera la revisión de un administrador
//   aprobado  → un administrador lo autorizó: recién ahí se muestra como verificado
//   rechazado → un administrador lo rechazó (con un motivo): el técnico lo corrige
//               y lo vuelve a presentar. El reintento es una fila NUEVA que apunta
//               a la rechazada (id_certificado_anterior), así no se pierde el historial.
//
// El estado solo lo cambia certificado_resolver(), que llaman las pantallas del
// panel de administración. Ningún formulario del técnico recibe ni acepta un estado.
//
// (El certificado con el que el técnico se registró sigue su propio camino:
//  tecnico.certificado_archivo + tecnico.estado_verificacion, en Verificaciones.)

require_once __DIR__ . '/funciones_especialidad.php';
require_once __DIR__ . '/funciones_notificaciones.php';

// Máximo de certificados "vivos" (pendientes + aprobados) por técnico. Los rechazados no cuentan.
const CERT_MAX_ACTIVOS = 10;

function certificado_estados() {
	return [
		'pendiente' => 'Pendiente de aprobación',
		'aprobado'  => 'Aprobado',
		'rechazado' => 'Rechazado',
	];
}

/**
 * Certificados de un técnico (el más viejo primero). Con $solo_aprobados = true devuelve
 * solo los verificados: es lo que ve cualquier visitante del perfil.
 * Cada fila trae "reintentos" (cuántos certificados nuevos lo reemplazan) y "nombre_anterior".
 */
function certificados_del_tecnico($conexion, $id_tecnico, $solo_aprobados = false) {
	$sql = "SELECT c.id_certificado, c.nombre, c.archivo, c.especialidad, c.clave_especialidad, c.anio,
	               c.institucion, c.estado, c.motivo_rechazo, c.id_certificado_anterior,
	               c.fecha_subida, c.fecha_revision, a.nombre AS nombre_anterior,
	               (SELECT COUNT(*) FROM tecnico_certificado h WHERE h.id_certificado_anterior = c.id_certificado) AS reintentos
	        FROM tecnico_certificado c
	        LEFT JOIN tecnico_certificado a ON a.id_certificado = c.id_certificado_anterior
	        WHERE c.id_tecnico = ?";

	if ($solo_aprobados) {
		$sql .= " AND c.estado = 'aprobado'";
	}

	$sql .= ' ORDER BY c.id_certificado';

	$stmt = $conexion->prepare($sql);
	$stmt->bind_param('i', $id_tecnico);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return $filas;
}

/**
 * Revisa un archivo subido ($_FILES['x'] de UN archivo). El tipo se comprueba mirando
 * el contenido, no solo la extensión. Devuelve ['ok' => true, 'ext' => 'pdf'] o
 * ['ok' => false, 'error' => 'archivo' | 'grande' | 'formato'].
 */
function certificado_validar_archivo($f) {
	if (!is_array($f) || !isset($f['error']) || is_array($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
		return ['ok' => false, 'error' => 'archivo'];
	}
	if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > 5 * 1024 * 1024) {
		return ['ok' => false, 'error' => 'grande'];
	}
	if ($f['error'] !== UPLOAD_ERR_OK) {
		return ['ok' => false, 'error' => 'archivo'];
	}

	$cabecera = (string) @file_get_contents($f['tmp_name'], false, null, 0, 5);
	if (strncmp($cabecera, '%PDF', 4) === 0) {
		return ['ok' => true, 'ext' => 'pdf'];
	}

	$tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
	$info  = @getimagesize($f['tmp_name']);
	if ($info !== false && isset($tipos[$info['mime']])) {
		return ['ok' => true, 'ext' => $tipos[$info['mime']]];
	}

	return ['ok' => false, 'error' => 'formato'];
}

/**
 * Avisa al técnico (campanita / bandeja de notificaciones) sobre su certificado.
 * $evento: 'pendiente' | 'aprobado' | 'rechazado'. Los textos entran en los 255
 * caracteres de notificacion.texto; el motivo completo se ve siempre en Configuración.
 */
function certificado_notificar($conexion, $id_usuario, $evento, $nombre, $motivo = '') {
	$nombre_corto = mb_strlen($nombre) > 50 ? mb_substr($nombre, 0, 49) . '…' : $nombre;

	if ($evento === 'aprobado') {
		$texto = 'Tu certificado «' . $nombre_corto . '» fue aprobado. Ya figura como verificado en tu perfil.';
	} elseif ($evento === 'rechazado') {
		$cola  = ' Corregilo y volvé a presentarlo desde Configuración.';
		$texto = 'Tu certificado «' . $nombre_corto . '» fue rechazado: tenés que volver a intentarlo.';

		if (trim($motivo) !== '') {
			$cabeza = $texto . ' Motivo: ';
			$lugar  = 255 - mb_strlen($cabeza) - mb_strlen($cola);
			$motivo = trim($motivo);
			$motivo = mb_strlen($motivo) > $lugar ? mb_substr($motivo, 0, max(0, $lugar - 1)) . '…' : $motivo;
			$texto  = $cabeza . $motivo . $cola;
		} else {
			$texto .= $cola;
		}
	} else {
		$texto = 'Recibimos tu certificado «' . $nombre_corto . '». Quedó pendiente de aprobación: te avisamos cuando un administrador lo revise.';
	}

	// Es un aviso del sistema (sin usuario de origen) y se envía siempre, sin depender de preferencias.
	return crear_notificacion($conexion, $id_usuario, null, 'certificado', $texto);
}

/**
 * Registra un certificado nuevo (o el reintento de uno rechazado) de un técnico.
 * $tecnico: fila con id_tecnico e id_usuario. $post: los campos del formulario
 * (cert_nombre, cert_especialidad, cert_anio, cert_institucion, cert_reemplaza).
 * $archivo: $_FILES['cert_archivo']. $mover: solo para pruebas (por defecto move_uploaded_file).
 *
 * Siempre queda en estado 'pendiente'. Devuelve ['ok' => bool, 'codigo' => string]:
 * 'enviado' o el motivo del error: nombre, especialidad, archivo, grande, formato, limite, reintento, error.
 */
function certificado_presentar($conexion, array $tecnico, array $post, $archivo, $mover = null) {
	$id_tecnico = (int) $tecnico['id_tecnico'];
	$mover      = $mover ?: 'move_uploaded_file';

	$nombre = trim((string) ($post['cert_nombre'] ?? ''));
	if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
		return ['ok' => false, 'codigo' => 'nombre'];
	}

	// La especialidad tiene que ser una de las del propio técnico.
	$clave = clave_especialidad($post['cert_especialidad'] ?? '');
	$etiqueta_esp = null;
	if ($clave !== '') {
		foreach (especialidades_de_tecnico($conexion, $id_tecnico) as $esp) {
			if ($esp['clave'] === $clave) {
				$etiqueta_esp = $esp['especialidad'];
				break;
			}
		}
	}
	if ($etiqueta_esp === null) {
		return ['ok' => false, 'codigo' => 'especialidad'];
	}

	$anio = (int) ($post['cert_anio'] ?? 0);
	$anio = ($anio >= 1950 && $anio <= (int) date('Y')) ? $anio : null;

	$institucion = mb_substr(trim((string) ($post['cert_institucion'] ?? '')), 0, 150);
	$institucion = $institucion === '' ? null : $institucion;

	// Reintento: tiene que ser un certificado RECHAZADO de este mismo técnico y sin otro reintento.
	$id_anterior = (int) ($post['cert_reemplaza'] ?? 0);
	if ($id_anterior > 0) {
		$stmt = $conexion->prepare(
			"SELECT c.id_certificado FROM tecnico_certificado c
			 WHERE c.id_certificado = ? AND c.id_tecnico = ? AND c.estado = 'rechazado'
			   AND NOT EXISTS (SELECT 1 FROM tecnico_certificado h WHERE h.id_certificado_anterior = c.id_certificado)"
		);
		$stmt->bind_param('ii', $id_anterior, $id_tecnico);
		$stmt->execute();
		$valido = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if (!$valido) {
			return ['ok' => false, 'codigo' => 'reintento'];
		}
	}
	$id_anterior_db = $id_anterior > 0 ? $id_anterior : null;

	$stmt = $conexion->prepare("SELECT COUNT(*) AS n FROM tecnico_certificado WHERE id_tecnico = ? AND estado IN ('pendiente', 'aprobado')");
	$stmt->bind_param('i', $id_tecnico);
	$stmt->execute();
	$activos = (int) $stmt->get_result()->fetch_assoc()['n'];
	$stmt->close();

	if ($activos >= CERT_MAX_ACTIVOS) {
		return ['ok' => false, 'codigo' => 'limite'];
	}

	$valida = certificado_validar_archivo($archivo);
	if (!$valida['ok']) {
		return ['ok' => false, 'codigo' => $valida['error']];
	}

	$carpeta = __DIR__ . '/../uploads/certificados/';
	if (!is_dir($carpeta)) {
		mkdir($carpeta, 0755, true);
	}

	$nombre_archivo = uniqid('cert_', true) . '.' . $valida['ext'];

	if (!$mover($archivo['tmp_name'], $carpeta . $nombre_archivo)) {
		return ['ok' => false, 'codigo' => 'error'];
	}

	try {
		// El estado no viene del formulario: todo certificado nuevo entra como 'pendiente'.
		$stmt = $conexion->prepare(
			"INSERT INTO tecnico_certificado
			   (id_tecnico, nombre, archivo, especialidad, clave_especialidad, anio, institucion, estado, id_certificado_anterior)
			 VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)"
		);
		$stmt->bind_param('issssisi', $id_tecnico, $nombre, $nombre_archivo, $etiqueta_esp, $clave, $anio, $institucion, $id_anterior_db);
		$stmt->execute();
		$stmt->close();
	} catch (Throwable $e) {
		@unlink($carpeta . $nombre_archivo);
		return ['ok' => false, 'codigo' => 'error'];
	}

	certificado_notificar($conexion, (int) $tecnico['id_usuario'], 'pendiente', $nombre);

	return ['ok' => true, 'codigo' => 'enviado'];
}

/**
 * El técnico cancela un envío que todavía está pendiente. Los aprobados y los rechazados
 * no se pueden borrar: forman parte del historial de la revisión.
 */
function certificado_cancelar_pendiente($conexion, $id_certificado, $id_tecnico) {
	$stmt = $conexion->prepare("SELECT archivo FROM tecnico_certificado WHERE id_certificado = ? AND id_tecnico = ? AND estado = 'pendiente'");
	$stmt->bind_param('ii', $id_certificado, $id_tecnico);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$fila) {
		return false;
	}

	$stmt = $conexion->prepare("DELETE FROM tecnico_certificado WHERE id_certificado = ? AND id_tecnico = ? AND estado = 'pendiente'");
	$stmt->bind_param('ii', $id_certificado, $id_tecnico);
	$stmt->execute();
	$borrado = $stmt->affected_rows > 0;
	$stmt->close();

	if ($borrado) {
		$ruta = __DIR__ . '/../uploads/certificados/' . basename($fila['archivo']);
		if (is_file($ruta)) {
			@unlink($ruta);
		}
	}

	return $borrado;
}

/* =========================================================
   PANEL DE ADMINISTRACIÓN
   ========================================================= */

/** Cantidad de certificados en un estado (para los contadores del panel). */
function certificados_contar($conexion, $estado) {
	try {
		$stmt = $conexion->prepare('SELECT COUNT(*) AS n FROM tecnico_certificado WHERE estado = ?');
		$stmt->bind_param('s', $estado);
		$stmt->execute();
		$n = (int) $stmt->get_result()->fetch_assoc()['n'];
		$stmt->close();
		return $n;
	} catch (Throwable $e) {
		return 0;
	}
}

/**
 * Certificados de TODOS los técnicos en un estado, con los datos para revisarlos
 * (el más viejo primero si están pendientes; los últimos revisados primero si no).
 */
function certificados_para_admin($conexion, $estado) {
	$orden = $estado === 'pendiente' ? 'c.id_certificado ASC' : 'c.fecha_revision DESC, c.id_certificado DESC';

	$stmt = $conexion->prepare(
		"SELECT c.id_certificado, c.id_tecnico, c.nombre, c.archivo, c.especialidad, c.clave_especialidad, c.anio,
		        c.institucion, c.estado, c.motivo_rechazo, c.id_certificado_anterior, c.fecha_subida, c.fecha_revision,
		        t.id_usuario, u.nombre AS tecnico_nombre,
		        a.nombre AS nombre_anterior, a.motivo_rechazo AS motivo_anterior
		 FROM tecnico_certificado c
		 JOIN tecnico t ON t.id_tecnico = c.id_tecnico
		 JOIN usuario u ON u.id_usuario = t.id_usuario
		 LEFT JOIN tecnico_certificado a ON a.id_certificado = c.id_certificado_anterior
		 WHERE c.estado = ?
		 ORDER BY $orden"
	);
	$stmt->bind_param('s', $estado);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return $filas;
}

/**
 * Decisión del administrador sobre un certificado PENDIENTE.
 * $decision: 'aprobar' | 'rechazar' (el rechazo exige un motivo). Se guarda quién y cuándo,
 * y se le avisa al técnico. Devuelve ['ok' => bool, 'codigo' => string]:
 * 'aprobado', 'rechazado', 'motivo' (falta el motivo), 'no_pendiente' (ya estaba resuelto o no existe), 'error'.
 *
 * Esta función NO comprueba que quien la llama sea administrador: lo hace la pantalla
 * (admin/procesar_admin_certificado.php) con la sesión de admin y el token CSRF.
 */
function certificado_resolver($conexion, $id_certificado, $decision, $motivo, $id_admin) {
	$id_certificado = (int) $id_certificado;
	$motivo         = mb_substr(trim((string) $motivo), 0, 500);

	if (!in_array($decision, ['aprobar', 'rechazar'], true)) {
		return ['ok' => false, 'codigo' => 'error'];
	}
	if ($decision === 'rechazar' && $motivo === '') {
		return ['ok' => false, 'codigo' => 'motivo'];
	}

	$stmt = $conexion->prepare(
		"SELECT c.nombre, t.id_usuario
		 FROM tecnico_certificado c JOIN tecnico t ON t.id_tecnico = c.id_tecnico
		 WHERE c.id_certificado = ? AND c.estado = 'pendiente'"
	);
	$stmt->bind_param('i', $id_certificado);
	$stmt->execute();
	$cert = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$cert) {
		return ['ok' => false, 'codigo' => 'no_pendiente'];
	}

	$nuevo_estado = $decision === 'aprobar' ? 'aprobado' : 'rechazado';
	$motivo_db    = $decision === 'rechazar' ? $motivo : null;
	$id_admin     = (int) $id_admin;

	try {
		// El "AND estado = 'pendiente'" evita que dos administradores resuelvan el mismo certificado a la vez.
		$stmt = $conexion->prepare(
			"UPDATE tecnico_certificado
			 SET estado = ?, motivo_rechazo = ?, fecha_revision = NOW(), id_admin_revisor = ?
			 WHERE id_certificado = ? AND estado = 'pendiente'"
		);
		$stmt->bind_param('ssii', $nuevo_estado, $motivo_db, $id_admin, $id_certificado);
		$stmt->execute();
		$cambio = $stmt->affected_rows > 0;
		$stmt->close();
	} catch (Throwable $e) {
		return ['ok' => false, 'codigo' => 'error'];
	}

	if (!$cambio) {
		return ['ok' => false, 'codigo' => 'no_pendiente'];
	}

	certificado_notificar($conexion, (int) $cert['id_usuario'], $nuevo_estado, $cert['nombre'], $motivo);

	return ['ok' => true, 'codigo' => $nuevo_estado];
}
