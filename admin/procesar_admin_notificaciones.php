<?php
// procesar_admin_notificaciones.php
//
// Acciones de las notificaciones del admin (campanita y pantalla Notificaciones):
//   - marcar_leidas  → guarda en admin.notif_visto_hasta el momento actual:
//                      los avisos pasan a la sección "Leídas".
//   - borrar         → borra un aviso (clave=...).
//   - borrar_leidas  → borra todos los avisos que ya están leídos.
// Los avisos se arman desde las tablas de la plataforma, así que "borrar"
// solo los oculta para este admin (ver admin_notif_borrar). Los datos
// originales (pagos, soporte, usuarios) no se tocan.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';

// Vuelve a la pantalla desde donde se apretó el botón, pero solo si es
// una página de esta carpeta (nada de redirigir a cualquier lado).
$destino = basename((string) ($_POST['volver_a'] ?? ''));
if (!preg_match('/^[a-z_]+\.php$/', $destino) || !is_file(__DIR__ . '/' . $destino)) {
	$destino = 'admin_notificaciones.php';
}

$accion = $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valido() || !in_array($accion, ['marcar_leidas', 'borrar', 'borrar_leidas'], true)) {
	header('Location: ' . $destino);
	exit;
}

if ($accion === 'borrar' || $accion === 'borrar_leidas') {
	try {
		if ($accion === 'borrar') {
			admin_notif_borrar($conexion, (int) $_SESSION['admin_id'], $_POST['clave'] ?? '');
		} else {
			admin_notif_borrar_leidas($conexion, (int) $_SESSION['admin_id']);
		}
	} catch (Throwable $e) {
		error_log('[admin] no se pudo borrar notificaciones: ' . $e->getMessage());
		header('Location: admin_notificaciones.php?err=bd');
		exit;
	}
	header('Location: ' . $destino . ($destino === 'admin_notificaciones.php' ? '?borrado=1' : ''));
	exit;
}

if (admin_migracion_pendiente($conexion)) {
	header('Location: admin_notificaciones.php?err=migracion');
	exit;
}

try {
	admin_notif_marcar_vistas($conexion, (int) $_SESSION['admin_id']);
} catch (mysqli_sql_exception $e) {
	error_log('[admin] no se pudo marcar como leídas: ' . $e->getMessage());
	header('Location: admin_notificaciones.php?err=bd');
	exit;
}

header('Location: ' . $destino . ($destino === 'admin_notificaciones.php' ? '?actualizado=1' : ''));
exit;
