<?php
// registro_usuario.php
$page_title = "Creá tu cuenta";
$extra_css  = ['css/reg.css'];
include 'includes/header.php';
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

				<form class="reg-form" action="procesar/procesar_registro_usuario.php" method="POST">

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

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Ingresar</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>