<?php
// includes/funciones_admin.php
//
// Lógica compartida del panel admin para tres cosas:
//   1) CONFIGURACIÓN  → ajustes clave/valor (tabla admin_config).
//   2) NOTIFICACIONES → feed que se ARMA en el momento a partir de las
//      tablas que ya existen (no hay tabla de avisos: no hace falta
//      tocar ningún procesar_*.php). Qué es "nuevo" lo decide
//      admin.notif_visto_hasta.
//   3) REPORTES       → consultas reales para admin_reportes.php y su
//      exportación a CSV.
//
// Todas las consultas de lectura pasan por admin_consulta(), que
// devuelve [] si algo falla (ej: falta correr sql/actualizar_bd.sql),
// así una tabla faltante nunca rompe una pantalla del panel.

require_once __DIR__ . '/funciones_suscripcion.php';

// Cuántos días hacia atrás mira el feed de notificaciones.
const ADMIN_NOTIF_DIAS = 30;
// Máximo de avisos que arma el feed (por tipo y en total).
const ADMIN_NOTIF_MAX = 60;
// Largo mínimo de contraseña de admin.
const ADMIN_PASS_MIN = 8;

/* =========================================================
   BASE
   ========================================================= */

/**
 * Último error de admin_consulta() (o null). Sirve para que una pantalla
 * distinga "no hay datos" de "la consulta falló" (ej: falta una tabla).
 * Con $reiniciar = true lo borra: llamalo antes de la consulta que querés vigilar.
 */
function admin_ultimo_error($reiniciar = false) {
	return admin_error_estado($reiniciar ? 'reiniciar' : 'leer');
}

function admin_registrar_error($mensaje) {
	admin_error_estado('guardar', $mensaje);
}

function admin_error_estado($modo, $mensaje = null) {
	static $ultimo = null;
	if ($modo === 'guardar')   { $ultimo = $mensaje; }
	if ($modo === 'reiniciar') { $ultimo = null; }
	return $ultimo;
}

/**
 * Nombres reales de las columnas "id" y "fecha" de mensaje_soporte.
 * Según con qué script se creó la base, la tabla puede tener
 * id_mensaje_soporte + fecha (sql/Schema.sql) o id_soporte + fecha_creacion
 * (la que quedó en algunas bases). Se detecta una vez y se usa el nombre que haya.
 */
function admin_soporte_cols($conexion) {
	static $cols = null;
	if ($cols !== null) {
		return $cols;
	}
	$cols = ['id' => 'id_mensaje_soporte', 'fecha' => 'fecha'];
	try {
		$res = $conexion->query('SHOW COLUMNS FROM mensaje_soporte');
		if ($res) {
			$nombres = [];
			foreach ($res->fetch_all(MYSQLI_ASSOC) as $f) {
				$nombres[$f['Field']] = true;
			}
			if (isset($nombres['id_soporte']) && !isset($nombres['id_mensaje_soporte'])) {
				$cols['id'] = 'id_soporte';
			}
			if (isset($nombres['fecha_creacion']) && !isset($nombres['fecha'])) {
				$cols['fecha'] = 'fecha_creacion';
			}
		}
	} catch (Throwable $e) {
		// sin la tabla: quedan los nombres por defecto y la consulta avisa el error
	}
	return $cols;
}

/** SELECT con parámetros (todos como texto). Devuelve filas o [] si falla. */
function admin_consulta($conexion, $sql, $params = []) {
	try {
		$stmt = $conexion->prepare($sql);
		if (!$stmt) {
			// En PHP < 8.1 prepare() devuelve false en vez de lanzar un error:
			// se muestra el motivo real (tabla o columna que falta, etc.)
			throw new RuntimeException('No se pudo preparar la consulta: ' . $conexion->error);
		}
		if ($params) {
			$stmt->bind_param(str_repeat('s', count($params)), ...$params);
		}
		$stmt->execute();
		$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
		return $filas;
	} catch (Throwable $e) {
		error_log('[admin] consulta fallida: ' . $e->getMessage());
		admin_registrar_error($e->getMessage());
		return [];
	}
}

/** Primera columna de la primera fila (para COUNT, SUM, AVG...). */
function admin_valor($conexion, $sql, $params = [], $defecto = 0) {
	$filas = admin_consulta($conexion, $sql, $params);
	if (!$filas) {
		return $defecto;
	}
	$valor = array_values($filas[0])[0] ?? $defecto;
	return $valor === null ? $defecto : $valor;
}

/** true si falta correr sql/actualizar_bd.sql (admin_config o notif_visto_hasta). */
function admin_migracion_pendiente($conexion) {
	try {
		$hay_config = $conexion->query("SHOW TABLES LIKE 'admin_config'")->num_rows > 0;
		$hay_visto  = $conexion->query("SHOW COLUMNS FROM admin LIKE 'notif_visto_hasta'")->num_rows > 0;
		return !($hay_config && $hay_visto);
	} catch (Throwable $e) {
		return true;
	}
}

/* =========================================================
   CSRF (formularios de Configuración y de la campanita)
   ========================================================= */

function admin_csrf_token() {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if (empty($_SESSION['admin_csrf'])) {
		$_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['admin_csrf'];
}

function admin_csrf_campo() {
	return '<input type="hidden" name="csrf" value="' . htmlspecialchars(admin_csrf_token()) . '">';
}

function admin_csrf_valido() {
	$enviado = $_POST['csrf'] ?? '';
	return !empty($_SESSION['admin_csrf']) && is_string($enviado) && hash_equals($_SESSION['admin_csrf'], $enviado);
}

/* =========================================================
   CONFIGURACIÓN (tabla admin_config)
   ========================================================= */

/** Todos los ajustes guardados, como [clave => valor]. */
function admin_config_todo($conexion, $recargar = false) {
	static $cache = null;
	if ($cache !== null && !$recargar) {
		return $cache;
	}
	$cache = [];
	foreach (admin_consulta($conexion, 'SELECT clave, valor FROM admin_config') as $fila) {
		$cache[$fila['clave']] = $fila['valor'];
	}
	return $cache;
}

function admin_config_get($conexion, $clave, $defecto = null) {
	$todo = admin_config_todo($conexion);
	return array_key_exists($clave, $todo) ? $todo[$clave] : $defecto;
}

function admin_config_set($conexion, $clave, $valor) {
	$valor = (string) $valor;
	$stmt = $conexion->prepare(
		'INSERT INTO admin_config (clave, valor) VALUES (?, ?)
		 ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
	);
	$stmt->bind_param('ss', $clave, $valor);
	$stmt->execute();
	$stmt->close();
}

/** Tipos de aviso que puede mostrar la campanita (y se pueden activar/desactivar). */
function admin_tipos_notificacion() {
	return [
		'verificaciones' => [
			'nombre'  => 'Verificaciones',
			'desc'    => 'Técnicos, empresas y cuentas Plus que piden aprobación.',
			'defecto' => 1,
		],
		'soporte' => [
			'nombre'  => 'Mensajes de soporte',
			'desc'    => 'Consultas y reportes de problemas que envían los usuarios (los prioritarios se marcan).',
			'defecto' => 1,
		],
		'pagos' => [
			'nombre'  => 'Pagos y suscripciones',
			'desc'    => 'Cada plan que se activa (Cliente Plus, Técnico Plus, Abono de empresa).',
			'defecto' => 1,
		],
		'registros' => [
			'nombre'  => 'Clientes nuevos',
			'desc'    => 'Cada cliente que se registra. Técnicos y empresas se avisan en Verificaciones.',
			'defecto' => 1,
		],
		'publicaciones' => [
			'nombre'  => 'Publicaciones nuevas',
			'desc'    => 'Cada trabajo que publica un cliente o una empresa. Es el aviso más frecuente.',
			'defecto' => 0,
		],
	];
}

function admin_notif_activa($conexion, $tipo) {
	$tipos   = admin_tipos_notificacion();
	$defecto = $tipos[$tipo]['defecto'] ?? 1;
	return (int) admin_config_get($conexion, 'notif_' . $tipo, $defecto) === 1;
}

/* =========================================================
   NOTIFICACIONES
   ========================================================= */

/** Cuántas cosas esperan una decisión del admin ahora mismo. */
function admin_pendientes($conexion) {
	$tecnicos = (int) admin_valor($conexion,
		"SELECT COUNT(*) FROM tecnico t JOIN usuario u ON u.id_usuario = t.id_usuario
		 WHERE t.estado_verificacion = 'pendiente' AND u.estado_cuenta = 'activa'");
	$empresas = (int) admin_valor($conexion,
		"SELECT COUNT(*) FROM empleador e JOIN usuario u ON u.id_usuario = e.id_usuario
		 WHERE e.verificada = 0 AND u.estado_cuenta = 'activa'");
	$cuentas = (int) admin_valor($conexion,
		"SELECT COUNT(*) FROM verificacion_cuenta v JOIN usuario u ON u.id_usuario = v.id_usuario
		 WHERE v.estado = 'pendiente' AND u.estado_cuenta = 'activa'");

	return [
		'tecnicos' => $tecnicos,
		'empresas' => $empresas,
		'cuentas'  => $cuentas,
		'total'    => $tecnicos + $empresas + $cuentas,
	];
}

/**
 * Arma el feed de avisos (más nuevos primero). Cada aviso:
 *   ['tipo' => clave, 'clave' => id estable del aviso, 'texto' => ..., 'fecha' => 'Y-m-d H:i:s', 'url' => ..., 'prioritario' => bool]
 * Solo consulta los tipos que el admin tiene activados en Configuración.
 */
function admin_notificaciones_feed($conexion, $tipo_filtrado = null, $id_admin = null) {
	$desde = 'DATE_SUB(NOW(), INTERVAL ' . (int) ADMIN_NOTIF_DIAS . ' DAY)';
	$max   = (int) ADMIN_NOTIF_MAX;
	$items = [];

	$activo = function ($tipo) use ($conexion, $tipo_filtrado) {
		if ($tipo_filtrado !== null && $tipo_filtrado !== $tipo) {
			return false;
		}
		return admin_notif_activa($conexion, $tipo);
	};

	// --- Verificaciones ---
	if ($activo('verificaciones')) {
		$filas = admin_consulta($conexion,
			"SELECT t.id_tecnico AS id, u.nombre, t.especialidad, u.fecha_registro AS fecha
			 FROM tecnico t JOIN usuario u ON u.id_usuario = t.id_usuario
			 WHERE t.estado_verificacion = 'pendiente' AND u.estado_cuenta = 'activa' AND u.fecha_registro >= $desde
			 ORDER BY u.fecha_registro DESC LIMIT $max");
		foreach ($filas as $f) {
			$items[] = ['tipo' => 'verificaciones', 'clave' => 'tec:' . $f['id'], 'fecha' => $f['fecha'], 'url' => 'verificaciones.php', 'prioritario' => false,
				'texto' => $f['nombre'] . ' pidió verificación como técnico (' . $f['especialidad'] . ')'];
		}

		$filas = admin_consulta($conexion,
			"SELECT e.id_empleador AS id, e.empresa, u.fecha_registro AS fecha
			 FROM empleador e JOIN usuario u ON u.id_usuario = e.id_usuario
			 WHERE e.verificada = 0 AND u.estado_cuenta = 'activa' AND u.fecha_registro >= $desde
			 ORDER BY u.fecha_registro DESC LIMIT $max");
		foreach ($filas as $f) {
			$items[] = ['tipo' => 'verificaciones', 'clave' => 'emp:' . $f['id'], 'fecha' => $f['fecha'], 'url' => 'verificaciones.php', 'prioritario' => false,
				'texto' => 'La empresa ' . $f['empresa'] . ' espera aprobación'];
		}

		$filas = admin_consulta($conexion,
			"SELECT v.id_usuario AS id, u.nombre, v.fecha_solicitud AS fecha
			 FROM verificacion_cuenta v JOIN usuario u ON u.id_usuario = v.id_usuario
			 WHERE v.estado = 'pendiente' AND u.estado_cuenta = 'activa' AND v.fecha_solicitud >= $desde
			 ORDER BY v.fecha_solicitud DESC LIMIT $max");
		foreach ($filas as $f) {
			$items[] = ['tipo' => 'verificaciones', 'clave' => 'cue:' . $f['id'], 'fecha' => $f['fecha'], 'url' => 'verificaciones.php', 'prioritario' => false,
				'texto' => $f['nombre'] . ' pidió la insignia de cuenta verificada'];
		}
	}

	// --- Soporte ---
	if ($activo('soporte')) {
		$sc    = admin_soporte_cols($conexion);
		$filas = admin_consulta($conexion,
			"SELECT m.{$sc['id']} AS id, m.tipo, m.asunto, m.mensaje, m.{$sc['fecha']} AS fecha, u.nombre
			 FROM mensaje_soporte m JOIN usuario u ON u.id_usuario = m.id_usuario
			 WHERE m.{$sc['fecha']} >= $desde
			 ORDER BY m.{$sc['fecha']} DESC LIMIT $max");
		foreach ($filas as $f) {
			$prioritario = strpos($f['asunto'], '[PRIORITARIO]') === 0;
			$asunto      = trim(preg_replace('/^\[PRIORITARIO\]\s*/', '', $f['asunto']));
			$que         = $f['tipo'] === 'reporte' ? 'Reporte de problema' : 'Mensaje de soporte';
			$items[] = ['tipo' => 'soporte', 'clave' => 'sop:' . (int) $f['id'], 'fecha' => $f['fecha'], 'url' => 'admin_soporte.php#m' . (int) $f['id'], 'prioritario' => $prioritario,
				'texto' => $que . ' de ' . $f['nombre'] . ': ' . mb_strimwidth($asunto, 0, 90, '…'),
				// Primeras líneas del mensaje, para leerlo sin abrir Soporte.
				'detalle' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', $f['mensaje'])), 0, 140, '…')];
		}
	}

	// --- Pagos ---
	if ($activo('pagos')) {
		$planes = planes_conectec();
		$filas  = admin_consulta($conexion,
			"SELECT s.id_suscripcion AS id, u.nombre, s.plan, s.monto, s.fecha_inicio AS fecha
			 FROM suscripcion s JOIN usuario u ON u.id_usuario = s.id_usuario
			 WHERE s.estado = 'activa' AND s.fecha_inicio >= $desde
			 ORDER BY s.fecha_inicio DESC LIMIT $max");
		foreach ($filas as $f) {
			$plan = $planes[$f['plan']]['nombre'] ?? $f['plan'];
			$items[] = ['tipo' => 'pagos', 'clave' => 'pag:' . $f['id'], 'fecha' => $f['fecha'], 'url' => 'admin_reportes.php', 'prioritario' => false,
				'texto' => $f['nombre'] . ' activó ' . $plan . ' (' . formatear_precio($f['monto']) . ')'];
		}
	}

	// --- Clientes nuevos (técnicos y empresas ya avisan en Verificaciones) ---
	if ($activo('registros')) {
		$filas = admin_consulta($conexion,
			"SELECT u.id_usuario AS id, u.nombre, u.fecha_registro AS fecha
			 FROM usuario u
			 LEFT JOIN tecnico t     ON t.id_usuario = u.id_usuario
			 LEFT JOIN empleador emp ON emp.id_usuario = u.id_usuario
			 WHERE t.id_tecnico IS NULL AND emp.id_empleador IS NULL AND u.fecha_registro >= $desde
			 ORDER BY u.fecha_registro DESC LIMIT $max");
		foreach ($filas as $f) {
			$items[] = ['tipo' => 'registros', 'clave' => 'cli:' . $f['id'], 'fecha' => $f['fecha'], 'url' => 'admin_usuarios.php', 'prioritario' => false,
				'texto' => 'Nuevo cliente registrado: ' . $f['nombre']];
		}
	}

	// --- Publicaciones ---
	if ($activo('publicaciones')) {
		$filas = admin_consulta($conexion,
			"SELECT tr.id_trabajo AS id, tr.titulo, tr.fecha_publicacion AS fecha, COALESCE(uc.nombre, ue.nombre) AS por
			 FROM trabajo tr
			 LEFT JOIN trabajo_cliente tc   ON tc.id_trabajo = tr.id_trabajo
			 LEFT JOIN cliente c            ON c.id_cliente = tc.id_cliente
			 LEFT JOIN usuario uc           ON uc.id_usuario = c.id_usuario
			 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
			 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador
			 LEFT JOIN usuario ue           ON ue.id_usuario = emp.id_usuario
			 WHERE tr.fecha_publicacion >= $desde
			 ORDER BY tr.fecha_publicacion DESC LIMIT $max");
		foreach ($filas as $f) {
			$items[] = ['tipo' => 'publicaciones', 'clave' => 'pub:' . $f['id'], 'fecha' => $f['fecha'], 'url' => 'admin_trabajos.php', 'prioritario' => false,
				'texto' => 'Nueva publicación de ' . ($f['por'] ?? 'un usuario') . ': ' . mb_strimwidth($f['titulo'], 0, 90, '…')];
		}
	}

	// Los avisos que este admin borró no vuelven a aparecer
	if ($id_admin !== null) {
		$borradas = admin_notif_claves_borradas($conexion, (int) $id_admin);
		if ($borradas) {
			$items = array_values(array_filter($items, function ($item) use ($borradas) {
				return !isset($borradas[$item['clave'] ?? '']);
			}));
		}
	}

	usort($items, fn($a, $b) => strtotime($b['fecha']) <=> strtotime($a['fecha']));

	return array_slice($items, 0, $max);
}

/**
 * Los avisos del admin se arman desde las tablas de la plataforma (no son
 * filas propias), así que "borrar" uno es recordar su clave estable
 * (ej: 'sop:12', 'pag:3') en admin_notif_borrada, y sacarlo del feed.
 * La tabla se crea sola la primera vez (también está en sql/actualizar_bd.sql).
 */
function admin_notif_asegurar_tabla($conexion) {
	static $hecho = false;
	if ($hecho) {
		return;
	}
	$hecho = true;

	$ok = $conexion->query(
		'CREATE TABLE IF NOT EXISTS admin_notif_borrada (
			id_admin  INT NOT NULL,
			clave     VARCHAR(80) NOT NULL,
			fecha     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id_admin, clave)
		) ENGINE=InnoDB'
	);
	if (!$ok) {
		throw new RuntimeException('No se pudo crear la tabla admin_notif_borrada: ' . $conexion->error);
	}
}

/** Claves de los avisos que este admin ya borró, como [clave => true]. */
function admin_notif_claves_borradas($conexion, $id_admin) {
	$claves = [];
	try {
		admin_notif_asegurar_tabla($conexion);
		$stmt = $conexion->prepare('SELECT clave FROM admin_notif_borrada WHERE id_admin = ?');
		if (!$stmt) {
			return [];
		}
		$stmt->bind_param('i', $id_admin);
		$stmt->execute();
		foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $f) {
			$claves[$f['clave']] = true;
		}
		$stmt->close();
	} catch (Throwable $e) {
		// sin la tabla simplemente no hay avisos borrados: el feed se ve completo
		error_log('[admin] avisos borrados: ' . $e->getMessage());
		return [];
	}
	return $claves;
}

/** Borra un aviso (por su clave). Devuelve true si quedó borrado. */
function admin_notif_borrar($conexion, $id_admin, $clave) {
	$clave = (string) $clave;
	if (!preg_match('/^[a-z]{3}:\d+$/', $clave)) {
		return false;
	}
	admin_notif_asegurar_tabla($conexion);
	$stmt = $conexion->prepare('INSERT IGNORE INTO admin_notif_borrada (id_admin, clave) VALUES (?, ?)');
	if (!$stmt) {
		throw new RuntimeException('No se pudo borrar el aviso: ' . $conexion->error);
	}
	$stmt->bind_param('is', $id_admin, $clave);
	$stmt->execute();
	$stmt->close();
	return true;
}

/** Borra todos los avisos que ya están leídos. Devuelve cuántos. */
function admin_notif_borrar_leidas($conexion, $id_admin) {
	$resumen = admin_notificaciones_resumen($conexion, $id_admin);
	$total   = 0;
	foreach ($resumen['items'] as $item) {
		if (!$item['nueva'] && !empty($item['clave']) && admin_notif_borrar($conexion, $id_admin, $item['clave'])) {
			$total++;
		}
	}
	return $total;
}

/** Fecha hasta la que este admin ya vio los avisos (null = nunca abrió la campanita). */
function admin_notif_visto_hasta($conexion, $id_admin) {
	$filas = admin_consulta($conexion, 'SELECT notif_visto_hasta FROM admin WHERE id_admin = ?', [$id_admin]);
	return $filas[0]['notif_visto_hasta'] ?? null;
}

/** Marca todo como visto (lo guarda con la hora de la base, la misma que usan los avisos). */
function admin_notif_marcar_vistas($conexion, $id_admin) {
	$stmt = $conexion->prepare('UPDATE admin SET notif_visto_hasta = NOW() WHERE id_admin = ?');
	$stmt->bind_param('i', $id_admin);
	$stmt->execute();
	$stmt->close();
}

/**
 * Feed + cuántos son nuevos. Cada aviso suma 'nueva' (bool) y 'hace' (texto).
 * Si el admin nunca abrió la campanita, cuenta como nuevo lo de los
 * últimos 3 días (para no arrancar con decenas de avisos sin leer).
 */
function admin_notificaciones_resumen($conexion, $id_admin, $tipo_filtrado = null) {
	$visto    = admin_notif_visto_hasta($conexion, $id_admin);
	$ts_visto = $visto ? strtotime($visto) : strtotime('-3 days');
	$items    = admin_notificaciones_feed($conexion, $tipo_filtrado, $id_admin);
	$nuevas   = 0;
	$ahora_bd = strtotime((string) admin_valor($conexion, 'SELECT NOW()', [], date('Y-m-d H:i:s')));

	foreach ($items as &$item) {
		$item['hace']  = admin_tiempo_relativo($item['fecha'], $ahora_bd);
		$item['nueva'] = strtotime($item['fecha']) > $ts_visto;
		if ($item['nueva']) {
			$nuevas++;
		}
	}
	unset($item);

	return ['items' => $items, 'no_leidas' => $nuevas];
}

/**
 * "hace 5 min", "ayer", "12/09/2026". $ahora es la hora ACTUAL según la
 * base (las fechas de los avisos vienen de MySQL: comparar contra la
 * hora de PHP corre el "hace X" si los dos relojes tienen distinta zona).
 */
function admin_tiempo_relativo($fecha, $ahora = null) {
	$ahora = $ahora ?? time();
	$dif   = $ahora - strtotime($fecha);
	if ($dif < 60)        return 'ahora';
	if ($dif < 3600)      return 'hace ' . floor($dif / 60) . ' min';
	if ($dif < 86400)     return 'hace ' . floor($dif / 3600) . ' h';
	if ($dif < 172800)    return 'ayer';
	if ($dif < 86400 * 30) return 'hace ' . floor($dif / 86400) . ' días';
	return date('d/m/Y', strtotime($fecha));
}

/* =========================================================
   REPORTES
   ========================================================= */

function admin_reporte_rangos() {
	return [
		7   => 'Últimos 7 días',
		30  => 'Últimos 30 días',
		90  => 'Últimos 90 días',
		365 => 'Último año',
		0   => 'Todo el historial',
	];
}

/** Rango pedido en ?dias= (si es válido), si no el guardado en Configuración, si no 30. */
function admin_reporte_dias($conexion, $pedido) {
	$rangos = admin_reporte_rangos();

	if ($pedido !== null && is_numeric($pedido) && isset($rangos[(int) $pedido])) {
		return (int) $pedido;
	}

	$guardado = admin_config_get($conexion, 'reportes_dias_defecto', 30);
	return isset($rangos[(int) $guardado]) ? (int) $guardado : 30;
}

/** Todos los números de Reportes para un rango de días (0 = todo el historial). */
function admin_reporte_datos($conexion, $dias) {
	$dias  = (int) $dias;
	$d     = $dias > 0 ? $dias : 36500;
	$desde = "DATE_SUB(NOW(), INTERVAL {$d} DAY)";
	$r     = ['dias' => $dias];

	// Usuarios
	$r['usuarios_nuevos'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM usuario WHERE fecha_registro >= $desde");
	$r['usuarios_activos'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM usuario WHERE estado_cuenta = 'activa'");
	$r['clientes'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM cliente c JOIN usuario u ON u.id_usuario = c.id_usuario WHERE u.estado_cuenta = 'activa'");
	$r['tecnicos'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM tecnico t JOIN usuario u ON u.id_usuario = t.id_usuario WHERE u.estado_cuenta = 'activa'");
	$r['empresas'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM empleador e JOIN usuario u ON u.id_usuario = e.id_usuario WHERE u.estado_cuenta = 'activa'");

	// Publicaciones
	$r['trabajos_nuevos'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM trabajo WHERE fecha_publicacion >= $desde");
	$r['trabajos_estado'] = ['abierto' => 0, 'en_progreso' => 0, 'cerrado' => 0];
	foreach (admin_consulta($conexion, "SELECT estado, COUNT(*) AS c FROM trabajo WHERE fecha_publicacion >= $desde GROUP BY estado") as $f) {
		$r['trabajos_estado'][$f['estado']] = (int) $f['c'];
	}
	$r['trabajos_origen'] = ['cliente' => 0, 'empresa' => 0];
	foreach (admin_consulta($conexion,
		"SELECT CASE WHEN te.id_trabajo IS NOT NULL THEN 'empresa' ELSE 'cliente' END AS origen, COUNT(*) AS c
		 FROM trabajo tr LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
		 WHERE tr.fecha_publicacion >= $desde GROUP BY origen") as $f) {
		$r['trabajos_origen'][$f['origen']] = (int) $f['c'];
	}
	$r['categorias'] = admin_consulta($conexion,
		"SELECT categoria, COUNT(*) AS c FROM trabajo WHERE fecha_publicacion >= $desde
		 GROUP BY categoria ORDER BY c DESC, categoria ASC LIMIT 6");

	// Postulaciones
	$r['postulaciones_estado'] = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0];
	foreach (admin_consulta($conexion, "SELECT estado, COUNT(*) AS c FROM postulacion WHERE fecha_postulacion >= $desde GROUP BY estado") as $f) {
		$r['postulaciones_estado'][$f['estado']] = (int) $f['c'];
	}
	$r['postulaciones'] = array_sum($r['postulaciones_estado']);
	$r['tasa_aceptacion'] = $r['postulaciones'] > 0
		? round($r['postulaciones_estado']['aceptada'] * 100 / $r['postulaciones'], 1)
		: null;

	// Calificaciones
	$cal = admin_consulta($conexion, "SELECT AVG(puntaje) AS prom, COUNT(*) AS c FROM calificacion WHERE fecha >= $desde");
	$r['calificacion_prom'] = ($cal && (int) $cal[0]['c'] > 0) ? round((float) $cal[0]['prom'], 1) : null;
	$r['calificaciones']    = $cal ? (int) $cal[0]['c'] : 0;

	// Planes e ingresos (pagos = suscripciones que llegaron a activarse)
	$r['suscripciones_activas'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM suscripcion WHERE estado = 'activa' AND fecha_vencimiento > NOW()");
	$r['ingresos'] = (float) admin_valor($conexion,
		"SELECT COALESCE(SUM(monto), 0) FROM suscripcion WHERE estado IN ('activa','vencida') AND fecha_inicio >= $desde");
	$r['ingresos_plan'] = admin_consulta($conexion,
		"SELECT plan, COUNT(*) AS c, COALESCE(SUM(monto), 0) AS total FROM suscripcion
		 WHERE estado IN ('activa','vencida') AND fecha_inicio >= $desde
		 GROUP BY plan ORDER BY total DESC");

	// Soporte
	$r['soporte_contacto'] = 0;
	$r['soporte_reporte']  = 0;
	$sc_fecha = admin_soporte_cols($conexion)['fecha'];
	foreach (admin_consulta($conexion, "SELECT tipo, COUNT(*) AS c FROM mensaje_soporte WHERE $sc_fecha >= $desde GROUP BY tipo") as $f) {
		$r['soporte_' . $f['tipo']] = (int) $f['c'];
	}
	$r['soporte'] = $r['soporte_contacto'] + $r['soporte_reporte'];
	$r['soporte_prioritarios'] = (int) admin_valor($conexion, "SELECT COUNT(*) FROM mensaje_soporte WHERE $sc_fecha >= $desde AND asunto LIKE '[PRIORITARIO]%'");

	// Pendientes de verificación (es el estado de ahora, no depende del rango)
	$r['pendientes'] = admin_pendientes($conexion);

	// Últimos 6 meses (no depende del rango): registros y publicaciones por mes
	$abrev  = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$meses  = [];
	for ($i = 5; $i >= 0; $i--) {
		$ts = strtotime(date('Y-m-01') . " -{$i} months");
		$meses[date('Y-m', $ts)] = ['etiqueta' => $abrev[(int) date('n', $ts) - 1], 'usuarios' => 0, 'trabajos' => 0];
	}
	$primero = array_key_first($meses) . '-01';
	foreach (admin_consulta($conexion, "SELECT DATE_FORMAT(fecha_registro, '%Y-%m') AS ym, COUNT(*) AS c FROM usuario WHERE fecha_registro >= ? GROUP BY ym", [$primero]) as $f) {
		if (isset($meses[$f['ym']])) $meses[$f['ym']]['usuarios'] = (int) $f['c'];
	}
	foreach (admin_consulta($conexion, "SELECT DATE_FORMAT(fecha_publicacion, '%Y-%m') AS ym, COUNT(*) AS c FROM trabajo WHERE fecha_publicacion >= ? GROUP BY ym", [$primero]) as $f) {
		if (isset($meses[$f['ym']])) $meses[$f['ym']]['trabajos'] = (int) $f['c'];
	}
	$r['meses'] = array_values($meses);

	return $r;
}

/** Barras horizontales. $filas: [['etiqueta' => ..., 'valor' => n, 'texto' => opcional], ...] */
function admin_barras_h($filas) {
	$max = 0;
	foreach ($filas as $f) {
		$max = max($max, (float) $f['valor']);
	}
	if ($max <= 0) {
		return '<p class="admin-vacio admin-vacio--chico">Sin datos en este período.</p>';
	}

	$html = '<div class="admin-hbars">';
	foreach ($filas as $f) {
		$ancho = round((float) $f['valor'] * 100 / $max, 1);
		$texto = $f['texto'] ?? number_format((float) $f['valor'], 0, ',', '.');
		$html .= '<div class="admin-hbar">'
			. '<span class="admin-hbar-etiqueta">' . htmlspecialchars($f['etiqueta']) . '</span>'
			. '<span class="admin-hbar-pista"><span class="admin-hbar-relleno" style="width:' . $ancho . '%;"></span></span>'
			. '<span class="admin-hbar-valor">' . htmlspecialchars($texto) . '</span>'
			. '</div>';
	}
	return $html . '</div>';
}

/** Barras verticales por mes. $meses: [['etiqueta' => 'sep', 'usuarios' => n, ...]]; $clave: cuál columna graficar. */
function admin_barras_v($meses, $clave) {
	$max = 0;
	foreach ($meses as $m) {
		$max = max($max, (int) $m[$clave]);
	}
	if ($max <= 0) {
		return '<p class="admin-vacio admin-vacio--chico">Todavía no hay datos en estos meses.</p>';
	}

	$html = '<div class="admin-vchart">';
	foreach ($meses as $m) {
		$alto = round((int) $m[$clave] * 100 / $max);
		$alto = max($alto, (int) $m[$clave] > 0 ? 3 : 0);   // que un valor chico igual se vea
		$html .= '<div class="admin-vchart-col">'
			. '<span class="admin-vchart-valor">' . (int) $m[$clave] . '</span>'
			. '<span class="admin-vchart-pista"><span class="admin-vchart-barra" style="height:' . $alto . '%;"></span></span>'
			. '<span class="admin-vchart-etiqueta">' . htmlspecialchars($m['etiqueta']) . '</span>'
			. '</div>';
	}
	return $html . '</div>';
}

/** Evita que Excel interprete como fórmula un texto que empieza con = + - @ (campos que escriben los usuarios). */
function admin_csv_seguro($valor) {
	if (is_string($valor) && $valor !== '' && strpbrk($valor[0], "=+-@\t\r") !== false) {
		return "'" . $valor;
	}
	return $valor;
}
