<?php
// servicios.php
$page_title = "Servicios";
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
			<h1>Nuestros Servicios</h1>
			<p>
				<!-- Reemplazá esta bajada por tu propio texto de introducción -->
				SServicios y soluciones técnicas para hogares, comercios y empresas, de manera rápida, sencilla y confiable.
			</p>
		</div>
	</section>

	<section class="servicios-grid-section">
		<div class="servicios-grid">
            
			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><path d="M24 4 L40 12 V24 C40 34 33 41 24 44 C15 41 8 34 8 24 V12 Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M17 24 L22 29 L32 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<h3>Perfil profesional</h3>
				<p>Crear un perfil profesional.
                   Ofrecer sus servicios.
                   Ampliar su cartera de clientes y reputación.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="8" stroke="currentColor" stroke-width="2.5"/><path d="M24 4 V12 M24 36 V44 M44 24 H36 M12 24 H4 M37.5 10.5 L32 16 M16 32 L10.5 37.5 M37.5 37.5 L32 32 M16 16 L10.5 10.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
				</div>
				<h3>Oportunidades para técnicos</h3>
				<p>Conseguir nuevos clientes.
                   Acceder a trabajos independientes.
                   Encontrar ofertas laborales.
                   Realizar reparaciones y mantenimientos solicitados por usuarios.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><rect x="8" y="8" width="32" height="32" rx="4" stroke="currentColor" stroke-width="2.5"/><path d="M8 20 H40 M20 20 V40" stroke="currentColor" stroke-width="2.5"/></svg>
				</div>
				<h3>Servicios para empresas</h3>
				<p>Publicar ofertas de empleo.
                   Buscar técnicos y profesionales.
                   Encontrar candidatos para cubrir vacantes.
                   Incorporar talento a sus equipos.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><path d="M8 40 V24 L24 8 L40 24 V40 Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M20 40 V28 H28 V40" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/></svg>
				</div>
				<h3>Solicitudes de trabajo</h3>
				<p>Publicar solicitudes de trabajo.
                   Solicitar reparaciones y mantenimientos.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><circle cx="21" cy="21" r="13" stroke="currentColor" stroke-width="2.5"/><path d="M30.5 30.5 L42 42" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><path d="M15 21 L19 25 L27 16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<h3>Búsqueda de profesionales</h3>
				<p>Buscar técnicos por especialidad.
                   Contactar profesionales.</p>
			</article>

			<article class="service-card">
				<div class="service-icon">
					<svg viewBox="0 0 48 48" fill="none"><path d="M10 38 L26 22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><path d="M30 10 C28 8 25 8 23 10 L20 13 L22 15 L19 18 L17 16 L14 19 C12 21 12 24 14 26 L14 26 L26 14 C24 12 24 12 30 10 Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M22 38 L38 22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><path d="M40 12 L36 8 L32 12 L34 16 L30 20 L34 24 L38 20 L42 24 L44 20 Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/></svg>
				</div>
				<h3>Soluciones técnicas</h3>
				<p>Encontrar soluciones para problemas del hogar, comercio o empresa.</p>
			</article>

		</div>
	</section>

</main>

<?php include 'includes/footer.php'; ?>