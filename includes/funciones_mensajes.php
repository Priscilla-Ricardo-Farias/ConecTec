<?php
// includes/funciones_mensajes.php
//
// Funciones compartidas por mensajes.php, chat.php, procesar_mensaje.php
// y por cualquier página que necesite ofrecer un botón de "Enviar
// mensaje" (perfil.php, feed.php).
//
// Reglas de quién puede escribirle a quién:
//   - tecnico  <-> cliente   permitido
//   - tecnico  <-> empresa   permitido
//   - tecnico  <-> tecnico   permitido
//   - cliente  <-> empresa   NO permitido
//   - cliente  <-> cliente   permitido
//   - empresa  <-> empresa   permitido

require_once __DIR__ . '/funciones_suscripcion.php';

/**
 * Devuelve los datos básicos de un usuario más su tipo de cuenta
 * ('tecnico', 'empresa' o 'cliente'), igual que se calcula en
 * procesar_login.php. Devuelve null si el id_usuario no existe.
 */
function datos_usuario_para_mensajes($conexion, $id_usuario) {
	$stmt = $conexion->prepare('SELECT id_usuario, nombre, apellido, foto_perfil, ' . sql_insignia('usuario.id_usuario') . ' AS verificado FROM usuario WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$usuario = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$usuario) {
		return null;
	}

	$stmt = $conexion->prepare('SELECT id_tecnico FROM tecnico WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$es_tecnico = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($es_tecnico) {
		$usuario['tipo']    = 'tecnico';
		$usuario['empresa'] = null;
		return $usuario;
	}

	$stmt = $conexion->prepare('SELECT empresa FROM empleador WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$es_empleador = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($es_empleador) {
		$usuario['tipo']    = 'empresa';
		$usuario['empresa'] = $es_empleador['empresa'];
		return $usuario;
	}

	$usuario['tipo']    = 'cliente';
	$usuario['empresa'] = null;
	return $usuario;
}

/**
 * La única combinación que no puede mandarse mensajes es
 * cliente <-> empresa. Todo el resto (incluido el mismo tipo entre
 * sí) está permitido.
 */
function pueden_conversar($tipo_a, $tipo_b) {
	$par = [$tipo_a, $tipo_b];
	sort($par);
	return $par !== ['cliente', 'empresa'];
}

/**
 * Busca la conversación 1 a 1 ya existente entre dos usuarios. Si no
 * existe, la crea (fila en "conversacion" + una fila en
 * "usuario_conversacion" por cada participante). Devuelve el
 * id_conversacion.
 */
function conversacion_entre($conexion, $id_usuario_a, $id_usuario_b) {
	$stmt = $conexion->prepare(
		'SELECT uc1.id_conversacion
		 FROM usuario_conversacion uc1
		 JOIN usuario_conversacion uc2 ON uc2.id_conversacion = uc1.id_conversacion AND uc2.id_usuario = ?
		 WHERE uc1.id_usuario = ?
		 LIMIT 1'
	);
	$stmt->bind_param('ii', $id_usuario_b, $id_usuario_a);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($fila) {
		return (int) $fila['id_conversacion'];
	}

	$conexion->query('INSERT INTO conversacion () VALUES ()');
	$id_conversacion = $conexion->insert_id;

	$stmt = $conexion->prepare('INSERT INTO usuario_conversacion (id_usuario, id_conversacion) VALUES (?, ?), (?, ?)');
	$stmt->bind_param('iiii', $id_usuario_a, $id_conversacion, $id_usuario_b, $id_conversacion);
	$stmt->execute();
	$stmt->close();

	return $id_conversacion;
}

/**
 * ¿El usuario logueado ($id_usuario) participa de esa conversación?
 * Se usa para no dejar entrar a chatear en una conversación ajena
 * con solo cambiar el id en la URL.
 */
function pertenece_a_conversacion($conexion, $id_conversacion, $id_usuario) {
	$stmt = $conexion->prepare('SELECT 1 FROM usuario_conversacion WHERE id_conversacion = ? AND id_usuario = ?');
	$stmt->bind_param('ii', $id_conversacion, $id_usuario);
	$stmt->execute();
	$existe = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (bool) $existe;
}