<?php
// cambiar_contrasena.php
//
// A diferencia de recuperar_contrasena.php (para cuando te olvidaste
// la contraseña y NO estás logueado), esta es para cambiarla estando
// logueado: pide la contraseña actual como confirmación, más la
// nueva dos veces.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$page_title = "Cambiar contraseña";
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

				<h1>Cambiar contraseña</h1>
				<p class="reg-subtitle">Confirmá tu contraseña actual y elegí la nueva</p>

				<?php if (isset($_GET['error'])): ?>
				<p class="reg-aviso reg-aviso-error">Revisá los datos: la contraseña actual no puede estar vacía y las nuevas tienen que coincidir.</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_cambiar_contrasena.php" method="POST">

					<div class="reg-field">
						<label for="clave_actual">Contraseña actual</label>
						<input type="password" id="clave_actual" name="clave_actual" placeholder="••••••" required>
					</div>

					<div class="reg-field">
						<label for="clave_nueva">Contraseña nueva</label>
						<input type="password" id="clave_nueva" name="clave_nueva" placeholder="••••••" minlength="6" required>
					</div>

					<div class="reg-field">
						<label for="clave_nueva_confirmar">Confirmar contraseña nueva</label>
						<input type="password" id="clave_nueva_confirmar" name="clave_nueva_confirmar" placeholder="••••••" minlength="6" required>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Guardar</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>