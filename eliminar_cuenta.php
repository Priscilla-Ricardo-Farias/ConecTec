<?php
// eliminar_cuenta.php
//
// Pantalla de confirmación antes de borrar la cuenta (para que no se
// pueda eliminar sin querer con un solo clic desde ajustes.php).
// El botón "Sí, eliminar mi cuenta" manda a procesar_eliminar_cuenta.php.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$page_title = "Eliminar cuenta";
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

				<h1>¿Eliminar tu cuenta?</h1>
				<p class="reg-subtitle">Se van a borrar tu perfil, tus publicaciones y tu historial. Esta acción no se puede deshacer.</p>

				<form class="reg-form" action="procesar/procesar_eliminar_cuenta.php" method="POST">

					<div class="reg-field">
						<label for="clave_confirmar">Confirmá tu contraseña</label>
						<input type="password" id="clave_confirmar" name="clave_confirmar" placeholder="••••••" required>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Sí, eliminar mi cuenta</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>