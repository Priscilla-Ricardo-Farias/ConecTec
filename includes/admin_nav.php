<?php
$raiz = $raiz ?? '';   // prefijo hacia la raíz del proyecto ('' en la raíz, '../' en admin/)
// includes/admin_nav.php
//
// Sidebar del panel admin. Asume que quien lo incluye ya validó
// $_SESSION['admin_id'] y definió $admin_active con la clave de la
// sección actual: 'tablero', 'verificaciones', 'publicaciones',
// 'certificados', 'usuarios', 'empresas', 'tecnicos', 'soporte', 'notificaciones',
// 'reportes' o 'configuracion' ('notificaciones' no tiene link propio: se
// llega desde la campanita, y deja activo el resto sin marcar).

$admin_active = $admin_active ?? '';

function admin_nav_link($href, $clave, $activa, $icono_svg, $texto) {
	$es_activo = ($activa === $clave);
	echo '<a href="' . htmlspecialchars($href) . '" class="admin-nav-link' . ($es_activo ? ' activo' : '') . '">'
		. $icono_svg
		. '<span>' . htmlspecialchars($texto) . '</span></a>';
}
?>
<aside class="admin-sidebar">
	<div class="admin-sidebar-logo">
		<img src="<?php echo $raiz; ?>img/logo.png" alt="CT">
		<span>Panel Admin</span>
	</div>

	<nav class="admin-sidebar-nav">
		<?php
		admin_nav_link('panel_admin.php', 'tablero', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
			'Tablero de control');

		admin_nav_link('verificaciones.php', 'verificaciones', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9"/></svg>',
			'Verificaciones');

		admin_nav_link('certificados.php', 'certificados', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="9" r="5.5"/><path d="M8.5 14l-1.5 7 5-2.5 5 2.5-1.5-7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'Certificados');

		admin_nav_link('admin_trabajos.php', 'publicaciones', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5" stroke-linecap="round"/></svg>',
			'Publicaciones');

		admin_nav_link('admin_usuarios.php', 'usuarios', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.3"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round"/></svg>',
			'Usuarios');

		admin_nav_link('admin_empresas.php', 'empresas', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="7" height="10"/><rect x="13" y="4" width="7" height="16"/><path d="M7 14h1M7 17h1M16 8h1M16 11h1M16 14h1" stroke-linecap="round"/></svg>',
			'Empresas');

		admin_nav_link('admin_tecnicos.php', 'tecnicos', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a3 3 0 0 1-4.2 4.2L4 17v3h3l6.5-6.5a3 3 0 0 1 4.2-4.2l-2.4 2.4-2-2 2.4-2.4z" stroke-linejoin="round"/></svg>',
			'Técnicos');

		admin_nav_link('admin_soporte.php', 'soporte', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.5 11h7M8.5 14h4" stroke-linecap="round"/></svg>',
			'Soporte');

		admin_nav_link('admin_reportes.php', 'reportes', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M11 20V4M18 20v-7" stroke-linecap="round"/></svg>',
			'Reportes');

		admin_nav_link('admin_configuracion.php', 'configuracion', $admin_active,
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
			'Configuración');
		?>
	</nav>

	<div class="admin-sidebar-footer">
		<div class="admin-sidebar-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['admin_usuario'], 0, 1))); ?></div>
		<div class="admin-sidebar-footer-texto">
			<strong>Admin</strong>
			<a href="logout_admin.php">Cerrar sesión</a>
		</div>
	</div>
</aside>
