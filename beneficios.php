<?php
// beneficios.php
$page_title = "Beneficios";
include 'includes/header.php';
?>

<main>

	<section class="servicios-hero">
		<svg class="circuit-lines" viewBox="0 0 1200 400" preserveAspectRatio="none" aria-hidden="true">
			<path d="M0 80 H260 L300 120 H900" />
			<path d="M1200 320 H900 L860 280 H400" />
			<circle cx="300" cy="120" r="5" />
			<circle cx="860" cy="280" r="5" />
		</svg>

		<div class="servicios-hero-inner">
			<h1>Beneficios</h1>
			<p>
				<!-- Reemplazá esta bajada por tu propio texto de introducción -->
				¿Por qué registrarte? <br>
				Porque aqui encontraras mucho más que una bolsa de trabajo.
			</p>
		</div>
	</section>

	<section class="servicios-grid-section">
		<div class="servicios-grid">

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><path d="M24 4 L28.5 8.5 L34.5 7 L36 13 L42 15.5 L39.5 21 L42 26.5 L36 29 L34.5 35 L28.5 33.5 L24 38 L19.5 33.5 L13.5 35 L12 29 L6 26.5 L8.5 21 L6 15.5 L12 13 L13.5 7 L19.5 8.5 Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M18 21 L22 25 L30 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<h3>Si eres técnico</h3>
				<p>Podras aumentar tu visibilidad, conseguir nuevos clientes, acceder a ofertas laborales y desarrollar tu reputación profesional.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><rect x="6" y="16" width="36" height="24" rx="3" stroke="currentColor" stroke-width="2.5"/><path d="M17 16 V11 C17 9 18.5 8 20 8 H28 C29.5 8 31 9 31 11 V16" stroke="currentColor" stroke-width="2.5"/><path d="M6 26 H42" stroke="currentColor" stroke-width="2.5"/><rect x="21" y="23" width="6" height="6" rx="1" fill="currentColor"/></svg>
				</div>
				<h3>Si representas a una empresa</h3>
				<p>Podras encontrar candidatos calificados para cubrir vacantes de forma rapida y eficiente.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="16" r="8" stroke="currentColor" stroke-width="2.5"/><path d="M8 42 C8 32 15 27 24 27 C33 27 40 32 40 42" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
				</div>
				<h3>Si eres un cliente</h3>
				<p>Tendras acceso a tecnicos de distintas especialidades para resolver cualquier incoveniente de manera sencilla, sin largas busquedas ni complicaciones.</p>
			</article>
             
			 <p class="servicios-footer-text">
			    Únete hoy a nuestra comunidad y descubre una nueva forma de conectar el talento con las oportunidades. Ya se que busques trabajo, necesites un profesional o quieras ofrecer tus servicios, este es el lugar donde las oportunidades comienzan.
                </p>

		</div>
	</section>

</main>

<?php include 'includes/footer.php'; ?>
                        