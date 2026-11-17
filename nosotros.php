<?php
// nosotros.php
$page_title = "Nosotros";
$extra_css  = ['css/nosotros.css'];
include 'includes/header.php';
?>

<main class="nos">

	<!-- ============ PORTADA ============ -->
	<section class="nos-hero">
		<svg class="circuit-lines" viewBox="0 0 1200 700" preserveAspectRatio="none" aria-hidden="true">
			<path d="M0 120 H300 L340 160 H700" />
			<path d="M0 560 H250 L290 520 H600 L640 480 H1200" />
			<circle cx="700" cy="160" r="5" />
			<circle cx="640" cy="480" r="5" />
		</svg>

		<div class="nos-hero-inner">
			<div class="nos-hero-texto">
				<span class="nos-etiqueta">Sobre ConecTec</span>
				<h1>Conectamos a quien <span class="nos-resalte">necesita ayuda técnica</span> con quien sabe resolverla</h1>
				<p class="nos-bajada">Hogares, comercios y empresas encuentran técnicos de confianza. Los técnicos encuentran trabajo.</p>
				<div class="nos-botones">
					<a href="registro.php" class="nos-btn nos-btn-principal">Sumate</a>
					<a href="servicios.php" class="nos-btn nos-btn-secundario">Ver servicios</a>
				</div>
			</div>

			<div class="nos-collage">
				<figure class="nos-foto nos-foto-1">
					<img src="img/tecnicos.avif" alt="Equipo técnico trabajando sobre una máquina en planta">
				</figure>
				<figure class="nos-foto nos-foto-2">
					<img src="img/tecnicos2.jpg" alt="Equipo reunido frente a una pizarra en planta">
				</figure>
				<figure class="nos-foto nos-foto-3">
					<img src="img/tecnicos3.avif" alt="Saludo entre un directivo y un operario en planta">
				</figure>
				<span class="nos-chip nos-chip-1">Rápido</span>
				<span class="nos-chip nos-chip-2">Seguro</span>
			</div>
		</div>
	</section>

	<!-- ============ PARA QUIÉN ES ============ -->
	<section class="nos-seccion">
		<div class="nos-contenedor">
			<h2 class="nos-titulo">Una plataforma, tres perfiles</h2>
			<p class="nos-subtitulo">Cada uno encuentra lo que busca.</p>

			<div class="nos-tarjetas">
				<article class="nos-tarjeta">
					<div class="nos-icono">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>
					</div>
					<h3>Cliente</h3>
					<p>Publicá tu problema y recibí postulaciones de técnicos.</p>
				</article>

				<article class="nos-tarjeta nos-tarjeta-destacada">
					<div class="nos-icono">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
					</div>
					<h3>Técnicos</h3>
					<p>Mostrá tu experiencia, conseguí clientes y hacé crecer tu carrera.</p>
				</article>

				<article class="nos-tarjeta">
					<div class="nos-icono">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="1"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2"/></svg>
					</div>
					<h3>Empresas</h3>
					<p>Sumá talento a tu equipo con ofertas de empleo.</p>
				</article>
			</div>
		</div>
	</section>

	<!-- ============ CÓMO FUNCIONA ============ -->
	<section class="nos-seccion nos-seccion-oscura">
		<div class="nos-contenedor">
			<h2 class="nos-titulo nos-titulo-claro">Cómo funciona</h2>
			<p class="nos-subtitulo nos-subtitulo-claro">En pocos pasos.</p>

			<ol class="nos-pasos">
				<li>
					<span class="nos-paso-num">1</span>
					<h3>Registrate</h3>
					<p>Creá tu perfil en minutos.</p>
				</li>
				<li>
					<span class="nos-paso-num">2</span>
					<h3>Publicá o buscá</h3>
					<p>Pedí un servicio o encontrá trabajo.</p>
				</li>
				<li>
					<span class="nos-paso-num">3</span>
					<h3>Conectá</h3>
					<p>Hablá directo y cerrá el trato.</p>
				</li>
			</ol>
		</div>
	</section>

	<!-- ============ VALORES + CIERRE ============ -->
	<section class="nos-seccion">
		<div class="nos-contenedor">
			<div class="nos-valores">
				<div class="nos-valor"><strong>Confianza</strong><span>Perfiles verificados</span></div>
				<div class="nos-valor"><strong>Calidad</strong><span>Profesionales calificados</span></div>
				<div class="nos-valor"><strong>Oportunidades</strong><span>Para todas las especialidades</span></div>
			</div>

			<div class="nos-cierre">
				<h2>El talento merece ser visible</h2>
				<p>Encontrar ayuda especializada no debería ser complicado.</p>
				<a href="registro.php" class="nos-btn nos-btn-principal">Empezá ahora</a>
			</div>
		</div>
	</section>

</main>

<?php include 'includes/footer.php'; ?>
