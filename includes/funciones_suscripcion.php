<?php
// includes/funciones_suscripcion.php
//
// Planes de pago de Conectec, estado de la suscripción de cada usuario
// y lo que cambia cuando el plan está activo.
//
//   - cliente  → "Cliente Plus"   (opcional)
//   - tecnico  → "Técnico Plus"   (opcional)
//   - empresa  → "Abono empresa"  (OBLIGATORIO para usar la plataforma)
//
// Un plan está activo si hay una fila en "suscripcion" con
// estado = 'activa' y fecha_vencimiento > NOW().
//
// La INSIGNIA de verificado (cliente y técnico) necesita además que el
// admin apruebe la cuenta (tabla "verificacion_cuenta"). Se muestra
// solo mientras el plan esté activo, como en Instagram.

// Las notificaciones de trabajos nuevos comparan especialidades (clave_especialidad()).
require_once __DIR__ . '/funciones_especialidad.php';

// true  = el "pago" se aprueba solo, sin cobrar nada (sirve para probar).
// false = hay que conectar una pasarela de pago real; mientras no esté
//         conectada, procesar/procesar_pago.php rechaza el pago.
// ¡Acordate de ponerlo en false antes de publicar el sitio!
const PAGOS_MODO_PRUEBA = true;

// Cuánto dura cada pago (no hay renovación automática).
const SUSCRIPCION_DIAS = 30;

// Las cuentas SIN plan reciben las notificaciones de trabajos nuevos
// con esta demora (en minutos). Con plan, llegan al instante.
const NOTIF_DEMORA_GRATIS_MIN = 30;

/**
 * Catálogo de planes. Los precios son de ejemplo (ARS por período):
 * cambialos acá y se actualizan en toda la app.
 */
function planes_conectec() {
	return [
		'cliente_verificado' => [
			'nombre'      => 'Cliente Plus',
			'tipo_cuenta' => 'cliente',
			'precio'      => 3999,
			'obligatorio' => false,
			'beneficios'  => [
				'Insignia de cuenta verificada junto a tu nombre (la confirma el equipo de Conectec)',
				'Atención prioritaria: tus consultas a soporte se responden primero',
			],
		],
		'tecnico_plus' => [
			'nombre'      => 'Técnico Plus',
			'tipo_cuenta' => 'tecnico',
			'precio'      => 6999,
			'obligatorio' => false,
			'beneficios'  => [
				'Insignia de cuenta verificada en tu perfil y cuando te postulás (la confirma el equipo de Conectec)',
				'Notificaciones inmediatas de los trabajos de tu especialidad (el resto las recibe con ' . NOTIF_DEMORA_GRATIS_MIN . ' minutos de demora)',
				'Los trabajos más cercanos a tu zona aparecen primero en el inicio',
				'Atención prioritaria: tus consultas a soporte se responden primero',
			],
		],
		'empresa_abono' => [
			'nombre'      => 'Abono de empresa',
			'tipo_cuenta' => 'empresa',
			'precio'      => 24999,
			'obligatorio' => true,
			'beneficios'  => [
				'Publicar búsquedas de técnicos certificados',
				'Escribirle a los técnicos y agendar reuniones',
				'Atención prioritaria: tus consultas a soporte se responden primero',
			],
		],
	];
}

/** Clave del plan que le corresponde a cada tipo de cuenta. */
function clave_plan_para_tipo($tipo_cuenta) {
	$mapa = [
		'cliente' => 'cliente_verificado',
		'tecnico' => 'tecnico_plus',
		'empresa' => 'empresa_abono',
	];
	return $mapa[$tipo_cuenta] ?? 'cliente_verificado';
}

/** "$6.999" */
function formatear_precio($monto) {
	return '$' . number_format((float) $monto, 0, ',', '.');
}

/**
 * Fragmento SQL "¿este usuario tiene un plan activo?" para meter en un
 * SELECT o un WHERE. $columna_usuario es una columna propia de la
 * consulta (ej: 't.id_usuario'); nunca le pases datos del usuario.
 */
function sql_plan_activo($columna_usuario) {
	return "EXISTS (SELECT 1 FROM suscripcion s_pl
	                WHERE s_pl.id_usuario = {$columna_usuario}
	                  AND s_pl.estado = 'activa'
	                  AND s_pl.fecha_vencimiento > NOW())";
}

/**
 * Devuelve ['plan' => ..., 'fecha_vencimiento' => ...] de la
 * suscripción activa que más tarde vence, o null si no tiene ninguna.
 */
function suscripcion_activa($conexion, $id_usuario) {
	$stmt = $conexion->prepare(
		"SELECT plan, fecha_vencimiento
		 FROM suscripcion
		 WHERE id_usuario = ? AND estado = 'activa' AND fecha_vencimiento > NOW()
		 ORDER BY fecha_vencimiento DESC
		 LIMIT 1"
	);
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	return $fila ?: null;
}

function tiene_plan_activo($conexion, $id_usuario) {
	return suscripcion_activa($conexion, $id_usuario) !== null;
}

/**
 * Estado de habilitación de una cuenta de empresa. Para usar la
 * plataforma necesita las DOS cosas:
 *   1) abono activo (pagó y no venció)      → suscripcion
 *   2) cuenta aprobada por el admin          → empleador.verificada = 1
 *
 * Devuelve ['abono' => bool, 'aprobada' => bool, 'habilitada' => bool].
 * Se consulta la base en cada llamada (sin cachear en la sesión) para
 * que la aprobación del admin, un pago o un vencimiento se noten al
 * instante.
 */
function estado_habilitacion_empresa($conexion, $id_usuario) {
	$abono = tiene_plan_activo($conexion, $id_usuario);

	$stmt = $conexion->prepare('SELECT verificada FROM empleador WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	$aprobada = $fila !== null && (int) $fila['verificada'] === 1;

	return [
		'abono'      => $abono,
		'aprobada'   => $aprobada,
		'habilitada' => $abono && $aprobada,
	];
}

/**
 * true si quien está logueado es una empresa que TODAVÍA no puede usar
 * la plataforma (le falta el abono o la aprobación del admin). Con
 * cualquier otro tipo de cuenta devuelve false.
 */
function empresa_bloqueada($conexion) {
	if (($_SESSION['usuario_tipo'] ?? '') !== 'empresa') {
		return false;
	}
	return !estado_habilitacion_empresa($conexion, (int) $_SESSION['usuario_id'])['habilitada'];
}

/**
 * Las empresas solo pueden usar la plataforma con abono activo Y
 * aprobadas por el admin. Si quien está logueado es una empresa a la
 * que le falta alguna de las dos cosas, la manda a verificar_cuenta.php
 * (donde ve qué le falta y puede pagar) y corta la ejecución. Con
 * cualquier otro tipo de cuenta no hace nada.
 *
 * Va al principio de TODA página o proceso al que una empresa no debe
 * entrar sin habilitar (todas menos verificar_cuenta.php, pagar.php,
 * procesar_pago.php y logout.php). Llamala ANTES de imprimir HTML.
 * $ruta_base es '../' en /procesar.
 */
function exigir_empresa_habilitada($conexion, $ruta_base = '') {
	if (!empresa_bloqueada($conexion)) {
		return;
	}
	header('Location: ' . $ruta_base . 'verificar_cuenta.php?requerido=1');
	exit;
}

/** Nombre anterior: se mantiene para no romper las páginas que ya lo usan. */
function exigir_abono_empresa($conexion, $ruta_base = '') {
	exigir_empresa_habilitada($conexion, $ruta_base);
}

/**
 * Fragmento SQL "¿este usuario muestra la insignia?": plan activo Y
 * cuenta aprobada por el admin. Mismo uso que sql_plan_activo().
 *
 *   - cliente / técnico: aprobada en verificacion_cuenta.
 *   - empresa: aprobada en empleador.verificada (la aprobación del
 *     registro por CUIT) + abono activo.
 */
function sql_insignia($columna_usuario) {
	return '(' . sql_plan_activo($columna_usuario) . "
	        AND (EXISTS (SELECT 1 FROM verificacion_cuenta vc
	                     WHERE vc.id_usuario = {$columna_usuario} AND vc.estado = 'aprobada')
	             OR EXISTS (SELECT 1 FROM empleador em_v
	                        WHERE em_v.id_usuario = {$columna_usuario} AND em_v.verificada = 1)))";
}

/** 'pendiente' | 'aprobada' | 'rechazada' | null (nunca pidió verificación). */
function estado_verificacion_cuenta($conexion, $id_usuario) {
	$stmt = $conexion->prepare('SELECT estado FROM verificacion_cuenta WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	return $fila['estado'] ?? null;
}

/**
 * Solicitudes de verificación esperando al admin, con la forma que
 * espera includes/admin_verif_cards.php.
 */
function verificaciones_cuenta_pendientes($conexion) {
	$filas = $conexion->query(
		"SELECT v.id_usuario AS id, u.nombre, v.fecha_solicitud AS fecha,
		        t.especialidad, t.certificado_archivo, (t.id_tecnico IS NOT NULL) AS es_tecnico
		 FROM verificacion_cuenta v
		 JOIN usuario u ON u.id_usuario = v.id_usuario
		 LEFT JOIN tecnico t ON t.id_usuario = v.id_usuario
		 WHERE v.estado = 'pendiente' AND u.estado_cuenta = 'activa'
		 ORDER BY v.fecha_solicitud DESC"
	)->fetch_all(MYSQLI_ASSOC);

	$items = [];
	foreach ($filas as $f) {
		$items[] = [
			'tipo'                => 'cuenta',
			'id'                  => $f['id'],
			'nombre'              => $f['nombre'],
			'sub'                 => ($f['es_tecnico'] ? 'Técnico · ' . $f['especialidad'] : 'Cliente') . ' · pagó Plus',
			'certificado_archivo' => $f['certificado_archivo'],
			'fecha'               => $f['fecha'],
		];
	}
	return $items;
}

/**
 * Insignia de cuenta verificada junto al nombre.
 *   - 'persona' (cliente y técnico): tilde azul en círculo.
 *   - 'empresa': escudo naranja con tilde, para distinguirla a simple vista.
 */
function insignia_verificada($tipo = 'persona') {
	if ($tipo === 'empresa') {
		return '<span class="insignia-verificada insignia-verificada--empresa" title="Empresa verificada" aria-label="Empresa verificada">'
			. '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 L20 5 V11 C20 16 16.5 20 12 22 C7.5 20 4 16 4 11 V5 Z" fill="currentColor"/>'
			. '<path d="M8 12.5 L11 15.5 L16 9.5" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '</span>';
	}
	return '<span class="insignia-verificada" title="Cuenta verificada" aria-label="Cuenta verificada">'
		. '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/>'
		. '<path d="M8 12.5 L11 15.5 L16 9.5" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
		. '</span>';
}

/**
 * Avisa a los técnicos de la especialidad del trabajo que acaba de
 * publicarse. Con Técnico Plus la notificación se ve al instante
 * (visible_desde = NULL); sin plan, con NOTIF_DEMORA_GRATIS_MIN de
 * demora. Respeta la preferencia "noti_ofertas" (por defecto activa)
 * y no le avisa a quien publicó.
 */
function notificar_tecnicos_de_trabajo($conexion, $categoria, $titulo, $id_usuario_origen) {
	$texto  = mb_substr('Nuevo trabajo de ' . etiqueta_especialidad($categoria) . ': ' . $titulo, 0, 255);
	$demora = NOTIF_DEMORA_GRATIS_MIN;

	// Le llega a todo técnico que tenga ESA especialidad entre las suyas (puede tener varias),
	// sin importar cómo esté escrita: "informática", "Informático", "informatica/o"...
	$clave = clave_especialidad($categoria);

	$sql = "INSERT INTO notificacion (id_usuario, id_usuario_origen, tipo, texto, visible_desde)
	        SELECT t.id_usuario, ?, 'trabajo', ?,
	               IF(" . sql_plan_activo('t.id_usuario') . ", NULL, DATE_ADD(NOW(), INTERVAL ? MINUTE))
	        FROM tecnico t
	        JOIN usuario u ON u.id_usuario = t.id_usuario AND u.estado_cuenta = 'activa'
	        LEFT JOIN preferencia_usuario pu ON pu.id_usuario = t.id_usuario
	        WHERE EXISTS (SELECT 1 FROM tecnico_especialidad te WHERE te.id_tecnico = t.id_tecnico AND te.clave = ?)
	          AND t.id_usuario <> ?
	          AND COALESCE(pu.noti_ofertas, 1) = 1";

	$stmt = $conexion->prepare($sql);
	$stmt->bind_param('isisi', $id_usuario_origen, $texto, $demora, $clave, $id_usuario_origen);
	$stmt->execute();
	$stmt->close();
}