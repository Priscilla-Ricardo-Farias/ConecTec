<?php
// procesar_admin_configuracion.php
//
// Recibe los formularios de admin_configuracion.php:
//   guardar_notificaciones → qué avisos muestra la campanita
//   guardar_reportes       → período por defecto de Reportes
//   cambiar_contrasena     → contraseña del admin logueado
//   crear_admin            → suma una cuenta de admin
//   eliminar_admin         → quita otra cuenta de admin
//
// Todas son POST con token CSRF. Siempre vuelve a la pantalla de
// Configuración con ?ok=... o ?err=... (ver los mensajes allá).

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';

/** Vuelve a Configuración con un aviso y corta la ejecución. */
function volver_config($tipo, $codigo, $ancla) {
	header('Location: admin_configuracion.php?' . $tipo . '=' . $codigo . '#' . $ancla);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valido()) {
	volver_config('err', 'csrf', '');
}

$accion = $_POST['accion'] ?? '';
$mi_id  = (int) $_SESSION['admin_id'];

try {

	switch ($accion) {

		case 'guardar_notificaciones':
			if (admin_migracion_pendiente($conexion)) {
				volver_config('err', 'migracion', 'notificaciones');
			}
			$marcados = $_POST['notif'] ?? [];
			if (!is_array($marcados)) {
				$marcados = [];
			}
			foreach (array_keys(admin_tipos_notificacion()) as $tipo) {
				admin_config_set($conexion, 'notif_' . $tipo, isset($marcados[$tipo]) ? '1' : '0');
			}
			volver_config('ok', 'notif', 'notificaciones');

		case 'guardar_reportes':
			if (admin_migracion_pendiente($conexion)) {
				volver_config('err', 'migracion', 'reportes');
			}
			$dias = $_POST['dias'] ?? '';
			if (!is_numeric($dias) || !isset(admin_reporte_rangos()[(int) $dias])) {
				volver_config('err', 'rango', 'reportes');
			}
			admin_config_set($conexion, 'reportes_dias_defecto', (string) (int) $dias);
			volver_config('ok', 'reportes', 'reportes');

		case 'cambiar_contrasena':
			$actual    = (string) ($_POST['clave_actual'] ?? '');
			$nueva     = (string) ($_POST['clave_nueva'] ?? '');
			$confirmar = (string) ($_POST['clave_confirmar'] ?? '');

			$stmt = $conexion->prepare('SELECT contrasena FROM admin WHERE id_admin = ?');
			$stmt->bind_param('i', $mi_id);
			$stmt->execute();
			$fila = $stmt->get_result()->fetch_assoc();
			$stmt->close();

			if (!$fila || !password_verify($actual, $fila['contrasena'])) {
				volver_config('err', 'clave_actual', 'mi-cuenta');
			}
			if (mb_strlen($nueva) < ADMIN_PASS_MIN) {
				volver_config('err', 'clave_corta', 'mi-cuenta');
			}
			if ($nueva !== $confirmar) {
				volver_config('err', 'clave_distinta', 'mi-cuenta');
			}
			if ($nueva === $actual) {
				volver_config('err', 'clave_igual', 'mi-cuenta');
			}

			$hash = password_hash($nueva, PASSWORD_DEFAULT);
			$stmt = $conexion->prepare('UPDATE admin SET contrasena = ? WHERE id_admin = ?');
			$stmt->bind_param('si', $hash, $mi_id);
			$stmt->execute();
			$stmt->close();

			session_regenerate_id(true);
			volver_config('ok', 'clave', 'mi-cuenta');

		case 'crear_admin':
			$usuario = trim($_POST['nuevo_usuario'] ?? '');
			$clave   = (string) ($_POST['nueva_clave'] ?? '');

			if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)) {
				volver_config('err', 'usuario_formato', 'administradores');
			}
			if (mb_strlen($clave) < ADMIN_PASS_MIN) {
				volver_config('err', 'clave_corta', 'administradores');
			}

			$stmt = $conexion->prepare('SELECT 1 FROM admin WHERE usuario = ?');
			$stmt->bind_param('s', $usuario);
			$stmt->execute();
			$existe = $stmt->get_result()->num_rows > 0;
			$stmt->close();
			if ($existe) {
				volver_config('err', 'usuario_existe', 'administradores');
			}

			$hash = password_hash($clave, PASSWORD_DEFAULT);
			$stmt = $conexion->prepare('INSERT INTO admin (usuario, contrasena) VALUES (?, ?)');
			$stmt->bind_param('ss', $usuario, $hash);
			$stmt->execute();
			$stmt->close();
			volver_config('ok', 'creado', 'administradores');

		case 'eliminar_admin':
			$id = (int) ($_POST['id_admin'] ?? 0);

			if ($id === $mi_id) {
				volver_config('err', 'borrar_yo', 'administradores');
			}
			if ((int) $conexion->query('SELECT COUNT(*) FROM admin')->fetch_row()[0] <= 1) {
				volver_config('err', 'borrar_ultimo', 'administradores');
			}

			$stmt = $conexion->prepare('DELETE FROM admin WHERE id_admin = ?');
			$stmt->bind_param('i', $id);
			$stmt->execute();
			$borrado = $stmt->affected_rows > 0;
			$stmt->close();
			volver_config($borrado ? 'ok' : 'err', $borrado ? 'borrado' : 'borrar_no', 'administradores');

		default:
			volver_config('err', 'csrf', '');
	}

} catch (mysqli_sql_exception $e) {
	error_log('[admin] configuración: ' . $e->getMessage());
	volver_config('err', 'bd', '');
}
