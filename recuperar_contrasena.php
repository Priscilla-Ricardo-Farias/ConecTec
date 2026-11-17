<?php
// recuperar_contrasena.php
$page_title = "Recuperar contraseña";
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

				<h1>¿Olvidaste tu contraseña?</h1>
				<p class="reg-subtitle">Escribí tu correo y cambiá tu contraseña por una nueva</p>

				<?php if (isset($_GET['enviado'])): ?>
				<p class="reg-aviso reg-aviso-ok">Si el correo está registrado, te enviamos un link para elegir una nueva contraseña. Revisá tu bandeja de entrada.</p>
				<?php elseif (isset($_GET['error'])): ?>
				<p class="reg-aviso reg-aviso-error">Ingresá un correo electrónico válido.</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_recuperar_contrasena.php" method="POST">

					<div class="reg-field">
						<label for="correo">Correo Electrónico</label>
						<input type="email" id="correo" name="correo" placeholder="ejemplo@ejemplo.com" required>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Enviar</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>