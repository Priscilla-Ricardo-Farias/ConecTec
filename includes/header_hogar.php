<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once __DIR__ . '/indicadores_header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<script>
	(function () {
		if (localStorage.getItem('ct_tema') === 'oscuro') {
			document.documentElement.setAttribute('data-theme', 'oscuro');
		}
	})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? $page_title . ' | CT' : 'CT'; ?></title>

<link rel="icon" href="img/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/nobese.css?v=<?php echo file_exists(__DIR__ . '/../css/nobese.css') ? filemtime(__DIR__ . '/../css/nobese.css') : time(); ?>">
<?php if (!empty($extra_css)): foreach ($extra_css as $hoja_extra): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($hoja_extra); ?>?v=<?php echo file_exists(__DIR__ . '/../' . $hoja_extra) ? filemtime(__DIR__ . '/../' . $hoja_extra) : time(); ?>">
<?php endforeach; endif; ?>
</head>
<body>

<header class="site-header site-header-app">
	<div class="header-inner header-inner-hogar">

		<a href="feed.php" class="home-btn" aria-label="Volver al inicio">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M4 11 L12 4 L20 11" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M6 10 V20 H18 V10" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</a>

		<?php if (empty($nav_bloqueada)): ?>
		<nav class="app-icons app-icons-hogar" aria-label="Accesos rápidos">
			<a href="mensajes.php" class="app-icon-btn" aria-label="Mensajes<?php echo $hay_mensajes_sin_leer ? ' (hay mensajes sin leer)' : ''; ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7 L12 13 L21 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				<?php if ($hay_mensajes_sin_leer): ?><span class="app-icon-punto" aria-hidden="true"></span><?php endif; ?>
			</a>
			<a href="calendario.php" class="app-icon-btn" aria-label="Calendario">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10 H21 M8 3 V7 M16 3 V7" stroke-linecap="round"/></svg>
			</a>
			<a href="notificaciones.php" class="app-icon-btn" aria-label="Notificaciones<?php echo $hay_notif_sin_leer ? ' (hay notificaciones sin leer)' : ''; ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 10 C6 6 8.5 4 12 4 C15.5 4 18 6 18 10 C18 14 19.5 15.5 19.5 16.5 H4.5 C4.5 15.5 6 14 6 10 Z" stroke-linejoin="round"/><path d="M9.5 19 a2.5 2.5 0 0 0 5 0"/></svg>
				<?php if ($hay_notif_sin_leer): ?><span class="app-icon-punto" aria-hidden="true"></span><?php endif; ?>
			</a>
			<a href="ajustes.php" class="app-icon-btn" aria-label="Ajustes">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 13c.05-.33.1-.66.1-1s-.05-.67-.1-1l2-1.5-2-3.4-2.4 1a7.6 7.6 0 0 0-1.7-1L15 3h-6l-.3 2.5a7.6 7.6 0 0 0-1.7 1l-2.4-1-2 3.4L4.6 11c-.05.33-.1.66-.1 1s.05.67.1 1l-2 1.5 2 3.4 2.4-1a7.6 7.6 0 0 0 1.7 1L9 21h6l.3-2.5a7.6 7.6 0 0 0 1.7-1l2.4 1 2-3.4Z" stroke-linejoin="round"/></svg>
			</a>
			<a href="perfil.php" class="app-icon-btn app-icon-btn-perfil" aria-label="Mi perfil">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21 C4 16.5 7.5 13.5 12 13.5 C16.5 13.5 20 16.5 20 21" stroke-linecap="round"/></svg>
			</a>
		</nav>
		<?php else: ?>
		<?php // Empresa sin abono o sin aprobar: solo puede pagar o cerrar sesión. ?>
		<nav class="app-icons app-icons-hogar" aria-label="Sesión">
			<a href="logout.php" class="app-icon-btn" aria-label="Cerrar sesión" title="Cerrar sesión">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 8l4 4-4 4M20 12H9" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</a>
		</nav>
		<?php endif; ?>

	</div>
</header>