<?php
// registro_empresa.php
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

				<?php
				$errores = [
					'campos_incompletos' => 'Completá todos los campos.',
					'correo_invalido'    => 'Ingresá un correo electrónico válido.',
					'correo_registrado'  => 'Ya existe una cuenta con ese correo.',
					'cuit_invalido'      => 'El CUIT tiene que tener el formato 20-12345678-9.',
					'cuit_registrado'    => 'Ya existe una empresa registrada con ese CUIT.',
					'error_servidor'     => 'Ocurrió un error, probá de nuevo.',
				];
				$error = $_GET['error'] ?? '';
				if (isset($errores[$error])):
				?>
				<p class="reg-aviso reg-aviso-error"><?php echo htmlspecialchars($errores[$error]); ?></p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_registro_empresa.php" method="POST">

					<div class="reg-field">
						<label for="nombre_empresa">Nombre de Empresa</label>
						<input type="text" id="nombre_empresa" name="nombre_empresa" placeholder="Ingrese nombre de la empresa" required>
					</div>

					<div class="reg-field">
						<label for="cuit">CUIT</label>
						<input type="text" id="cuit" name="cuit" placeholder="xx-xxxxxxxx-x" pattern="\d{2}-\d{8}-\d{1}" maxlength="13" title="Formato: 20-12345678-9" required>
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
						<label for="fecha_nacimiento">Fecha de creación de la empresa</label>
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
