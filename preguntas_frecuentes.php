<?php
// preguntas_frecuentes.php
//
// FAQ estática (no necesita base de datos). Usa <details>/<summary>
// para el acordeón, así funciona sin JavaScript.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$page_title = "Preguntas frecuentes";
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css'];
include 'includes/header_hogar.php';

$preguntas = [
	[
		'q' => '¿Cómo publico un trabajo?',
		'r' => 'Desde el inicio, tocá "Posteá tu trabajo" y completá la categoría, la descripción y la dirección. Los técnicos van a poder postularse apenas quede publicado.',
	],
	[
		'q' => '¿Cómo me postulo a un trabajo?',
		'r' => 'Si tenés una cuenta de técnico, vas a ver el botón "Postularme" en cada publicación. El dueño del trabajo puede aceptar tu postulación desde su propio feed.',
	],
	[
		'q' => '¿Qué pasa cuando aceptan mi postulación?',
		'r' => 'El trabajo pasa a "en progreso", se agenda automáticamente en tu calendario y le aparece al dueño una notificación para escribirte por mensaje.',
	],
	[
		'q' => '¿Cómo sé si ya vieron mi mensaje?',
		'r' => 'Al lado de la hora de cada mensaje que mandaste vas a ver un tilde: uno solo significa que todavía no lo vieron, dos significa que ya lo leyeron.',
	],
	[
		'q' => '¿Puedo pasar de cuenta de cliente a cuenta de técnico?',
		'r' => 'Sí. Desde Ajustes → Cuenta vas a encontrar la opción para convertir tu cuenta en una cuenta de técnico, cargando tu especialidad y certificado.',
	],
	[
		'q' => '¿Cómo verifico mi perfil de técnico o de empresa?',
		'r' => 'La verificación depende de la revisión del equipo de Conectec sobre el certificado o los datos cargados. Mientras se revisa, tu perfil funciona igual, pero sin la insignia de verificado.',
	],
	[
		'q' => '¿Cómo elimino mi cuenta?',
		'r' => 'Desde Ajustes → Cuenta → "Eliminar cuenta". Es una acción permanente, así que te la vamos a confirmar antes de borrar nada.',
	],
];
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">
			<?php $volver_a = 'ajustes.php#ayuda'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo">Preguntas frecuentes</h1>

			<div class="ayuda-card">
				<?php foreach ($preguntas as $p): ?>
				<details class="ayuda-faq-item">
					<summary><?php echo htmlspecialchars($p['q']); ?></summary>
					<p><?php echo htmlspecialchars($p['r']); ?></p>
				</details>
				<?php endforeach; ?>
			</div>

			<p class="ayuda-pie">
				¿No encontraste lo que buscabas? Visitá el
				<a href="centro_ayuda.php">Centro de ayuda</a> o
				<a href="contactar_soporte.php">contactá a soporte</a>.
			</p>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>