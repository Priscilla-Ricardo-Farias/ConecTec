<?php
// publicar.php
//
// Es la página a la que lleva la barra "Postea tu trabajo" del feed.
// Muestra un formulario distinto según quién inició sesión:
//
//   - Empresa           → busca un técnico para cubrir un puesto
//                          (título, tipo de técnico, características
//                          necesarias, dirección).
//   - Cliente / usuario  → describe un problema y pide un técnico
//                          (título, tipo de técnico, descripción,
//                          dirección, fecha, foto o video).
//
// Igual que feed.php, esta pantalla es solo para quien ya inició
// sesión.
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_especialidad.php';

// Las empresas sin abono activo no pueden publicar.
exigir_abono_empresa($conexion);

// Si por alguna razón no quedó guardado el tipo de cuenta, se asume
// "cliente" (el formulario más simple) en vez de romper la página.
$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$es_empresa  = $tipo_cuenta === 'empresa';

$page_title = "Publicar trabajo";
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

				<h1>Formulario</h1>
				<p class="reg-subtitle">*Obligatorio completar antes de publicar</p>

				<?php if ($es_empresa): ?>

				<!-- ================= FORMULARIO DE EMPRESA ================= -->
				<form class="reg-form" action="procesar/procesar_publicacion.php" method="POST">

					<div class="reg-field">
						<label for="titulo_trabajo">Título del trabajo</label>
						<input type="text" id="titulo_trabajo" name="titulo_trabajo" placeholder="*Ingrese título" required>
					</div>

					<div class="reg-field">
						<label for="tipo_tecnico">Tipo de técnico</label>
						<select id="tipo_tecnico" name="tipo_tecnico" required>
							<option value="" selected disabled>Seleccionar</option>
							<?php echo opciones_especialidades_html(); ?>
						</select>
					</div>

					<input type="text" id="tipo_tecnico_otro" name="tipo_tecnico_otro" placeholder="*Especificá qué técnico necesitás" class="reg-otro-input" style="display:none;">

					<div class="reg-field">
						<label for="caracteristicas">Características necesarias</label>
						<input type="text" id="caracteristicas" name="caracteristicas" placeholder="*Ingrese características" required>
					</div>

					<div class="reg-field">
						<label for="direccion">Dirección</label>
						<input type="text" id="direccion" name="direccion" placeholder="*Ingrese su dirección" required>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Subir</button>
					</div>

				</form>

				<?php else: ?>

				<!-- ============ FORMULARIO DE CLIENTE / USUARIO ============ -->
				<form class="reg-form" action="procesar/procesar_publicacion.php" method="POST" enctype="multipart/form-data">

					<div class="reg-field">
						<label for="titulo_trabajo">Título del trabajo</label>
						<input type="text" id="titulo_trabajo" name="titulo_trabajo" placeholder="*Ingrese título" required>
					</div>

					<div class="reg-field">
						<label for="tipo_tecnico">Tipo de técnico</label>
						<select id="tipo_tecnico" name="tipo_tecnico" required>
							<option value="" selected disabled>Seleccionar</option>
							<?php echo opciones_especialidades_html(); ?>
						</select>
					</div>

					<input type="text" id="tipo_tecnico_otro" name="tipo_tecnico_otro" placeholder="*Especificá qué técnico necesitás" class="reg-otro-input" style="display:none;">

					<div class="reg-field">
						<label for="descripcion">Descripción</label>
						<input type="text" id="descripcion" name="descripcion" placeholder="*Ingrese descripción" required>
					</div>

					<div class="reg-field">
						<label for="direccion">Dirección</label>
						<input type="text" id="direccion" name="direccion" placeholder="*Ingrese su dirección" required>
					</div>

					<div class="reg-field">
						<label for="fecha">Fecha límite <span style="font-weight:400;color:#9aa3b5;">(opcional)</span></label>
						<input type="date" id="fecha" name="fecha" min="<?php echo date('Y-m-d'); ?>">
					</div>

					<div class="reg-field">
						<span class="reg-cert-heading">Ingrese fotos o videos de su problema</span>
						<span class="carrusel-ayuda">Podés elegir hasta 10. Se verán como carrusel en el feed.</span>

						<label class="carrusel-subir" for="publicacion_archivo">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
								<rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/>
								<circle cx="8.5" cy="8.5" r="1.6"/>
								<path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/>
							</svg>
							<span id="carrusel-texto">Elegir fotos o videos</span>
						</label>
						<input type="file" id="publicacion_archivo" name="publicacion_archivo[]" accept="image/*,video/*" multiple required class="carrusel-input">

						<div class="carrusel-previas" id="carrusel-previas"></div>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Subir</button>
					</div>

				</form>

				<?php endif; ?>

			</div>
		</div>
	</section>
</main>

<script>
	// Muestra un campo de texto cuando eligen "Otro" en Tipo de Técnico
	// (mismo comportamiento que ya usás en registro_tecnico.php)
	const selectTipoTecnico = document.getElementById('tipo_tecnico');
	const inputTipoTecnicoOtro = document.getElementById('tipo_tecnico_otro');

	selectTipoTecnico.addEventListener('change', function () {
		const esOtro = this.value === 'otro';
		inputTipoTecnicoOtro.style.display = esOtro ? 'block' : 'none';
		inputTipoTecnicoOtro.required = esOtro;
		if (!esOtro) inputTipoTecnicoOtro.value = '';
	});

	// Carrusel: se pueden sumar fotos/videos de a uno o varios a la vez
	// (máximo 10). Se muestran miniaturas y cada una se puede quitar con la X.
	const MAX_ARCHIVOS = 10;
	const inputArchivo = document.getElementById('publicacion_archivo');
	const contPrevias  = document.getElementById('carrusel-previas');
	const textoSubir   = document.getElementById('carrusel-texto');
	let seleccion = [];

	function sincronizar() {
		const dt = new DataTransfer();
		seleccion.forEach(f => dt.items.add(f));
		inputArchivo.files = dt.files;
		inputArchivo.required = seleccion.length === 0;
	}

	function dibujar() {
		contPrevias.innerHTML = '';
		seleccion.forEach((archivo, i) => {
			const item = document.createElement('div');
			item.className = 'carrusel-previa';

			let media;
			if (archivo.type.startsWith('video/')) {
				media = document.createElement('video');
				media.muted = true;
				media.preload = 'metadata';
			} else {
				media = document.createElement('img');
				media.alt = '';
			}
			media.src = URL.createObjectURL(archivo);
			item.appendChild(media);

			if (i === 0) {
				const tag = document.createElement('span');
				tag.className = 'carrusel-previa-portada';
				tag.textContent = 'Portada';
				item.appendChild(tag);
			}

			const quitar = document.createElement('button');
			quitar.type = 'button';
			quitar.className = 'carrusel-previa-quitar';
			quitar.setAttribute('aria-label', 'Quitar');
			quitar.innerHTML = '&times;';
			quitar.addEventListener('click', () => {
				seleccion.splice(i, 1);
				sincronizar();
				dibujar();
			});
			item.appendChild(quitar);

			contPrevias.appendChild(item);
		});

		textoSubir.textContent = seleccion.length === 0
			? 'Elegir fotos o videos'
			: seleccion.length + (seleccion.length === 1 ? ' archivo' : ' archivos') + ' · agregar más';
	}

	if (inputArchivo) {
		inputArchivo.addEventListener('change', function () {
			const nuevos = Array.from(this.files);
			seleccion = seleccion.concat(nuevos).slice(0, MAX_ARCHIVOS);
			if (seleccion.length === MAX_ARCHIVOS && nuevos.length > 0) {
				alert('El máximo es de ' + MAX_ARCHIVOS + ' archivos por publicación.');
			}
			sincronizar();
			dibujar();
		});
	}
</script>

<?php include 'includes/footer.php'; ?>