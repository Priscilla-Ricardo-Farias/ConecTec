<?php
// centro_ayuda.php
//
// Página central de ayuda: agrupa guías rápidas por tema y los
// accesos a preguntas frecuentes / contacto / reporte. Estática, no
// necesita base de datos.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$page_title = "Centro de ayuda";
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css'];
include 'includes/header_hogar.php';

$temas = [
	[
		'titulo'  => 'Publicar y contratar',
		'texto'   => 'Cómo publicar un trabajo, revisar postulantes y aceptar al técnico o la empresa que más te convenga.',
	],
	[
		'titulo'  => 'Postularte a trabajos',
		'texto'   => 'Cómo buscar publicaciones abiertas, postularte y hacer seguimiento del estado de tus postulaciones.',
	],
	[
		'titulo'  => 'Mensajes y notificaciones',
		'texto'   => 'Cómo iniciar una conversación, qué significan los tildes de "visto" y cuándo te llega una notificación.',
	],
	[
		'titulo'  => 'Tu cuenta',
		'texto'   => 'Editar tus datos, cambiar tu contraseña, pasar a cuenta de técnico o eliminar tu cuenta.',
	],
];
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">
			<?php $volver_a = 'ajustes.php#ayuda'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo">Centro de ayuda</h1>
			<p class="ayuda-intro">Elegí un tema para orientarte, o andá directo a preguntas frecuentes y contacto.</p>

			<div class="ayuda-card ayuda-temas">
				<?php foreach ($temas as $tema): ?>
				<div class="ayuda-tema">
					<h3><?php echo htmlspecialchars($tema['titulo']); ?></h3>
					<p><?php echo htmlspecialchars($tema['texto']); ?></p>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="ayuda-card">
				<a href="preguntas_frecuentes.php" class="ajustes-item ajustes-item--link">
					<span>Preguntas frecuentes</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
				<a href="contactar_soporte.php" class="ajustes-item ajustes-item--link">
					<span>Contactar soporte</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
				<a href="reportar_problema.php" class="ajustes-item ajustes-item--link">
					<span>Reportar un problema</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
			</div>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>