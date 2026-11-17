<?php
// procesar_aceptar_postulacion.php
//
// Recibe el botón "Aceptar" que aparece junto a cada postulante en
// feed.php (solo lo ve el dueño del trabajo). Al aceptar una
// postulación: esa pasa a "aceptada", todas las demás postulaciones
// de ese mismo trabajo pasan a "rechazada", y el trabajo pasa a
// "en_progreso".
//
// Si el trabajo es de una EMPRESA (no tiene fecha_trabajo todavía,
// porque publicar.php no se la pide), la fecha se manda en este mismo
// formulario y se guarda recién ahora.
//
// Lo mismo pasa con un trabajo de CLIENTE que se publicó SIN fecha límite
// (es opcional): la fecha se acuerda al aceptar al técnico y se guarda acá.

session_start();
require __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../includes/funciones_suscripcion.php';
require_once __DIR__ . '/../includes/funciones_notificaciones.php';

// Las empresas sin abono activo no pueden aceptar postulantes ni agendar reuniones.
exigir_abono_empresa($conexion, '../');

$id_postulacion = (int) ($_POST['id_postulacion'] ?? 0);

if ($id_postulacion <= 0) {
	header('Location: ../feed.php');
	exit;
}

/* =========================================================
   TRAER LA POSTULACIÓN + EL TRABAJO + QUIÉN LO PUBLICÓ
   Se hace todo en una sola consulta para poder validar de una que
   quien aprieta "Aceptar" sea realmente el dueño de la publicación.
   ========================================================= */
$stmt = $conexion->prepare(
	"SELECT p.id_trabajo, p.id_tecnico, p.estado AS estado_postulacion,
	        tr.estado AS estado_trabajo, tr.fecha_trabajo,
	        uc.id_usuario AS id_usuario_cliente,
	        ue.id_usuario AS id_usuario_empleador,
	        ut.id_usuario AS id_usuario_tecnico,
	        ut.nombre AS nombre_tecnico,
	        tr.titulo AS titulo_trabajo
	 FROM postulacion p
	 JOIN trabajo tr ON tr.id_trabajo = p.id_trabajo

	 LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
	 LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente
	 LEFT JOIN usuario uc         ON uc.id_usuario = c.id_usuario

	 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
	 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador
	 LEFT JOIN usuario ue           ON ue.id_usuario = emp.id_usuario

	 JOIN tecnico t   ON t.id_tecnico = p.id_tecnico
	 JOIN usuario ut  ON ut.id_usuario = t.id_usuario

	 WHERE p.id_postulacion = ?"
);
$stmt->bind_param('i', $id_postulacion);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila) {
	header('Location: ../feed.php');
	exit;
}

// Solo el dueño de la publicación (cliente o empleador que la creó)
// puede aceptar postulantes.
$es_dueno =
	(int) $_SESSION['usuario_id'] === (int) ($fila['id_usuario_cliente'] ?? 0) ||
	(int) $_SESSION['usuario_id'] === (int) ($fila['id_usuario_empleador'] ?? 0);

if (!$es_dueno) {
	header('Location: ../feed.php');
	exit;
}

// El trabajo tiene que seguir abierto y la postulación tiene que
// seguir pendiente (evita doble aceptación si se manda el form dos veces).
if ($fila['estado_trabajo'] !== 'abierto' || $fila['estado_postulacion'] !== 'pendiente') {
	header('Location: ../feed.php');
	exit;
}

$id_trabajo           = $fila['id_trabajo'];
$id_tecnico_aceptado  = $fila['id_tecnico'];
$es_de_empresa        = $fila['id_usuario_empleador'] !== null;

// Datos que van a terminar en evento_agenda: para cliente es el
// trabajo en sí (ya tiene fecha, sin hora puntual); para empresa es
// una REUNIÓN con el postulante, y acá se define fecha y hora.
$fecha_evento       = $fila['fecha_trabajo'];
$hora_inicio_evento = null;

if ($es_de_empresa) {
	// Una empresa no da "changuitas" con fecha fija: lo que hace acá
	// es agendar una reunión (entrevista) con el postulante elegido.
	$fecha_evento       = trim($_POST['fecha_reunion'] ?? '');
	$hora_inicio_evento = trim($_POST['hora_reunion'] ?? '');

	if ($fecha_evento === '' || $hora_inicio_evento === '') {
		header('Location: ../feed.php?error=falta_fecha_reunion');
		exit;
	}
}

// Trabajo de cliente sin fecha límite: hace falta la fecha acordada para poder agendarlo.
$fecha_acordada_ahora = false;

if (!$es_de_empresa && empty($fecha_evento)) {
	$fecha_evento = trim($_POST['fecha_trabajo'] ?? '');
	$f_obj        = DateTime::createFromFormat('Y-m-d', $fecha_evento);

	if (!$f_obj || $f_obj->format('Y-m-d') !== $fecha_evento) {
		header('Location: ../feed.php?error=falta_fecha_trabajo');
		exit;
	}
	$fecha_acordada_ahora = true;
}

$conexion->begin_transaction();

try {
	// Acepta esta postulación
	$stmt = $conexion->prepare("UPDATE postulacion SET estado = 'aceptada' WHERE id_postulacion = ?");
	$stmt->bind_param('i', $id_postulacion);
	$stmt->execute();
	$stmt->close();

	// Guarda a quiénes se les va a rechazar la postulación, para avisarles
	// después (una vez rechazadas ya no se las puede distinguir).
	$stmt = $conexion->prepare(
		"SELECT t.id_usuario
		 FROM postulacion p
		 JOIN tecnico t ON t.id_tecnico = p.id_tecnico
		 WHERE p.id_trabajo = ? AND p.id_postulacion <> ? AND p.estado = 'pendiente'"
	);
	$stmt->bind_param('ii', $id_trabajo, $id_postulacion);
	$stmt->execute();
	$usuarios_rechazados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_usuario');
	$stmt->close();

	// Rechaza a todos los demás postulantes del mismo trabajo
	$stmt = $conexion->prepare("UPDATE postulacion SET estado = 'rechazada' WHERE id_trabajo = ? AND id_postulacion <> ?");
	$stmt->bind_param('ii', $id_trabajo, $id_postulacion);
	$stmt->execute();
	$stmt->close();

	// Marca el trabajo como en progreso (empresa: guarda además la
	// fecha de la reunión, para mantenerlo consistente con trabajo.fecha_trabajo;
	// cliente sin fecha límite: guarda la fecha que se acordó ahora)
	if ($es_de_empresa || $fecha_acordada_ahora) {
		$stmt = $conexion->prepare("UPDATE trabajo SET estado = 'en_progreso', fecha_trabajo = ? WHERE id_trabajo = ?");
		$stmt->bind_param('si', $fecha_evento, $id_trabajo);
	} else {
		$stmt = $conexion->prepare("UPDATE trabajo SET estado = 'en_progreso' WHERE id_trabajo = ?");
		$stmt->bind_param('i', $id_trabajo);
	}
	$stmt->execute();
	$stmt->close();

	// Crea el evento en la agenda del técnico: "reunion" si es de
	// empresa (con hora), "trabajo" si es de cliente (solo fecha).
	$tipo_evento = $es_de_empresa ? 'reunion' : 'trabajo';
	$stmt = $conexion->prepare(
		"INSERT INTO evento_agenda (id_tecnico, id_trabajo, tipo, fecha, hora_inicio)
		 VALUES (?, ?, ?, ?, ?)"
	);
	$stmt->bind_param('iisss', $id_tecnico_aceptado, $id_trabajo, $tipo_evento, $fecha_evento, $hora_inicio_evento);
	$stmt->execute();
	$stmt->close();

	$conexion->commit();

} catch (mysqli_sql_exception $e) {
	$conexion->rollback();
	header('Location: ../feed.php?error=error_servidor');
	exit;
}

/* =========================================================
   AVISOS (ya con todo guardado: si algo falla acá, la aceptación
   no se deshace)
   ========================================================= */
$id_usuario_dueno = (int) ($fila['id_usuario_cliente'] ?? $fila['id_usuario_empleador']);

// Al técnico aceptado: "te aceptaron"
notificar_cambio_postulacion($conexion, (int) $fila['id_usuario_tecnico'], $id_usuario_dueno, $fila['titulo_trabajo'], true);

// A los demás postulantes: "eligieron a otro técnico"
foreach ($usuarios_rechazados as $id_usuario_rechazado) {
	notificar_cambio_postulacion($conexion, (int) $id_usuario_rechazado, $id_usuario_dueno, $fila['titulo_trabajo'], false);
}

// Al dueño de la publicación (cliente o empresa): ya puede escribirle
// al postulante que acaba de aceptar.
crear_notificacion($conexion, $id_usuario_dueno, (int) $fila['id_usuario_tecnico'], 'mensaje',
	'Enviar mensaje a ' . $fila['nombre_tecnico'], 'noti_mensajes');

header('Location: ../feed.php?aceptado=1');
exit;