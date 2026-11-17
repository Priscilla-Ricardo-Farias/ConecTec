<?php
// includes/funciones_notificaciones.php
//
// Todo lo que crea notificaciones vive acá, para que cada pantalla
// (postularse, aceptar, chatear, publicar) solo llame a una función.
//
// Cada tipo de aviso respeta el interruptor del usuario que lo recibe
// (Ajustes > Notificaciones, tabla preferencia_usuario):
//
//   noti_ofertas                → trabajo nuevo de tu especialidad
//                                 (notificar_tecnicos_de_trabajo, en funciones_suscripcion.php)
//   noti_postulaciones          → postulación recibida / enviada
//   noti_cambios_postulaciones  → te aceptaron / eligieron a otro
//   noti_mensajes               → mensaje nuevo
//   noti_alertas_preferencias   → trabajo que coincide con tus preferencias
//
// Los avisos del sistema (cuenta verificada, soporte) no pasan por acá
// y se mandan siempre.
//
// Ninguna función corta el flujo si algo falla: la notificación es un
// "extra", nunca tiene que impedir postularse, aceptar o mandar un mensaje.

require_once __DIR__ . '/funciones_suscripcion.php';
require_once __DIR__ . '/funciones_especialidad.php';

const PREFS_NOTIFICACION = [
	'noti_ofertas',
	'noti_postulaciones',
	'noti_cambios_postulaciones',
	'noti_mensajes',
	'noti_alertas_preferencias',
];

/**
 * ¿El usuario tiene activado este tipo de aviso? Si nunca tocó Ajustes
 * (no tiene fila en preferencia_usuario), están todos activados.
 */
function usuario_acepta_notificacion($conexion, $id_usuario, $preferencia) {
	// La columna sale de una lista fija, nunca de lo que escribe el usuario.
	if (!in_array($preferencia, PREFS_NOTIFICACION, true)) {
		return true;
	}

	$stmt = $conexion->prepare("SELECT {$preferencia} AS activa FROM preferencia_usuario WHERE id_usuario = ?");
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	return $fila ? (bool) $fila['activa'] : true;
}

/**
 * Crea una notificación para $id_destino. $id_origen es quien la
 * provocó (se muestra su nombre en la lista). $preferencia es el
 * interruptor que se consulta antes de crearla (null = siempre se crea).
 * Devuelve true si se creó.
 */
function crear_notificacion($conexion, $id_destino, $id_origen, $tipo, $texto, $preferencia = null) {
	$id_destino = (int) $id_destino;
	$id_origen  = $id_origen ? (int) $id_origen : null;

	// Nadie se avisa a sí mismo.
	if ($id_destino <= 0 || $id_destino === $id_origen) {
		return false;
	}

	try {
		if ($preferencia !== null && !usuario_acepta_notificacion($conexion, $id_destino, $preferencia)) {
			return false;
		}

		$texto = mb_substr($texto, 0, 255);

		$stmt = $conexion->prepare('INSERT INTO notificacion (id_usuario, id_usuario_origen, tipo, texto) VALUES (?, ?, ?, ?)');
		$stmt->bind_param('iiss', $id_destino, $id_origen, $tipo, $texto);
		$ok = $stmt->execute();
		$stmt->close();

		return (bool) $ok;
	} catch (Throwable $e) {
		return false;
	}
}

/**
 * Título del trabajo + id_usuario de quien lo publicó (cliente o
 * empresa). Devuelve null si el trabajo no existe.
 */
function datos_trabajo_para_avisos($conexion, $id_trabajo) {
	$stmt = $conexion->prepare(
		'SELECT tr.titulo, COALESCE(c.id_usuario, emp.id_usuario) AS id_dueno
		 FROM trabajo tr
		 LEFT JOIN trabajo_cliente tc    ON tc.id_trabajo = tr.id_trabajo
		 LEFT JOIN cliente c             ON c.id_cliente = tc.id_cliente
		 LEFT JOIN trabajo_empleador te  ON te.id_trabajo = tr.id_trabajo
		 LEFT JOIN empleador emp         ON emp.id_empleador = te.id_empleador
		 WHERE tr.id_trabajo = ?'
	);
	$stmt->bind_param('i', $id_trabajo);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	return $fila ?: null;
}

/**
 * Un técnico se postuló a un trabajo:
 *   - el dueño del trabajo recibe "se postuló" (postulación RECIBIDA)
 *   - el técnico recibe la confirmación (postulación ENVIADA)
 */
function notificar_postulacion($conexion, $id_trabajo, $id_usuario_tecnico) {
	try {
		$trabajo = datos_trabajo_para_avisos($conexion, $id_trabajo);
		if (!$trabajo || !$trabajo['id_dueno']) {
			return;
		}
		$id_dueno = (int) $trabajo['id_dueno'];

		crear_notificacion($conexion, $id_dueno, $id_usuario_tecnico, 'postulacion',
			'Se postuló a tu publicación: ' . $trabajo['titulo'], 'noti_postulaciones');

		crear_notificacion($conexion, $id_usuario_tecnico, $id_dueno, 'postulacion',
			'Tu postulación fue enviada: ' . $trabajo['titulo'], 'noti_postulaciones');
	} catch (Throwable $e) {
		// se ignora a propósito
	}
}

/**
 * Cambio de estado de una postulación, para el técnico que se postuló:
 * aceptada, o rechazada porque el dueño eligió a otro técnico.
 */
function notificar_cambio_postulacion($conexion, $id_usuario_tecnico, $id_usuario_dueno, $titulo, $aceptada) {
	$texto = $aceptada
		? '¡Aceptaron tu postulación a: ' . $titulo . '!'
		: 'Eligieron a otro técnico para: ' . $titulo;

	crear_notificacion($conexion, $id_usuario_tecnico, $id_usuario_dueno, 'cambio', $texto, 'noti_cambios_postulaciones');
}

/**
 * Mensaje nuevo en un chat. Si el destinatario todavía no leyó el aviso
 * anterior de la misma persona, no se apila otro igual: se vuelve a
 * subir ese aviso (como hace Gmail con una conversación).
 */
function notificar_mensaje_nuevo($conexion, $id_destino, $id_origen) {
	$id_destino = (int) $id_destino;
	$id_origen  = (int) $id_origen;
	$texto      = 'Te envió un nuevo mensaje';

	if ($id_destino <= 0 || $id_destino === $id_origen) {
		return;
	}

	try {
		if (!usuario_acepta_notificacion($conexion, $id_destino, 'noti_mensajes')) {
			return;
		}

		$stmt = $conexion->prepare(
			"SELECT id_notificacion FROM notificacion
			 WHERE id_usuario = ? AND id_usuario_origen = ? AND tipo = 'mensaje' AND leida = 0 AND texto = ?
			 LIMIT 1"
		);
		$stmt->bind_param('iis', $id_destino, $id_origen, $texto);
		$stmt->execute();
		$existente = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if ($existente) {
			$stmt = $conexion->prepare('UPDATE notificacion SET fecha = NOW() WHERE id_notificacion = ?');
			$stmt->bind_param('i', $existente['id_notificacion']);
			$stmt->execute();
			$stmt->close();
			return;
		}

		crear_notificacion($conexion, $id_destino, $id_origen, 'mensaje', $texto);
	} catch (Throwable $e) {
		// se ignora a propósito
	}
}

/**
 * "Alertas de empleos según preferencias": avisa a los técnicos de la
 * especialidad del trabajo cuyas preferencias de Ajustes (puesto y/o
 * zona) coinciden con el trabajo recién publicado.
 *
 * Quien tiene activado "Nuevas ofertas de trabajo" ya recibió el aviso
 * de este mismo trabajo (notificar_tecnicos_de_trabajo), así que acá se
 * saltea para no mandarle dos avisos iguales. Las alertas llegan a quien
 * quiere enterarse SOLO de lo que coincide con sus preferencias.
 *
 * Misma regla de demora que las ofertas: al instante con Técnico Plus,
 * con NOTIF_DEMORA_GRATIS_MIN de demora sin plan.
 */
function notificar_alertas_por_preferencias($conexion, $categoria, $titulo, $ubicacion, $id_usuario_origen) {
	$texto  = mb_substr('Coincide con tus preferencias: ' . $titulo, 0, 255);
	$demora = NOTIF_DEMORA_GRATIS_MIN;

	// La categoría se compara por su clave, así "Informático" y "informatica" son lo mismo.
	$clave = clave_especialidad($categoria);

	// "otro" en las preferencias = cualquier trabajo que no sea de la lista fija
	$categoria_es_otra = es_especialidad_de_lista($clave) ? 0 : 1;

	$sql = "INSERT INTO notificacion (id_usuario, id_usuario_origen, tipo, texto, visible_desde)
	        SELECT t.id_usuario, ?, 'alerta', ?,
	               IF(" . sql_plan_activo('t.id_usuario') . ", NULL, DATE_ADD(NOW(), INTERVAL ? MINUTE))
	        FROM tecnico t
	        JOIN usuario u ON u.id_usuario = t.id_usuario AND u.estado_cuenta = 'activa'
	        JOIN preferencia_usuario pu ON pu.id_usuario = t.id_usuario
	        WHERE EXISTS (SELECT 1 FROM tecnico_especialidad te WHERE te.id_tecnico = t.id_tecnico AND te.clave = ?)
	          AND t.id_usuario <> ?
	          AND pu.noti_alertas_preferencias = 1
	          AND pu.noti_ofertas = 0
	          AND (COALESCE(pu.pref_puesto, '') <> '' OR COALESCE(pu.pref_ubicacion, '') <> '')
	          AND (COALESCE(pu.pref_puesto, '') = ''
	               OR LOWER(pu.pref_puesto) = LOWER(?)
	               OR (pu.pref_puesto = 'otro' AND ? = 1))
	          AND (COALESCE(pu.pref_ubicacion, '') = ''
	               OR LOCATE(LOWER(pu.pref_ubicacion), LOWER(?)) > 0)";

	$stmt = $conexion->prepare($sql);
	$stmt->bind_param('isisisis', $id_usuario_origen, $texto, $demora, $clave, $id_usuario_origen, $clave, $categoria_es_otra, $ubicacion);
	$stmt->execute();
	$stmt->close();
}
