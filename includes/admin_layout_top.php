<?php
$raiz = $raiz ?? '';   // prefijo hacia la raíz del proyecto ('' en la raíz, '../' en admin/)
// includes/admin_layout_top.php
//
// Reemplaza a includes/header.php + includes/admin_nav.php en todas
// las pantallas de admin. Cada página, antes de incluir esto, define:
//   $page_title     -> pestaña del navegador
//   $admin_active   -> clave de la sección activa en el sidebar
//   $admin_titulo   -> título grande de la pantalla (topbar)
//   $admin_subtitulo (opcional) -> bajada debajo del título
//   $extra_css      (opcional) -> hojas de estilo extra además de admin.css
//
// admin_nav.php ya valida $_SESSION['admin_id'], así que cada página
// debe seguir chequeándolo ANTES de este include (igual que hasta
// ahora), porque acá no se vuelve a validar.

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$extra_css = array_merge(['css/admin.css'], $extra_css ?? []);

// Campanita de notificaciones: el feed se arma desde las tablas de la
// plataforma (ver includes/funciones_admin.php). Si algo falla (ej: falta
// correr sql/actualizar_bd.sql) la campanita queda vacía, sin romper la página.
require_once __DIR__ . '/funciones_admin.php';
if (!isset($conexion)) {
	require __DIR__ . '/../conexion.php';
}
$admin_notif = ['items' => [], 'no_leidas' => 0];
try {
	$admin_notif = admin_notificaciones_resumen($conexion, (int) $_SESSION['admin_id']);
} catch (Throwable $e) {
	error_log('[admin] no se pudo armar la campanita: ' . $e->getMessage());
}
$admin_notif_pagina = basename($_SERVER['PHP_SELF'] ?? 'panel_admin.php');

// Avisos de error que los procesar_admin_*.php mandan con ?error=...
$admin_errores = [
	'estado_no_soportado' => 'La base de datos todavía no admite ese estado de cuenta (por ejemplo "Suspendida"). Ejecutá el archivo sql/actualizar_bd.sql en phpMyAdmin y volvé a intentarlo.',
	'bd'                  => 'No se pudo guardar el cambio por un error de la base de datos. Si recién actualizaste el proyecto, ejecutá sql/actualizar_bd.sql en phpMyAdmin.',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? $page_title . ' | CT Admin' : 'CT Admin'; ?></title>

<link rel="icon" href="<?php echo $raiz; ?>img/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $raiz; ?>css/nobese.css?v=<?php echo file_exists(__DIR__ . '/../css/nobese.css') ? filemtime(__DIR__ . '/../css/nobese.css') : time(); ?>">
<?php foreach ($extra_css as $hoja_extra): ?>
<link rel="stylesheet" href="<?php echo $raiz . htmlspecialchars($hoja_extra); ?>?v=<?php echo file_exists(__DIR__ . '/../' . $hoja_extra) ? filemtime(__DIR__ . '/../' . $hoja_extra) : time(); ?>">
<?php endforeach; ?>
</head>
<body class="admin-body">

<div class="admin-shell">

	<?php include __DIR__ . '/admin_nav.php'; ?>

	<div class="admin-main">
		<header class="admin-topbar-new">
			<div>
				<h1 class="admin-page-title"><?php echo htmlspecialchars($admin_titulo ?? ''); ?></h1>
				<?php if (!empty($admin_subtitulo)): ?>
				<p class="admin-page-subtitle"><?php echo htmlspecialchars($admin_subtitulo); ?></p>
				<?php endif; ?>
			</div>

			<div class="admin-topbar-actions">
				<div class="admin-notif">
					<button type="button" class="admin-bell" id="admin-bell-btn" aria-label="Notificaciones<?php echo $admin_notif['no_leidas'] > 0 ? ' (' . (int) $admin_notif['no_leidas'] . ' sin leer)' : ''; ?>" aria-expanded="false" aria-controls="admin-notif-panel">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.7 21a2 2 0 0 1-3.4 0" stroke-linecap="round"/></svg>
						<?php if ($admin_notif['no_leidas'] > 0): ?>
						<span class="admin-bell-badge"><?php echo $admin_notif['no_leidas'] > 9 ? '9+' : (int) $admin_notif['no_leidas']; ?></span>
						<?php endif; ?>
					</button>

					<div class="admin-notif-panel" id="admin-notif-panel" hidden>
						<div class="admin-notif-panel-head">
							<strong>Notificaciones</strong>
							<?php if ($admin_notif['no_leidas'] > 0): ?>
							<form action="procesar_admin_notificaciones.php" method="POST">
								<?php echo admin_csrf_campo(); ?>
								<input type="hidden" name="accion" value="marcar_leidas">
								<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($admin_notif_pagina); ?>">
								<button type="submit" class="admin-notif-link">Marcar como leídas</button>
							</form>
							<?php endif; ?>
						</div>

						<?php if (empty($admin_notif['items'])): ?>
						<p class="admin-notif-vacio">No hay avisos por ahora.</p>
						<?php else: ?>
						<ul class="admin-notif-lista">
							<?php foreach (array_slice($admin_notif['items'], 0, 8) as $aviso): ?>
							<li>
								<a href="<?php echo htmlspecialchars($aviso['url']); ?>" class="admin-notif-item<?php echo $aviso['nueva'] ? ' es-nueva' : ''; ?>">
									<span class="admin-notif-punto admin-notif-punto--<?php echo htmlspecialchars($aviso['tipo']); ?>"></span>
									<span class="admin-notif-cuerpo">
										<span class="admin-notif-texto"><?php echo htmlspecialchars($aviso['texto']); ?></span>
										<?php if (!empty($aviso['detalle'])): ?><span class="admin-notif-detalle"><?php echo htmlspecialchars($aviso['detalle']); ?></span><?php endif; ?>
										<span class="admin-notif-meta">
											<?php echo htmlspecialchars($aviso['hace']); ?>
											<?php if (!empty($aviso['prioritario'])): ?><span class="admin-tag admin-tag--prio">Prioritario</span><?php endif; ?>
										</span>
									</span>
								</a>
							</li>
							<?php endforeach; ?>
						</ul>
						<?php endif; ?>

						<a href="admin_notificaciones.php" class="admin-notif-pie">Ver todas las notificaciones →</a>
					</div>
				</div>
				<a href="logout_admin.php" class="admin-topbar-logout">Cerrar sesión</a>
			</div>
		</header>

		<main class="admin-content">

			<?php if (isset($_GET['error'], $admin_errores[$_GET['error']])): ?>
			<p class="reg-aviso reg-aviso-error"><?php echo htmlspecialchars($admin_errores[$_GET['error']]); ?></p>
			<?php endif; ?>
