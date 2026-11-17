<?php
// admin_configuracion.php
//
// Sección "Configuración" del sidebar. Cuatro bloques:
//   1) Notificaciones → qué tipos de aviso muestra la campanita.
//   2) Reportes       → rango de días con el que abre Reportes.
//   3) Mi cuenta      → cambiar la contraseña del admin logueado.
//   4) Administradores → ver, sumar y quitar cuentas de admin.
//
// Los formularios van a procesar_admin_configuracion.php, que vuelve
// acá con ?ok=... o ?err=... (se usa "err" y no "error" porque el
// layout ya interpreta ?error= con sus propios mensajes).
//
// Los precios de los planes y la demora de notificaciones para cuentas
// sin plan siguen en includes/funciones_suscripcion.php: no son ajustes
// del panel, son reglas de la plataforma.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';

$migracion_pendiente = admin_migracion_pendiente($conexion);

$tipos_notif = admin_tipos_notificacion();
$rangos      = admin_reporte_rangos();
$dias_defecto = admin_reporte_dias($conexion, null);

$admins = admin_consulta($conexion, 'SELECT id_admin, usuario FROM admin ORDER BY id_admin ASC');
$mi_id  = (int) $_SESSION['admin_id'];

$avisos_ok = [
	'notif'   => 'Notificaciones guardadas.',
	'reportes' => 'Rango de Reportes guardado.',
	'clave'   => 'Contraseña actualizada.',
	'creado'  => 'Administrador creado.',
	'borrado' => 'Administrador eliminado.',
];

$avisos_err = [
	'csrf'          => 'La sesión del formulario venció. Volvé a intentarlo.',
	'bd'            => 'No se pudo guardar por un error de la base de datos.',
	'migracion'     => 'Falta actualizar la base: ejecutá sql/actualizar_bd.sql en phpMyAdmin y volvé a intentarlo.',
	'rango'         => 'Elegí un rango válido.',
	'clave_actual'  => 'La contraseña actual no es correcta.',
	'clave_corta'   => 'La contraseña nueva debe tener al menos ' . ADMIN_PASS_MIN . ' caracteres.',
	'clave_distinta' => 'La confirmación no coincide con la contraseña nueva.',
	'clave_igual'   => 'La contraseña nueva tiene que ser distinta de la actual.',
	'usuario_formato' => 'El usuario debe tener entre 3 y 50 caracteres: letras, números, punto, guion o guion bajo.',
	'usuario_existe' => 'Ya existe un administrador con ese usuario.',
	'borrar_yo'     => 'No podés eliminar tu propia cuenta de administrador.',
	'borrar_ultimo' => 'Tiene que quedar al menos un administrador.',
	'borrar_no'     => 'No se encontró ese administrador.',
];

$ok  = is_string($_GET['ok']  ?? null) ? $_GET['ok']  : '';
$err = is_string($_GET['err'] ?? null) ? $_GET['err'] : '';

$page_title      = "Configuración";
$admin_active    = 'configuracion';
$admin_titulo    = 'Configuración';
$admin_subtitulo = 'Ajustes del panel de administración';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($avisos_ok[$ok])): ?>
<p class="reg-aviso reg-aviso-ok"><?php echo htmlspecialchars($avisos_ok[$ok]); ?></p>
<?php endif; ?>

<?php if (isset($avisos_err[$err])): ?>
<p class="reg-aviso reg-aviso-error"><?php echo htmlspecialchars($avisos_err[$err]); ?></p>
<?php endif; ?>

<?php if ($migracion_pendiente): ?>
<p class="reg-aviso reg-aviso-aviso">Todavía falta actualizar la base de datos: ejecutá <code>sql/actualizar_bd.sql</code> en phpMyAdmin (sección 4). Hasta entonces no se pueden guardar las notificaciones ni el rango de Reportes, y la campanita no recuerda qué avisos ya viste.</p>
<?php endif; ?>

<!-- ---------- 1) NOTIFICACIONES ---------- -->
<section class="admin-section" id="notificaciones">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">Notificaciones</h2>
	</div>
	<p class="admin-section-ayuda">Elegí qué avisos aparecen en la campanita y en <a href="admin_notificaciones.php">Notificaciones</a>. Se muestran los de los últimos <?php echo (int) ADMIN_NOTIF_DIAS; ?> días.</p>

	<form action="procesar_admin_configuracion.php" method="POST" class="admin-form">
		<?php echo admin_csrf_campo(); ?>
		<input type="hidden" name="accion" value="guardar_notificaciones">

		<div class="admin-switches">
			<?php foreach ($tipos_notif as $clave => $tipo): ?>
			<label class="admin-switch">
				<input type="checkbox" name="notif[<?php echo htmlspecialchars($clave); ?>]" value="1"<?php echo admin_notif_activa($conexion, $clave) ? ' checked' : ''; ?>>
				<span class="admin-switch-pista" aria-hidden="true"></span>
				<span class="admin-switch-texto">
					<strong><?php echo htmlspecialchars($tipo['nombre']); ?></strong>
					<small><?php echo htmlspecialchars($tipo['desc']); ?></small>
				</span>
			</label>
			<?php endforeach; ?>
		</div>

		<div class="admin-form-acciones">
			<button type="submit" class="admin-btn-primario"<?php echo $migracion_pendiente ? ' disabled' : ''; ?>>Guardar notificaciones</button>
		</div>
	</form>
</section>

<!-- ---------- 2) REPORTES ---------- -->
<section class="admin-section" id="reportes">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">Reportes</h2>
	</div>
	<p class="admin-section-ayuda">Período con el que abre la pantalla de <a href="admin_reportes.php">Reportes</a>. Igual se puede cambiar ahí en cualquier momento.</p>

	<form action="procesar_admin_configuracion.php" method="POST" class="admin-form">
		<?php echo admin_csrf_campo(); ?>
		<input type="hidden" name="accion" value="guardar_reportes">

		<div class="admin-campo">
			<label for="dias">Período por defecto</label>
			<select id="dias" name="dias">
				<?php foreach ($rangos as $valor => $texto): ?>
				<option value="<?php echo (int) $valor; ?>"<?php echo $valor === $dias_defecto ? ' selected' : ''; ?>><?php echo htmlspecialchars($texto); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="admin-form-acciones">
			<button type="submit" class="admin-btn-primario"<?php echo $migracion_pendiente ? ' disabled' : ''; ?>>Guardar período</button>
		</div>
	</form>
</section>

<!-- ---------- 3) MI CUENTA ---------- -->
<section class="admin-section" id="mi-cuenta">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">Mi cuenta</h2>
	</div>
	<p class="admin-section-ayuda">Sesión iniciada como <strong><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></strong>. Cambiar la contraseña no cierra tu sesión.</p>

	<form action="procesar_admin_configuracion.php" method="POST" class="admin-form" autocomplete="off">
		<?php echo admin_csrf_campo(); ?>
		<input type="hidden" name="accion" value="cambiar_contrasena">

		<div class="admin-campos-fila">
			<div class="admin-campo">
				<label for="clave_actual">Contraseña actual</label>
				<input type="password" id="clave_actual" name="clave_actual" autocomplete="current-password" required>
			</div>
			<div class="admin-campo">
				<label for="clave_nueva">Contraseña nueva</label>
				<input type="password" id="clave_nueva" name="clave_nueva" minlength="<?php echo (int) ADMIN_PASS_MIN; ?>" autocomplete="new-password" required>
			</div>
			<div class="admin-campo">
				<label for="clave_confirmar">Repetir contraseña nueva</label>
				<input type="password" id="clave_confirmar" name="clave_confirmar" minlength="<?php echo (int) ADMIN_PASS_MIN; ?>" autocomplete="new-password" required>
			</div>
		</div>
		<p class="admin-campo-nota">Mínimo <?php echo (int) ADMIN_PASS_MIN; ?> caracteres.</p>

		<div class="admin-form-acciones">
			<button type="submit" class="admin-btn-primario">Cambiar contraseña</button>
		</div>
	</form>
</section>

<!-- ---------- 4) ADMINISTRADORES ---------- -->
<section class="admin-section" id="administradores">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">
			Administradores
			<span class="admin-badge-count" style="background:#eaf1fc;color:var(--blue-accent);"><?php echo count($admins); ?></span>
		</h2>
	</div>
	<p class="admin-section-ayuda">Todos los administradores tienen el mismo acceso al panel (todavía no hay roles).</p>

	<div class="admin-tabla-wrap admin-tabla-wrap--plana">
		<table class="admin-tabla">
			<thead>
				<tr>
					<th>Usuario</th>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($admins as $a): ?>
				<tr>
					<td>
						<?php echo htmlspecialchars($a['usuario']); ?>
						<?php if ((int) $a['id_admin'] === $mi_id): ?><span class="admin-tag">Vos</span><?php endif; ?>
					</td>
					<td>
						<?php if ((int) $a['id_admin'] !== $mi_id && count($admins) > 1): ?>
						<div class="admin-acciones">
							<form action="procesar_admin_configuracion.php" method="POST" onsubmit="return confirm('¿Eliminar al administrador <?php echo htmlspecialchars(addslashes($a['usuario'])); ?>? Va a dejar de poder ingresar al panel.');">
								<?php echo admin_csrf_campo(); ?>
								<input type="hidden" name="accion" value="eliminar_admin">
								<input type="hidden" name="id_admin" value="<?php echo (int) $a['id_admin']; ?>">
								<button type="submit" class="admin-btn admin-btn-danger">Eliminar</button>
							</form>
						</div>
						<?php else: ?>
						<span class="admin-campo-nota">—</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<form action="procesar_admin_configuracion.php" method="POST" class="admin-form admin-form--separado" autocomplete="off">
		<?php echo admin_csrf_campo(); ?>
		<input type="hidden" name="accion" value="crear_admin">

		<h3 class="admin-subtitulo">Agregar administrador</h3>

		<div class="admin-campos-fila">
			<div class="admin-campo">
				<label for="nuevo_usuario">Usuario</label>
				<input type="text" id="nuevo_usuario" name="nuevo_usuario" maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" autocomplete="off" required>
			</div>
			<div class="admin-campo">
				<label for="nueva_clave">Contraseña</label>
				<input type="password" id="nueva_clave" name="nueva_clave" minlength="<?php echo (int) ADMIN_PASS_MIN; ?>" autocomplete="new-password" required>
			</div>
		</div>
		<p class="admin-campo-nota">Usuario de 3 a 50 caracteres (letras, números, punto, guion o guion bajo). Contraseña de al menos <?php echo (int) ADMIN_PASS_MIN; ?> caracteres.</p>

		<div class="admin-form-acciones">
			<button type="submit" class="admin-btn-primario">Agregar administrador</button>
		</div>
	</form>
</section>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
