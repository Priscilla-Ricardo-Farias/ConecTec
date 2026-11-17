<?php
// cambiar_a_tecnico.php
//
// Lo ve un usuario/cliente logueado que quiere pasar a ofrecer sus
// servicios como técnico. Pide lo mismo que registro_tecnico.php
// pide en la sección del certificado (especialidad, año de
// graduación y el archivo), porque son los datos que le faltan a una
// cuenta de usuario para poder operar como técnico.
//
// Solo tiene sentido para cuentas de tipo "cliente"; si alguien que
// ya es técnico o empresa entra acá a mano, se lo manda de vuelta a
// ajustes.php.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

if (($_SESSION['usuario_tipo'] ?? 'cliente') !== 'cliente') {
	header('Location: ajustes.php#cuenta');
	exit;
}

require_once __DIR__ . '/includes/funciones_especialidad.php';

$anio_actual = (int) date('Y');

$page_title = "Cambiar a técnico";
$extra_css  = ['css/reg.css'];
include 'includes/header.php';
?>

<main>
	<section class="reg-page">
		<div class="reg-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<a href="ajustes.php#cuenta" class="reg-back">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					Volver
				</a>

				<h1>Convertite en técnico</h1>
				<p class="reg-subtitle">Presentá tu certificado para empezar a ofrecer tus servicios</p>

				<?php if (isset($_GET['error'])): ?>
				<p class="reg-aviso reg-aviso-error">Completá todos los campos y adjuntá tu certificado.</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_cambiar_a_tecnico.php" method="POST" enctype="multipart/form-data">

					<div class="reg-field">
						<span class="reg-cert-heading">Agregar Certificado de Técnico</span>

						<div class="reg-cert-box">
							<label class="reg-cert-upload" for="certificado_archivo">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
									<rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/>
									<circle cx="8.5" cy="8.5" r="1.6"/>
									<path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/>
								</svg>
								<img src="" alt="" class="reg-cert-preview">
							</label>
							<input type="file" id="certificado_archivo" name="certificado_archivo" accept="image/*,.pdf" required>

							<div class="reg-cert-info">
								<span>Agregar información</span>

								<select id="tipo_tecnico" name="tipo_tecnico" required>
									<option value="" selected disabled>Tipo de Técnico</option>
									<?php echo opciones_especialidades_html(); ?>
								</select>

								<input type="text" id="tipo_tecnico_otro" name="tipo_tecnico_otro" placeholder="Especificá de qué sos técnico" class="reg-otro-input" style="display:none;">

								<select id="fecha_graduacion" name="fecha_graduacion" required>
									<option value="" selected disabled>Fecha en la que se graduó</option>
									<?php for ($anio = $anio_actual; $anio >= $anio_actual - 50; $anio--): ?>
									<option value="<?php echo $anio; ?>"><?php echo $anio; ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Enviar</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<script>
	// Mismo comportamiento que ya usás en registro_tecnico.php
	const selectTipoTecnico = document.getElementById('tipo_tecnico');
	const inputTipoTecnicoOtro = document.getElementById('tipo_tecnico_otro');

	selectTipoTecnico.addEventListener('change', function () {
		const esOtro = this.value === 'otro';
		inputTipoTecnicoOtro.style.display = esOtro ? 'block' : 'none';
		inputTipoTecnicoOtro.required = esOtro;
		if (!esOtro) inputTipoTecnicoOtro.value = '';
	});

	document.getElementById('certificado_archivo').addEventListener('change', function (e) {
		const archivo = e.target.files[0];
		const preview = document.querySelector('.reg-cert-preview');
		const icono = document.querySelector('.reg-cert-upload svg');
		if (!archivo || !archivo.type.startsWith('image/')) return;

		const lector = new FileReader();
		lector.onload = function (evt) {
			preview.src = evt.target.result;
			preview.style.display = 'block';
			icono.style.display = 'none';
		};
		lector.readAsDataURL(archivo);
	});
</script>

<?php include 'includes/footer.php'; ?>