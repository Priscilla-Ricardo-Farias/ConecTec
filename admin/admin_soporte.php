<?php
// admin_soporte.php
//
// Bandeja de solo lectura con lo que los usuarios mandan desde
// "Contactar soporte" (tipo = 'contacto') y "Reportar un problema"
// (tipo = 'reporte'), tabla mensaje_soporte. Los de cuentas con plan
// activo llegan con "[PRIORITARIO]" al principio del asunto y acá
// salen primero.
//
// Todavía no hay estado "resuelto" ni respuesta desde el panel: para
// contestar se usa el botón "Responder por correo" (abre el mail del
// usuario).

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';

$filtro_tipo = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
if (!in_array($filtro_tipo, ['contacto', 'reporte'], true)) {
	$filtro_tipo = '';
}
$solo_prio = isset($_GET['prioritarios']);

$sc  = admin_soporte_cols($conexion);   // id_mensaje_soporte/fecha o id_soporte/fecha_creacion, según la base
$sql = "SELECT m.{$sc['id']} AS id, m.tipo, m.asunto, m.mensaje, m.{$sc['fecha']} AS fecha,
               u.nombre, u.email, u.estado_cuenta
        FROM mensaje_soporte m
        JOIN usuario u ON u.id_usuario = m.id_usuario
        WHERE 1 = 1";
$params = [];

if ($filtro_tipo !== '') {
	$sql .= ' AND m.tipo = ?';
	$params[] = $filtro_tipo;
}
if ($solo_prio) {
	$sql .= " AND m.asunto LIKE '[PRIORITARIO]%'";
}

// Prioritarios primero, después lo más nuevo. Tope de 200 mensajes.
$sql .= " ORDER BY (m.asunto LIKE '[PRIORITARIO]%') DESC, m.{$sc['fecha']} DESC LIMIT 200";

admin_ultimo_error(true);
$mensajes = admin_consulta($conexion, $sql, $params);
$error_lectura = admin_ultimo_error();   // null si la consulta anduvo (aunque no haya mensajes)

$page_title      = "Soporte";
$admin_active    = 'soporte';
$admin_titulo    = 'Soporte';
$admin_subtitulo = 'Consultas y reportes de problemas de los usuarios';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if ($error_lectura !== null): ?>
<p class="reg-aviso reg-aviso-error">No se pudieron leer los mensajes de soporte. Lo más probable es que falte la tabla <code>mensaje_soporte</code>: ejecutá <code>sql/actualizar_bd.sql</code> en phpMyAdmin. Detalle técnico: <?php echo htmlspecialchars($error_lectura); ?></p>
<?php endif; ?>

<div class="admin-toolbar">
	<form action="admin_soporte.php" method="GET">
		<select name="tipo" aria-label="Tipo de mensaje">
			<option value="">Consultas y reportes</option>
			<option value="contacto"<?php echo $filtro_tipo === 'contacto' ? ' selected' : ''; ?>>Solo consultas</option>
			<option value="reporte"<?php echo $filtro_tipo === 'reporte' ? ' selected' : ''; ?>>Solo reportes de problemas</option>
		</select>
		<label class="admin-check">
			<input type="checkbox" name="prioritarios" value="1"<?php echo $solo_prio ? ' checked' : ''; ?>>
			Solo prioritarios
		</label>
		<button type="submit">Filtrar</button>
	</form>
</div>

<section class="admin-section admin-section--lista">
	<?php if (empty($mensajes)): ?>

	<p class="admin-vacio">No hay mensajes de soporte<?php echo ($filtro_tipo !== '' || $solo_prio) ? ' con estos filtros' : ' todavía'; ?>.</p>

	<?php else: ?>

	<ul class="admin-soporte-lista">
		<?php foreach ($mensajes as $m): ?>
		<?php
		$prioritario = strpos($m['asunto'], '[PRIORITARIO]') === 0;
		$asunto      = trim(preg_replace('/^\[PRIORITARIO\]\s*/', '', $m['asunto']));
		?>
		<li class="admin-soporte-item" id="m<?php echo (int) $m['id']; ?>">
			<details>
				<summary>
					<span class="admin-soporte-asunto"><?php echo htmlspecialchars($asunto); ?></span>
					<span class="admin-soporte-previa"><?php echo htmlspecialchars(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $m['mensaje'])), 0, 160, '…')); ?></span>
					<span class="admin-soporte-meta">
						<span class="admin-tag"><?php echo $m['tipo'] === 'reporte' ? 'Reporte' : 'Consulta'; ?></span>
						<?php if ($prioritario): ?><span class="admin-tag admin-tag--prio">Prioritario</span><?php endif; ?>
						<?php echo htmlspecialchars($m['nombre']); ?> ·
						<?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($m['fecha']))); ?>
					</span>
				</summary>

				<div class="admin-soporte-detalle">
					<p class="admin-soporte-texto"><?php echo nl2br(htmlspecialchars($m['mensaje'])); ?></p>
					<p class="admin-campo-nota">
						<?php echo htmlspecialchars($m['nombre']); ?> · <?php echo htmlspecialchars($m['email']); ?>
						<?php if ($m['estado_cuenta'] !== 'activa'): ?> · cuenta <?php echo htmlspecialchars($m['estado_cuenta']); ?><?php endif; ?>
					</p>
					<a class="admin-btn admin-btn-link" href="mailto:<?php echo htmlspecialchars($m['email']); ?>?subject=<?php echo rawurlencode('Re: ' . $asunto); ?>">Responder por correo</a>
				</div>
			</details>
		</li>
		<?php endforeach; ?>
	</ul>

	<?php if (count($mensajes) >= 200): ?>
	<p class="admin-campo-nota admin-lista-pie">Se muestran los 200 mensajes más importantes/recientes.</p>
	<?php endif; ?>

	<?php endif; ?>
</section>

<script>
// Si se llega desde una notificación (admin_soporte.php#m12), abre ese mensaje.
(function () {
	try {
		var item = location.hash ? document.querySelector(location.hash) : null;
		var det = item && item.querySelector('details');
		if (det) { det.open = true; item.scrollIntoView({ block: 'center' }); }
	} catch (e) { /* hash que no es un selector válido: se ignora */ }
})();
</script>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
