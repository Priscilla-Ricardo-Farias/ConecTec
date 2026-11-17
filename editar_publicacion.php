<?php
// editar_publicacion.php
//
// Permite al dueño cambiar los datos de una publicación suya (título, tipo de
// técnico, descripción o características, dirección y, en las de clientes, la
// fecha límite), siempre que todavía no se haya aceptado como trabajo.
// Las fotos y videos no se cambian desde acá.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_especialidad.php';
require_once __DIR__ . '/includes/funciones_publicaciones.php';

exigir_abono_empresa($conexion);

$pub = publicacion_propia_editable($conexion, $_GET['id'] ?? 0, $_SESSION['usuario_id']);

if (!$pub) {
	header('Location: feed.php?error=no_editable');
	exit;
}

$es_empresa = $pub['es_empresa'];

// Si la especialidad es de la lista se elige en el desplegable; si la escribieron a mano, va en "Otro".
$clave_actual  = clave_especialidad($pub['categoria']);
$de_la_lista   = es_especialidad_de_lista($clave_actual);
$opciones_tipo = opciones_especialidades_html($de_la_lista ? $clave_actual : '');
if (!$de_la_lista) {
	$opciones_tipo = str_replace('<option value="otro">', '<option value="otro" selected>', $opciones_tipo);
}

$page_title = "Editar publicación";
$extra_css  = ['css/reg.css', 'css/feed.css'];
include 'includes/header_interno.php';
?>

<main>
	<section class="reg-page">
		<div class="reg-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<a href="feed.php" class="reg-back">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					Volver
				</a>

				<h1>Editar publicación</h1>
				<p class="reg-subtitle">Podés cambiar los datos mientras no hayas aceptado a ningún técnico.</p>

				<?php if (($_GET['error'] ?? '') === 'campos_incompletos'): ?>
				<p class="reg-aviso reg-aviso-error">Completá todos los campos obligatorios (y revisá que la fecha límite sea válida).</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_editar_publicacion.php" method="POST">
					<input type="hidden" name="id_trabajo" value="<?php echo (int) $pub['id_trabajo']; ?>">

					<div class="reg-field">
						<label for="titulo_trabajo">Título del trabajo</label>
						<input type="text" id="titulo_trabajo" name="titulo_trabajo" value="<?php echo htmlspecialchars($pub['titulo']); ?>" required>
					</div>

					<div class="reg-field">
						<label for="tipo_tecnico">Tipo de técnico</label>
						<select id="tipo_tecnico" name="tipo_tecnico" required>
							<?php echo $opciones_tipo; ?>
						</select>
					</div>

					<input type="text" id="tipo_tecnico_otro" name="tipo_tecnico_otro" placeholder="*Especificá qué técnico necesitás" class="reg-otro-input" value="<?php echo $de_la_lista ? '' : htmlspecialchars($pub['categoria']); ?>" style="display:<?php echo $de_la_lista ? 'none' : 'block'; ?>;">

					<?php if ($es_empresa): ?>
					<div class="reg-field">
						<label for="caracteristicas">Características necesarias</label>
						<input type="text" id="caracteristicas" name="caracteristicas" value="<?php echo htmlspecialchars($pub['caracteristicas'] ?? ''); ?>" required>
					</div>
					<?php else: ?>
					<div class="reg-field">
						<label for="descripcion">Descripción</label>
						<input type="text" id="descripcion" name="descripcion" value="<?php echo htmlspecialchars($pub['descripcion'] ?? ''); ?>" required>
					</div>
					<?php endif; ?>

					<div class="reg-field">
						<label for="direccion">Dirección</label>
						<input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($pub['ubicacion']); ?>" required>
					</div>

					<?php if (!$es_empresa): ?>
					<div class="reg-field">
						<label for="fecha">Fecha límite <span style="font-weight:400;color:#9aa3b5;">(opcional)</span></label>
						<input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($pub['fecha_trabajo'] ?? ''); ?>">
					</div>

					<p class="carrusel-ayuda">Las fotos y videos no se pueden cambiar desde acá. Si querés otros, eliminá la publicación y subí una nueva.</p>
					<?php endif; ?>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Guardar cambios</button>
					</div>
				</form>
			</div>
		</div>
	</section>
</main>

<script>
	// Muestra un campo de texto cuando eligen "Otro" en Tipo de técnico
	const selectTipoTecnico = document.getElementById('tipo_tecnico');
	const inputTipoTecnicoOtro = document.getElementById('tipo_tecnico_otro');

	function actualizarOtro() {
		const esOtro = selectTipoTecnico.value === 'otro';
		inputTipoTecnicoOtro.style.display = esOtro ? 'block' : 'none';
		inputTipoTecnicoOtro.required = esOtro;
		if (!esOtro) inputTipoTecnicoOtro.value = '';
	}
	selectTipoTecnico.addEventListener('change', actualizarOtro);
	inputTipoTecnicoOtro.required = selectTipoTecnico.value === 'otro';
</script>

<?php include 'includes/footer.php'; ?>
