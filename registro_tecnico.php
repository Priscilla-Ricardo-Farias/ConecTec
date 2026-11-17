<?php
// registro_tecnico.php
require_once __DIR__ . '/includes/funciones_especialidad.php';

$page_title = "Creá tu cuenta";
$extra_css  = ['css/reg.css'];
include 'includes/header.php';

// Años disponibles para "Fecha en la que se graduó" (últimos 50 años)
$anio_actual = (int) date('Y');
?>

<main>
	<section class="reg-page">
		<div class="reg-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<a href="registro.php" class="reg-back">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					Volver
				</a>

				<h1>Creá una cuenta</h1>
				<p class="reg-subtitle">¿Ya estás registrado? <a href="login.php">Ingresá</a></p>

				<form class="reg-form" action="procesar/procesar_registro_tecnico.php" method="POST" enctype="multipart/form-data">

					<div class="reg-field">
						<label for="nombre">Nombre</label>
						<input type="text" id="nombre" name="nombre" placeholder="Ingrese su nombre" required>
					</div>

					<div class="reg-field">
						<label for="correo">Correo Electrónico</label>
						<input type="email" id="correo" name="correo" placeholder="ejemplo@ejemplo.com" required>
					</div>

					<div class="reg-field">
						<label for="clave">Clave</label>
						<input type="password" id="clave" name="clave" placeholder="••••••" required>
					</div>

					<div class="reg-field">
						<label for="fecha_nacimiento">Fecha de Nacimiento</label>
						<input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
					</div>

					<?php
					/* =========================================================
					   AGREGAR CERTIFICADO DE TÉCNICO
					   - Carga de imagen/archivo del certificado.
					   - Tipo de Técnico: reemplazá las opciones por tu propio
					     listado de especialidades.
					   - Fecha en la que se graduó: selector de año (últimos 50
					     años). Cambiá el rango si lo necesitás.
					   ========================================================= */
					?>
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
						<button type="submit" class="reg-submit">Ingresar</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<script>
	// Muestra un campo de texto cuando eligen "Otro" en Tipo de Técnico
	const selectTipoTecnico = document.getElementById('tipo_tecnico');
	const inputTipoTecnicoOtro = document.getElementById('tipo_tecnico_otro');

	selectTipoTecnico.addEventListener('change', function () {
		const esOtro = this.value === 'otro';
		inputTipoTecnicoOtro.style.display = esOtro ? 'block' : 'none';
		inputTipoTecnicoOtro.required = esOtro;
		if (!esOtro) inputTipoTecnicoOtro.value = '';
	});

	// Muestra una vista previa de la imagen del certificado al seleccionarla
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