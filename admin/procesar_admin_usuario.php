<?php
// procesar_admin_usuario.php
//
// Recibe las acciones de admin_usuarios.php, admin_tecnicos.php y
// admin_empresas.php (suspender, reactivar, eliminar). Solo cambia
// usuario.estado_cuenta — nunca borra la fila, para no perder el
// historial de publicaciones, postulaciones y calificaciones
// asociadas a ese usuario.
//
// Antes de escribir se comprueba que la columna estado_cuenta admita
// el valor nuevo. Si la base se creó con el Schema.sql viejo, el ENUM
// solo tenía ('activa','eliminada'): MySQL rechazaba "suspendida" (o,
// en modo no estricto, la guardaba como texto vacío) y el panel no
// avisaba nada. Ahora, si falta actualizar la base, se muestra un
// aviso en pantalla en vez de fallar en silencio (ver
// sql/actualizar_bd.sql).

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$id_usuario = (int) ($_POST['id_usuario'] ?? 0);
$accion     = $_POST['accion'] ?? '';

$acciones_validas = [
	'suspender' => 'suspendida',
	'reactivar' => 'activa',
	'eliminar'  => 'eliminada',
];

$destino = $_POST['volver_a'] ?? 'admin_usuarios.php';
$destino = in_array($destino, ['admin_usuarios.php', 'admin_tecnicos.php', 'admin_empresas.php'], true) ? $destino : 'admin_usuarios.php';

if ($id_usuario <= 0 || !isset($acciones_validas[$accion])) {
	header('Location: ' . $destino);
	exit;
}

$nuevo_estado = $acciones_validas[$accion];

try {
	// ¿La columna admite este estado? (lee la definición del ENUM)
	$columna = $conexion->query("SHOW COLUMNS FROM usuario LIKE 'estado_cuenta'")->fetch_assoc();
	preg_match_all("/'([^']+)'/", $columna['Type'] ?? '', $coincidencias);

	if (!in_array($nuevo_estado, $coincidencias[1], true)) {
		header('Location: ' . $destino . '?error=estado_no_soportado');
		exit;
	}

	$stmt = $conexion->prepare('UPDATE usuario SET estado_cuenta = ? WHERE id_usuario = ?');
	$stmt->bind_param('si', $nuevo_estado, $id_usuario);
	$stmt->execute();
	$stmt->close();
} catch (mysqli_sql_exception $e) {
	header('Location: ' . $destino . '?error=bd');
	exit;
}

header('Location: ' . $destino . '?actualizado=1');
exit;
