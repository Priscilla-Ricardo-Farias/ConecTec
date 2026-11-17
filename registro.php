<?php
// registro.php
$page_title = "Registrate";
include 'includes/header.php';
?>

<main>

	<section class="registro-hero">
		<svg class="circuit-lines" viewBox="0 0 1200 500" preserveAspectRatio="none" aria-hidden="true">
			<path d="M0 100 H280 L320 140 H760" />
			<path d="M1200 400 H820 L780 360 H340" />
			<circle cx="320" cy="140" r="5" />
			<circle cx="780" cy="360" r="5" />
		</svg>

		<div class="registro-card">
			<h1>¿Cómo te registrás?</h1>
			<p>Iniciá sesión para continuar</p>

			<?php
			/* =========================================================
			   Cada botón lleva a un formulario de registro distinto
			   según el tipo de usuario. Ajustá los href si tus archivos
			   se llaman diferente.
			   ========================================================= */
			?>
			<div class="registro-opciones">
				<a href="registro_tecnico.php" class="btn-opcion">Tecnico</a>
				<a href="registro_empresa.php" class="btn-opcion">Empresa</a>
				<a href="registro_usuario.php" class="btn-opcion">Cliente</a>
			</div>
		</div>
	</section>

</main>

<?php include 'includes/footer.php'; ?>