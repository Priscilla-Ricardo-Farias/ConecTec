<?php
// admin_notificaciones.php
//
// Historial completo de avisos del panel (los de los últimos
// ADMIN_NOTIF_DIAS días), con filtro por tipo. Es a donde lleva
// "Ver todas" en la campanita. Arriba, lo que TODAVÍA espera una
// decisión (verificaciones pendientes): eso no se "lee", se resuelve.
//
// Qué avisos se muestran lo define Configuración → Notificaciones.
// Los avisos se arman en el momento desde las tablas de la plataforma
// (ver includes/funciones_admin.php); "nueva" = más reciente que la
// última vez que se tocó "Marcar como leídas".
//
// Igual que las notificaciones de los demás usuarios: las nuevas van arriba,
// al marcar todas como leídas pasan a la sección "Leídas", y se pueden
// borrar de a una o todas las leídas juntas (borrar = ocultarlas para este
// admin; no se toca ningún dato de la plataforma).

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';

$tipos = admin_tipos_notificacion();

$filtro = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
if (!isset($tipos[$filtro])) {
	$filtro = '';
}

admin_ultimo_error(true);
$resumen    = admin_notificaciones_resumen($conexion, (int) $_SESSION['admin_id'], $filtro !== '' ? $filtro : null);
$avisos     = $resumen['items'];
$nuevos     = array_values(array_filter($avisos, function ($a) { return $a['nueva']; }));
$leidos     = array_values(array_filter($avisos, function ($a) { return !$a['nueva']; }));
$error_lectura = admin_ultimo_error();   // algún aviso no se pudo leer (ej: falta una tabla)
$pendientes = admin_pendientes($conexion);

$migracion_pendiente = admin_migracion_pendiente($conexion);

$page_title      = "Notificaciones";
$admin_active    = 'notificaciones';
$admin_titulo    = 'Notificaciones';
$admin_subtitulo = 'Lo que pasó en la plataforma en los últimos ' . ADMIN_NOTIF_DIAS . ' días';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($_GET['actualizado'])): ?>
<p class="reg-aviso reg-aviso-ok">Listo: todas las notificaciones quedaron como leídas.</p>
<?php endif; ?>

<?php if (isset($_GET['borrado'])): ?>
<p class="reg-aviso reg-aviso-ok">Listo: se borraron las notificaciones.</p>
<?php endif; ?>

<?php if (isset($_GET['err']) && is_string($_GET['err'])): ?>
<p class="reg-aviso reg-aviso-error">No se pudo completar la acción. <?php echo $_GET['err'] === 'migracion' ? 'Falta ejecutar sql/actualizar_bd.sql en phpMyAdmin.' : 'Volvé a intentarlo.'; ?></p>
<?php endif; ?>

<?php if ($error_lectura !== null): ?>
<p class="reg-aviso reg-aviso-error">Algunos avisos no se pudieron leer, así que puede faltar información acá. Si falta una tabla, ejecutá <code>sql/actualizar_bd.sql</code> en phpMyAdmin. Detalle técnico: <?php echo htmlspecialchars($error_lectura); ?></p>
<?php endif; ?>

<?php if ($migracion_pendiente): ?>
<p class="reg-aviso reg-aviso-aviso">Falta ejecutar <code>sql/actualizar_bd.sql</code> (sección 4) en phpMyAdmin: hasta entonces no se puede recordar qué avisos ya viste.</p>
<?php endif; ?>

<?php if ($pendientes['total'] > 0): ?>
<section class="admin-section admin-section--alerta">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">
			Esperan tu decisión
			<span class="admin-badge-count"><?php echo (int) $pendientes['total']; ?></span>
		</h2>
		<a href="verificaciones.php" class="admin-section-link">Ir a Verificaciones →</a>
	</div>
	<ul class="admin-pendientes">
		<?php if ($pendientes['tecnicos'] > 0): ?>
		<li><strong><?php echo (int) $pendientes['tecnicos']; ?></strong> técnico<?php echo $pendientes['tecnicos'] === 1 ? '' : 's'; ?> por verificar</li>
		<?php endif; ?>
		<?php if ($pendientes['empresas'] > 0): ?>
		<li><strong><?php echo (int) $pendientes['empresas']; ?></strong> empresa<?php echo $pendientes['empresas'] === 1 ? '' : 's'; ?> por aprobar</li>
		<?php endif; ?>
		<?php if ($pendientes['cuentas'] > 0): ?>
		<li><strong><?php echo (int) $pendientes['cuentas']; ?></strong> solicitud<?php echo $pendientes['cuentas'] === 1 ? '' : 'es'; ?> de insignia de cuenta verificada</li>
		<?php endif; ?>
	</ul>
</section>
<?php endif; ?>

<div class="admin-toolbar">
	<form action="admin_notificaciones.php" method="GET">
		<select name="tipo" aria-label="Tipo de aviso">
			<option value="">Todos los tipos</option>
			<?php foreach ($tipos as $clave => $tipo): ?>
			<option value="<?php echo htmlspecialchars($clave); ?>"<?php echo $clave === $filtro ? ' selected' : ''; ?>><?php echo htmlspecialchars($tipo['nombre']); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit">Filtrar</button>
	</form>

	<div class="admin-toolbar-derecha">
		<a href="admin_configuracion.php#notificaciones" class="admin-section-link">Elegir qué avisos ver</a>
		<?php if ($admin_notif['no_leidas'] > 0): ?>
		<form action="procesar_admin_notificaciones.php" method="POST">
			<?php echo admin_csrf_campo(); ?>
			<input type="hidden" name="accion" value="marcar_leidas">
			<input type="hidden" name="volver_a" value="admin_notificaciones.php">
			<button type="submit" class="admin-btn">Marcar todas como leídas (<?php echo (int) $admin_notif['no_leidas']; ?>)</button>
		</form>
		<?php endif; ?>
	</div>
</div>

<?php
// Un aviso de la lista (se usa para Nuevas y Leídas)
function admin_dibujar_aviso($aviso, $tipos, $filtro) {
	?>
	<li class="admin-notif-fila">
		<a href="<?php echo htmlspecialchars($aviso['url']); ?>" class="admin-notif-item<?php echo $aviso['nueva'] ? ' es-nueva' : ''; ?>">
			<span class="admin-notif-punto admin-notif-punto--<?php echo htmlspecialchars($aviso['tipo']); ?>"></span>
			<span class="admin-notif-cuerpo">
				<span class="admin-notif-texto"><?php echo htmlspecialchars($aviso['texto']); ?></span>
				<?php if (!empty($aviso['detalle'])): ?><span class="admin-notif-detalle"><?php echo htmlspecialchars($aviso['detalle']); ?></span><?php endif; ?>
				<span class="admin-notif-meta">
					<?php echo htmlspecialchars($tipos[$aviso['tipo']]['nombre']); ?> ·
					<?php echo htmlspecialchars($aviso['hace']); ?>
					<?php if (!empty($aviso['prioritario'])): ?><span class="admin-tag admin-tag--prio">Prioritario</span><?php endif; ?>
					<?php if ($aviso['nueva']): ?><span class="admin-tag admin-tag--nueva">Nueva</span><?php endif; ?>
				</span>
			</span>
		</a>
		<?php if (!empty($aviso['clave'])): ?>
		<form action="procesar_admin_notificaciones.php" method="POST" class="admin-notif-borrar-form">
			<?php echo admin_csrf_campo(); ?>
			<input type="hidden" name="accion" value="borrar">
			<input type="hidden" name="clave" value="<?php echo htmlspecialchars($aviso['clave']); ?>">
			<input type="hidden" name="volver_a" value="admin_notificaciones.php">
			<button type="submit" class="admin-notif-borrar" aria-label="Borrar notificación" title="Borrar notificación">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
			</button>
		</form>
		<?php endif; ?>
	</li>
	<?php
}
?>

<?php if (empty($avisos)): ?>

<section class="admin-section admin-section--lista">
	<p class="admin-vacio">No hay notificaciones<?php echo $filtro !== '' ? ' de este tipo' : ''; ?> en los últimos <?php echo (int) ADMIN_NOTIF_DIAS; ?> días.</p>
</section>

<?php else: ?>

<!-- NUEVAS -->
<section class="admin-section admin-section--lista">
	<div class="admin-notif-seccion-titulo">
		<h2>Nuevas <span class="admin-badge-count"><?php echo count($nuevos); ?></span></h2>
	</div>
	<?php if (empty($nuevos)): ?>
	<p class="admin-vacio">No tenés notificaciones nuevas.</p>
	<?php else: ?>
	<ul class="admin-notif-lista admin-notif-lista--pagina">
		<?php foreach ($nuevos as $aviso) { admin_dibujar_aviso($aviso, $tipos, $filtro); } ?>
	</ul>
	<?php endif; ?>
</section>

<!-- LEÍDAS -->
<?php if (!empty($leidos)): ?>
<section class="admin-section admin-section--lista admin-notif-leidas">
	<div class="admin-notif-seccion-titulo">
		<h2>Leídas <span class="admin-badge-count admin-badge-count--gris"><?php echo count($leidos); ?></span></h2>
		<form action="procesar_admin_notificaciones.php" method="POST" onsubmit="return confirm('¿Borrar todas las notificaciones leídas?');">
			<?php echo admin_csrf_campo(); ?>
			<input type="hidden" name="accion" value="borrar_leidas">
			<input type="hidden" name="volver_a" value="admin_notificaciones.php">
			<button type="submit" class="admin-notif-borrar-todas">Borrar leídas</button>
		</form>
	</div>
	<ul class="admin-notif-lista admin-notif-lista--pagina">
		<?php foreach ($leidos as $aviso) { admin_dibujar_aviso($aviso, $tipos, $filtro); } ?>
	</ul>
</section>
<?php endif; ?>

<?php if (count($avisos) >= ADMIN_NOTIF_MAX): ?>
<p class="admin-campo-nota admin-lista-pie">Se muestran los <?php echo (int) ADMIN_NOTIF_MAX; ?> avisos más recientes.</p>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
