<?php
// restablecer_contrasena.php
//
// Es la página a la que llega la persona al hacer clic en el link
// que le mandamos por correo desde procesar_recuperar_contrasena.php
// (algo como restablecer_contrasena.php?token=XXXXX). Acá elige su
// contraseña nueva.
//
// TODO cuando conectes la base de datos: antes de mostrar el
// formulario, buscar el token en la base y comprobar que exista y
// no haya expirado; si no es válido, redirigir a
// recuperar_contrasena.php?error=token_invalido en vez de mostrar el
// formulario.

$token = $_GET['token'] ?? '';

if ($token === '') {
	header('Location: recuperar_contrasena.php?error=token_invalido');
	exit;
}

$page_title = "Nueva contraseña";
$extra_css  = ['css/reg.css'];
include 'includes/header.php';
?>

<main>
	<section class="reg-page">
		<div class="reg-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<a href="login.php" class="reg-back">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					Volver
				</a>

				<h1>Elegí tu nueva contraseña</h1>
				<p class="reg-subtitle">Escribila dos veces para confirmar que es la correcta</p>

				<?php if (isset($_GET['error'])): ?>
				<p class="reg-aviso reg-aviso-error">Las contraseñas no coinciden. Probá de nuevo.</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_restablecer_contrasena.php" method="POST">

					<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

					<div class="reg-field">
						<label for="clave_nueva">Contraseña nueva</label>
						<input type="password" id="clave_nueva" name="clave_nueva" placeholder="••••••" minlength="6" required>
					</div>

					<div class="reg-field">
						<label for="clave_nueva_confirmar">Confirmar contraseña</label>
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