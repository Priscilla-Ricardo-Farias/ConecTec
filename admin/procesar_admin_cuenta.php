<?php
// procesar_admin_cuenta.php
//
// El admin aprueba o rechaza la solicitud de "cuenta verificada" de un
// cliente o técnico que pagó Plus (tabla verificacion_cuenta). Al
// aprobar, la insignia aparece junto al nombre mientras el plan esté
// activo. Se le avisa al usuario con una notificación.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$id_usuario = (int) ($_POST['id_usuario'] ?? 0);
$accion     = $_POST['accion'] ?? '';

$acciones_validas = [
	'aprobar'  => 'aprobada',
	'rechazar' => 'rechazada',
];

if ($id_usuario <= 0 || !isset($acciones_validas[$accion])) {
	header('Location: verificaciones.php');
	exit;
}

$nuevo_estado = $acciones_validas[$accion];

// Solo cambia solicitudes que siguen pendientes (evita reprocesar).
$stmt = $conexion->prepare(
	"UPDATE verificacion_cuenta
	 SET estado = ?, fecha_resolucion = NOW()
	 WHERE id_usuario = ? AND estado = 'pendiente'"
);
$stmt->bind_param('si', $nuevo_estado, $id_usuario);
$stmt->execute();
$cambio = $stmt->affected_rows > 0;
$stmt->close();

if ($cambio) {
	$texto = $nuevo_estado === 'aprobada'
		? '¡Tu cuenta fue verificada! Ya se muestra la insignia junto a tu nombre.'
		: 'No pudimos verificar tu cuenta. Escribinos desde Contactar soporte si tenés dudas.';

	$stmt = $conexion->prepare(
		"INSERT INTO notificacion (id_usuario, id_usuario_origen, tipo, texto)
		 VALUES (?, NULL, 'mensaje', ?)"
	);
	$stmt->bind_param('is', $id_usuario, $texto);
	$stmt->execute();
	$stmt->close();
}

$destino = $_POST['volver_a'] ?? 'verificaciones.php';
$destino = in_array($destino, ['verificaciones.php', 'panel_admin.php'], true) ? $destino : 'verificaciones.php';

header('Location: ' . $destino . '?actualizado=1');
exit;