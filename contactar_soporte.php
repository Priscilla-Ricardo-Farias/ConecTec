<?php
// contactar_soporte.php
//
// Formulario de contacto general. Guarda el mensaje en
// "mensaje_soporte" (tipo = 'contacto') vía procesar_contactar_soporte.php.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';

$con_prioridad = tiene_plan_activo($conexion, (int) $_SESSION['usuario_id']);

$page_title = "Contactar soporte";
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">

			<?php $volver_a = 'ajustes.php#ayuda'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo">Contactar soporte</h1>
			<p class="ayuda-intro">Contanos tu consulta y el equipo de Conectec te va a responder a la brevedad.</p>

			<?php if ($con_prioridad): ?>
			<p class="ayuda-intro">Tenés atención prioritaria: tus mensajes se responden primero.</p>
			<?php endif; ?>

			<?php if (isset($_GET['enviado'])): ?>
			<p class="reg-aviso reg-aviso-ok">¡Listo! Recibimos tu mensaje, te vamos a responder pronto.</p>
			<?php endif; ?>

			<form action="procesar/procesar_contactar_soporte.php" method="POST" class="ayuda-card ayuda-form">

				<div class="ajustes-campo">
					<label for="asunto">Asunto</label>
					<select id="asunto" name="asunto" class="ajustes-input" required>
						<option value="" disabled selected>Elegí un motivo</option>
						<option value="Consulta sobre mi cuenta">Consulta sobre mi cuenta</option>
						<option value="Consulta sobre un trabajo o postulación">Consulta sobre un trabajo o postulación</option>
						<option value="Problema con un pago o cobro">Problema con un pago o cobro</option>
						<option value="Sugerencia">Sugerencia</option>
						<option value="Otro">Otro</option>
					</select>
				</div>

				<div class="ajustes-campo">
					<label for="mensaje">Mensaje</label>
					<textarea id="mensaje" name="mensaje" class="ajustes-input ayuda-textarea" rows="6" maxlength="2000" required placeholder="Contanos con el mayor detalle posible qué necesitás..."></textarea>
				</div>

				<div class="ajustes-guardar-wrap">
					<button type="submit" class="ajustes-btn ajustes-btn--primario">Enviar</button>
				</div>

			</form>

			<p class="ayuda-pie">
				También podés escribirnos directamente a
				<a href="mailto:soporte@conectec.com">soporte@conectec.com</a>.
			</p>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>