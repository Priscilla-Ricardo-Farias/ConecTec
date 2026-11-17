<?php
// procesar_ajustes.php
//
// Todos los formularios de ajustes.php mandan acá, con un campo
// oculto "seccion" que indica cuál se envió. Por ahora solo valida
// lo mínimo y redirige de nuevo a ajustes.php con un aviso de
// "guardado". Cuando conectes la base de datos, cada rama del switch
// hace su propio UPDATE sobre la fila del usuario logueado.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_especialidad.php';
require_once __DIR__ . '/../includes/funciones_certificados.php';
exigir_empresa_habilitada($conexion, '../');

require_once __DIR__ . '/../conexion.php';

$id_usuario_propio = (int) $_SESSION['usuario_id'];
$seccion            = $_POST['seccion'] ?? '';

// Si los archivos suman más que post_max_size de php.ini, PHP descarta todo el
// formulario (llega vacío). Se avisa en vez de no hacer nada.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
	header('Location: ../ajustes.php?cert=grande#certificados');
	exit;
}

// Fila de "tecnico" de la cuenta logueada (null si la cuenta no es de técnico).
function tecnico_del_usuario($conexion, $id_usuario) {
	$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

// Borra un CV de la carpeta uploads/cv (basename evita salirse de la carpeta)
function borrar_archivo_cv($nombre) {
	if (!$nombre) {
		return;
	}
	$ruta = __DIR__ . '/../uploads/cv/' . basename($nombre);
	if (is_file($ruta)) {
		unlink($ruta);
	}
}

switch ($seccion) {

	case 'correo':
		$correo_nuevo = trim($_POST['correo_nuevo'] ?? '');
		if ($correo_nuevo === '' || !filter_var($correo_nuevo, FILTER_VALIDATE_EMAIL)) {
			header('Location: ../ajustes.php?error=correo_invalido#cuenta');
			exit;
		}
		/* Sin base de datos, el "guardado" es actualizar la sesión, así
		   el cambio se ve reflejado al toque en toda la app. Con la base
		   de datos real, lo normal es guardarlo como "correo pendiente
		   de confirmar" y recién reemplazarlo cuando confirme por mail. */
		$_SESSION['usuario_correo'] = $correo_nuevo;
		break;

	case 'notificaciones':
		// Cada interruptor llega solo si estaba tildado; los que no llegan
		// quedan guardados en 0 (desactivados).
		$noti = [];
		foreach (['noti_ofertas', 'noti_postulaciones', 'noti_cambios_postulaciones', 'noti_mensajes', 'noti_alertas_preferencias'] as $campo_noti) {
			$noti[$campo_noti] = isset($_POST[$campo_noti]) ? 1 : 0;
		}

		$stmt = $conexion->prepare(
			'INSERT INTO preferencia_usuario
			   (id_usuario, noti_ofertas, noti_postulaciones, noti_cambios_postulaciones, noti_mensajes, noti_alertas_preferencias)
			 VALUES (?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE
			   noti_ofertas               = VALUES(noti_ofertas),
			   noti_postulaciones         = VALUES(noti_postulaciones),
			   noti_cambios_postulaciones = VALUES(noti_cambios_postulaciones),
			   noti_mensajes              = VALUES(noti_mensajes),
			   noti_alertas_preferencias  = VALUES(noti_alertas_preferencias)'
		);
		$stmt->bind_param(
			'iiiiii',
			$id_usuario_propio,
			$noti['noti_ofertas'],
			$noti['noti_postulaciones'],
			$noti['noti_cambios_postulaciones'],
			$noti['noti_mensajes'],
			$noti['noti_alertas_preferencias']
		);
		$stmt->execute();
		$stmt->close();
		break;

	case 'preferencias_busqueda':
		// La zona se guarda porque Técnico Plus la usa para mostrar primero
		// los trabajos cercanos (ver feed.php).
		$ubicacion = trim($_POST['pref_ubicacion'] ?? '');
		$ubicacion = $ubicacion === '' ? null : mb_substr($ubicacion, 0, 150);

		// El puesto se guarda porque las "Alertas de empleos según
		// preferencias" lo comparan con la categoría de cada trabajo nuevo.
		$puestos_validos = array_merge(array_keys(especialidades_conectec()), ['otro']);
		$puesto          = $_POST['pref_puesto'] ?? '';
		$puesto          = in_array($puesto, $puestos_validos, true) ? $puesto : null;

		$stmt = $conexion->prepare(
			'INSERT INTO preferencia_usuario (id_usuario, pref_ubicacion, pref_puesto)
			 VALUES (?, ?, ?)
			 ON DUPLICATE KEY UPDATE pref_ubicacion = VALUES(pref_ubicacion), pref_puesto = VALUES(pref_puesto)'
		);
		$stmt->bind_param('iss', $id_usuario_propio, $ubicacion, $puesto);
		$stmt->execute();
		$stmt->close();

		/* TODO: guardar $_POST['pref_modalidad'] (array),
		   $_POST['pref_tipo_contrato'] y
		   $_POST['pref_salario_min'] / $_POST['pref_salario_max']. */
		break;

	case 'preferencias_contratacion':
		/* TODO: guardar $_POST['pref_categorias'] (array), $_POST['pref_ubicacion'],
		   $_POST['pref_modalidad'] (array), $_POST['pref_tipo_contratacion']. */
		break;

	case 'privacidad':
		// $_POST['privacidad_perfil'] todavía no se guarda: el <select>
		// del formulario ofrece 3 opciones (todos/registrados/contactos)
		// pero la columna de la base solo admite 2 (publico/privado), así
		// que falta definir esa equivalencia antes de conectarlo.

		$privacidad_cv_visible = isset($_POST['privacidad_cv_visible']) ? 1 : 0;

		$stmt = $conexion->prepare(
			'INSERT INTO preferencia_usuario (id_usuario, privacidad_cv_visible)
			 VALUES (?, ?)
			 ON DUPLICATE KEY UPDATE privacidad_cv_visible = VALUES(privacidad_cv_visible)'
		);
		$stmt->bind_param('ii', $id_usuario_propio, $privacidad_cv_visible);
		$stmt->execute();
		$stmt->close();

		// Subida del CV (solo para cuentas de técnico).
		if (($_SESSION['usuario_tipo'] ?? '') === 'tecnico'
			&& isset($_FILES['cv_archivo'])
			&& $_FILES['cv_archivo']['error'] === UPLOAD_ERR_OK
		) {
			$extensiones_permitidas = ['pdf', 'doc', 'docx'];
			$extension = strtolower(pathinfo($_FILES['cv_archivo']['name'], PATHINFO_EXTENSION));

			if (in_array($extension, $extensiones_permitidas, true)) {
				$carpeta_destino = __DIR__ . '/../uploads/cv/';
				if (!is_dir($carpeta_destino)) {
					mkdir($carpeta_destino, 0755, true);
				}

				// CV anterior, para borrar el archivo viejo cuando se reemplaza
				$stmt = $conexion->prepare('SELECT cv_archivo FROM tecnico WHERE id_usuario = ?');
				$stmt->bind_param('i', $id_usuario_propio);
				$stmt->execute();
				$fila_cv     = $stmt->get_result()->fetch_assoc();
				$stmt->close();
				$cv_anterior = $fila_cv['cv_archivo'] ?? null;

				$nombre_archivo = 'cv_' . uniqid('', true) . '.' . $extension;
				move_uploaded_file($_FILES['cv_archivo']['tmp_name'], $carpeta_destino . $nombre_archivo);

				$stmt = $conexion->prepare('UPDATE tecnico SET cv_archivo = ? WHERE id_usuario = ?');
				$stmt->bind_param('si', $nombre_archivo, $id_usuario_propio);
				$guardado = $stmt->execute();
				$stmt->close();

				if ($guardado) {
					borrar_archivo_cv($cv_anterior);
				}
			}
		}
		break;

	case 'eliminar_cv':
		// Deja el CV vacío y borra el archivo. Solo tiene efecto en
		// cuentas de técnico (las únicas con fila en "tecnico").
		$stmt = $conexion->prepare('SELECT cv_archivo FROM tecnico WHERE id_usuario = ?');
		$stmt->bind_param('i', $id_usuario_propio);
		$stmt->execute();
		$fila_cv = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if ($fila_cv && $fila_cv['cv_archivo']) {
			$stmt = $conexion->prepare('UPDATE tecnico SET cv_archivo = NULL WHERE id_usuario = ?');
			$stmt->bind_param('i', $id_usuario_propio);
			$guardado = $stmt->execute();
			$stmt->close();

			if ($guardado) {
				borrar_archivo_cv($fila_cv['cv_archivo']);
			}
		}
		break;

	case 'especialidades':
		// Solo técnicos. Se guardan todas las especialidades marcadas más las que
		// escriban a mano en "otra" (separadas por coma). Las variaciones de una
		// misma especialidad ("informático", "informática/o") se juntan en una sola.
		$tec = tecnico_del_usuario($conexion, $id_usuario_propio);
		if (!$tec) {
			header('Location: ../ajustes.php');
			exit;
		}

		$validas  = array_keys(especialidades_conectec());
		$elegidas = $_POST['especialidades'] ?? [];
		$elegidas = is_array($elegidas) ? array_values(array_filter($elegidas, fn($v) => in_array($v, $validas, true))) : [];

		$otras = preg_split('/[,;]+/', (string) ($_POST['esp_otra'] ?? ''));
		$otras = array_slice(array_values(array_filter(array_map(fn($v) => mb_substr(trim($v), 0, 100), $otras), fn($v) => $v !== '')), 0, 5);

		try {
			$guardo = guardar_especialidades_tecnico($conexion, (int) $tec['id_tecnico'], array_merge($elegidas, $otras));
		} catch (Throwable $e) {
			header('Location: ../ajustes.php?cert=error#certificados');
			exit;
		}

		header('Location: ../ajustes.php?cert=' . ($guardo ? 'esp_ok' : 'esp_vacia') . '#certificados');
		exit;

	case 'certificado_subir':
		// Un certificado técnico (o el reintento de uno rechazado). Siempre queda PENDIENTE de
		// aprobación: el estado nunca viene del formulario y lo cambia solo un administrador.
		$tec = tecnico_del_usuario($conexion, $id_usuario_propio);
		if (!$tec) {
			header('Location: ../ajustes.php');
			exit;
		}

		$resultado = certificado_presentar(
			$conexion,
			['id_tecnico' => (int) $tec['id_tecnico'], 'id_usuario' => $id_usuario_propio],
			$_POST,
			$_FILES['cert_archivo'] ?? null
		);

		header('Location: ../ajustes.php?cert=' . ($resultado['ok'] ? 'enviado' : $resultado['codigo']) . '#certificados');
		exit;

	case 'certificado_eliminar':
		// Cancelar un envío que todavía está pendiente. Los aprobados y rechazados se conservan (historial).
		$tec = tecnico_del_usuario($conexion, $id_usuario_propio);
		if (!$tec) {
			header('Location: ../ajustes.php');
			exit;
		}

		$cancelado = certificado_cancelar_pendiente($conexion, (int) ($_POST['id_certificado'] ?? 0), (int) $tec['id_tecnico']);

		header('Location: ../ajustes.php?cert=' . ($cancelado ? 'cancelado' : 'no_cancelable') . '#certificados');
		exit;

	case 'apariencia':
		$modo_oscuro = isset($_POST['modo_oscuro']) ? 1 : 0;

		$stmt = $conexion->prepare(
			'INSERT INTO preferencia_usuario (id_usuario, modo_oscuro)
			 VALUES (?, ?)
			 ON DUPLICATE KEY UPDATE modo_oscuro = VALUES(modo_oscuro)'
		);
		$stmt->bind_param('ii', $id_usuario_propio, $modo_oscuro);
		$stmt->execute();
		$stmt->close();
		break;

	default:
		header('Location: ../ajustes.php');
		exit;
}

$anclas = [
	'correo'                     => 'cuenta',
	'notificaciones'             => 'notificaciones',
	'preferencias_busqueda'      => 'preferencias',
	'preferencias_contratacion'  => 'preferencias',
	'privacidad'                 => 'privacidad',
	'eliminar_cv'                => 'privacidad',
	'apariencia'                 => 'apariencia',
];

header('Location: ../ajustes.php?guardado=1#' . ($anclas[$seccion] ?? ''));
exit;