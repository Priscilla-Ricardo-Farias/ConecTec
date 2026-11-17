<?php
// login_admin.php
//
// Login separado del de usuarios/técnicos/empresas: valida contra la
// tabla "admin" (no contra "usuario"), y guarda la sesión en claves
// distintas ($_SESSION['admin_id'] / $_SESSION['admin_usuario']) para
// que nunca se pisen con la sesión de un usuario normal logueado en
// el mismo navegador.

session_start();

if (isset($_SESSION['admin_id'])) {
	header('Location: panel_admin.php');
	exit;
}

$page_title = "Ingreso administrador";
$extra_css  = ['css/reg.css'];
include 'includes/header.php';
?>

<main>
	<section class="reg-page">
		<div class="reg-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<h1 style="margin-bottom: 28px;">Panel de administración</h1>

				<?php if (isset($_GET['error'])): ?>
				<p class="reg-aviso reg-aviso-error">Usuario o contraseña incorrectos.</p>
				<?php endif; ?>

				<form class="reg-form" action="procesar_login_admin.php" method="POST">

					<div class="reg-field">
						<label for="usuario">Usuario</label>
						<input type="text" id="usuario" name="usuario" placeholder="Usuario admin" required autofocus>
					</div>

					<div class="reg-field">
						<label for="clave">Contraseña</label>
						<input type="password" id="clave" name="clave" placeholder="••••••" required>
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
