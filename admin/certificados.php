<?php
// certificados.php (panel de administración)
//
// Revisión de los certificados técnicos que envían los técnicos desde
// Configuración. El administrador ve cada certificado con su técnico, su
// especialidad y el archivo adjunto, y lo APRUEBA o lo RECHAZA (el rechazo
// lleva un motivo, que le llega al técnico por notificación).
//
// Pestañas: Pendientes (por defecto) | Aprobados | Rechazados.

session_start();

// Solo administradores con sesión de admin (la misma que usan las demás pantallas de este panel).
if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';
require_once __DIR__ . '/../includes/funciones_certificados.php';

$estados = certificado_estados();
$estado  = $_GET['estado'] ?? 'pendiente';
$estado  = isset($estados[$estado]) ? $estado : 'pendiente';

$migracion_pendiente = false;
try {
	$certificados = certificados_para_admin($conexion, $estado);
} catch (Throwable $e) {
	$certificados        = [];
	$migracion_pendiente = true;   // todavía no se corrió sql/migracion_certificados_revision.sql
}

$contadores = [];
foreach (array_keys($estados) as $e) {
	$contadores[$e] = certificados_contar($conexion, $e);
}

$avisos = [
	'aprobado'     => ['ok',    'Certificado aprobado. Le avisamos al técnico.'],
	'rechazado'    => ['ok',    'Certificado rechazado. Le avisamos al técnico con el motivo.'],
	'motivo'       => ['error', 'Para rechazar un certificado tenés que escribir el motivo.'],
	'no_pendiente' => ['error', 'Ese certificado ya fue revisado o no existe.'],
	'error'        => ['error', 'No se pudo guardar la decisión. Probá de nuevo.'],
	'csrf'         => ['error', 'La sesión de seguridad venció. Probá de nuevo.'],
];
$aviso_cert_admin = $avisos[$_GET['resultado'] ?? ''] ?? null;   // (no se llama $aviso: el layout del admin usa ese nombre)

$page_title      = "Certificados";
$admin_active    = 'certificados';
$admin_titulo    = 'Certificados técnicos';
$admin_subtitulo = 'Revisá los certificados que enviaron los técnicos';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if ($aviso_cert_admin): ?>
<p class="reg-aviso <?php echo $aviso_cert_admin[0] === 'ok' ? 'reg-aviso-ok' : 'reg-aviso-error'; ?>"><?php echo htmlspecialchars($aviso_cert_admin[1]); ?></p>
<?php endif; ?>

<?php if ($migracion_pendiente): ?>
<p class="reg-aviso reg-aviso-error">Falta correr <strong>sql/migracion_certificados_revision.sql</strong> en phpMyAdmin para poder revisar certificados.</p>
<?php endif; ?>

<section class="admin-section">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">
			Certificados
			<span class="admin-badge-count"><?php echo (int) $contadores[$estado]; ?> <?php echo strtolower($estados[$estado]); ?><?php echo $estado === 'pendiente' ? '' : 's'; ?></span>
		</h2>
	</div>

	<nav class="admin-cert-tabs" aria-label="Estado de los certificados">
		<?php foreach ($estados as $clave => $nombre_estado): ?>
		<a href="certificados.php?estado=<?php echo $clave; ?>" class="admin-cert-tab<?php echo $estado === $clave ? ' admin-cert-tab--activa' : ''; ?>">
			<?php echo htmlspecialchars($clave === 'pendiente' ? 'Pendientes' : ($clave === 'aprobado' ? 'Aprobados' : 'Rechazados')); ?>
			<span><?php echo (int) $contadores[$clave]; ?></span>
		</a>
		<?php endforeach; ?>
	</nav>

	<?php if (empty($certificados)): ?>
	<p class="admin-vacio">
		<?php echo $estado === 'pendiente' ? 'No hay certificados pendientes de revisión.' : 'No hay certificados en este estado.'; ?>
	</p>
	<?php else: ?>

	<div class="admin-verif-grid">
		<?php foreach ($certificados as $c): ?>
		<?php
		$es_pdf  = strtolower(pathinfo($c['archivo'], PATHINFO_EXTENSION)) === 'pdf';
		$url_doc = $raiz . 'uploads/certificados/' . $c['archivo'];
		?>
		<div class="admin-verif-card admin-cert-card">
			<span class="admin-verif-tipo admin-cert-estado admin-cert-estado--<?php echo htmlspecialchars($c['estado']); ?>"><?php echo htmlspecialchars(strtoupper($estados[$c['estado']])); ?></span>

			<div>
				<p class="admin-verif-nombre"><?php echo htmlspecialchars($c['nombre'] !== '' ? $c['nombre'] : 'Certificado técnico'); ?></p>
				<p class="admin-verif-sub">
					Técnico: <strong><?php echo htmlspecialchars($c['tecnico_nombre']); ?></strong>
				</p>
			</div>

			<dl class="admin-cert-datos">
				<dt>Especialidad</dt>
				<dd><?php echo htmlspecialchars(etiqueta_especialidad($c['especialidad'] ?? '') ?: '—'); ?></dd>
				<?php if (!empty($c['anio'])): ?>
				<dt>Año</dt><dd><?php echo (int) $c['anio']; ?></dd>
				<?php endif; ?>
				<?php if (!empty($c['institucion'])): ?>
				<dt>Institución</dt><dd><?php echo htmlspecialchars($c['institucion']); ?></dd>
				<?php endif; ?>
				<dt>Enviado</dt>
				<dd><?php echo htmlspecialchars((new DateTime($c['fecha_subida']))->format('d/m/Y H:i')); ?></dd>
				<?php if (!empty($c['fecha_revision'])): ?>
				<dt>Revisado</dt>
				<dd><?php echo htmlspecialchars((new DateTime($c['fecha_revision']))->format('d/m/Y H:i')); ?></dd>
				<?php endif; ?>
			</dl>

			<?php if (!empty($c['id_certificado_anterior'])): ?>
			<p class="admin-cert-historial">
				Reintento de «<?php echo htmlspecialchars($c['nombre_anterior'] ?? 'certificado anterior'); ?>», rechazado antes
				<?php if (!empty($c['motivo_anterior'])): ?>
				por: <em><?php echo htmlspecialchars($c['motivo_anterior']); ?></em>
				<?php endif; ?>
			</p>
			<?php endif; ?>

			<?php if ($c['estado'] === 'rechazado' && !empty($c['motivo_rechazo'])): ?>
			<p class="admin-cert-motivo"><strong>Motivo del rechazo:</strong> <?php echo htmlspecialchars($c['motivo_rechazo']); ?></p>
			<?php endif; ?>

			<a href="<?php echo htmlspecialchars($url_doc); ?>" target="_blank" rel="noopener" class="admin-cert-link admin-verif-cert">
				Ver documento<?php echo $es_pdf ? ' (PDF)' : ''; ?>
			</a>

			<?php if ($c['estado'] === 'pendiente'): ?>
			<form action="procesar_admin_certificado.php" method="POST" class="admin-cert-form">
				<?php echo admin_csrf_campo(); ?>
				<input type="hidden" name="id_certificado" value="<?php echo (int) $c['id_certificado']; ?>">

				<label for="motivo-<?php echo (int) $c['id_certificado']; ?>">Motivo del rechazo <span>(obligatorio solo si lo rechazás)</span></label>
				<textarea id="motivo-<?php echo (int) $c['id_certificado']; ?>" name="motivo" rows="2" maxlength="300" placeholder="Ej: La imagen está borrosa o no se lee el nombre del titular."></textarea>

				<div class="admin-verif-acciones">
					<button type="submit" name="accion" value="aprobar" class="admin-btn-aprobar" onclick="return confirm('¿Aprobar este certificado? Se va a mostrar como verificado en el perfil del técnico.');">✓ Aprobar</button>
					<button type="submit" name="accion" value="rechazar" class="admin-btn-rechazar">✗ Rechazar</button>
				</div>
			</form>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
