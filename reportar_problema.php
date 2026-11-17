<?php
// reportar_problema.php
//
// Formulario para reportar un error o una conducta indebida (usuario,
// publicación, mensaje, etc.). Guarda en "mensaje_soporte"
// (tipo = 'reporte') vía procesar_reportar_problema.php.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$page_title = "Reportar un problema";
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">
			<?php $volver_a = 'ajustes.php#ayuda'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo">Reportar un problema</h1>
			<p class="ayuda-intro">Cabe algo raro: un error del sitio, un usuario o una publicación con contenido indebido. Contanos qué pasó.</p>

			<?php if (isset($_GET['enviado'])): ?>
			<p class="reg-aviso reg-aviso-ok">¡Listo! Recibimos tu reporte y lo vamos a revisar.</p>
			<?php endif; ?>

			<form action="procesar/procesar_reportar_problema.php" method="POST" class="ayuda-card ayuda-form">

				<div class="ajustes-campo">
					<label for="tipo_problema">Tipo de problema</label>
					<select id="tipo_problema" name="tipo_problema" class="ajustes-input" required>
						<option value="" disabled selected>Elegí una opción</option>
						<option value="Error o falla del sitio">Error o falla del sitio</option>
						<option value="Usuario con conducta inapropiada">Usuario con conducta inapropiada</option>
						<option value="Publicación con contenido indebido">Publicación con contenido indebido</option>
						<option value="Mensaje ofensivo o spam">Mensaje ofensivo o spam</option>
						<option value="Otro">Otro</option>
					</select>
				</div>

				<div class="ajustes-campo">
					<label for="donde">¿Dónde pasó? (opcional)</label>
					<input type="text" id="donde" name="donde" class="ajustes-input" maxlength="150" placeholder="Ej: en el chat con Juan, en una publicación de electricidad...">
				</div>

				<div class="ajustes-campo">
					<label for="descripcion">Descripción</label>
					<textarea id="descripcion" name="descripcion" class="ajustes-input ayuda-textarea" rows="6" maxlength="2000" required placeholder="Describí qué pasó, cuándo y con quién, si corresponde..."></textarea>
				</div>

				<div class="ajustes-guardar-wrap">
					<button type="submit" class="ajustes-btn ajustes-btn--primario">Enviar reporte</button>
				</div>

			</form>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>