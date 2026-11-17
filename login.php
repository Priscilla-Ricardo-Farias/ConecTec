<?php
// login.php
$page_title = "Iniciar sesión";
$extra_css  = ['css/reg.css'];
require __DIR__ . '/includes/funciones_suscripcion.php';
include 'includes/header.php';
?>

<main>
	<section class="reg-page">
		<div class="reg-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<a href="nosotros.php" class="reg-back">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					Volver
				</a>

				<div class="login-empresa-aviso">
					<p><strong>¿Sos una empresa?</strong> El acceso para empresas requiere un abono de <?php echo formatear_precio(planes_conectec()['empresa_abono']['precio']); ?> cada <?php echo SUSCRIPCION_DIAS; ?> días, que se activa al ingresar.</p>
					<a href="registro_empresa.php">Registrar mi empresa</a>
				</div>

				<h1 style="margin-bottom: 28px;">Iniciar Sesión</h1>

				<?php if (isset($_GET['clave_actualizada'])): ?>
				<p class="reg-aviso reg-aviso-ok">Tu contraseña se actualizó. Iniciá sesión con la nueva.</p>
				<?php elseif (isset($_GET['error']) && $_GET['error'] === 'cuenta_suspendida'): ?>
				<p class="reg-aviso reg-aviso-error">Tu cuenta está suspendida. Contactá a soporte si creés que es un error.</p>
				<?php elseif (isset($_GET['error']) && $_GET['error'] === 'cuenta_eliminada'): ?>
				<p class="reg-aviso reg-aviso-error">Esta cuenta fue eliminada.</p>
				<?php elseif (isset($_GET['error'])): ?>
				<p class="reg-aviso reg-aviso-error">Nombre, correo o contraseña incorrectos.</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_login.php" method="POST">

					<div class="reg-field">
						<label for="nombre">Nombre</label>
						<input type="text" id="nombre" name="nombre" placeholder="Ingrese su nombre" required>
					</div>

					<div class="reg-field">
						<label for="correo">Correo Electrónico</label>
						<input type="email" id="correo" name="correo" placeholder="ejemplo@ejemplo.com" required>
					</div>

					<div class="reg-field">
						<label for="clave">Contraseña</label>
						<input type="password" id="clave" name="clave" placeholder="••••••" required>
						<a href="recuperar_contrasena.php" class="reg-forgot">¿Olvidaste tu contraseña?</a>
					</div>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Ingresá</button>
					</div>

				</form>

				<p class="reg-subtitle-bottom">¿No tenés cuenta? <a href="registro.php">Registrate</a></p>
			</div>
		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>