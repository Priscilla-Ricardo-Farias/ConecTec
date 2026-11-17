<?php
// perfil.php
//
// Muestra el perfil de un técnico. Tiene dos modos:
//
//   - PERFIL PROPIO (perfil.php, sin parámetros): el usuario logueado
//     viendo su propio perfil. El primer casillero de la galería es
//     un botón "+" para publicar fotos de trabajos (como en Instagram:
//     foto + descripción opcional). Se ven solo en el perfil.
//   - PERFIL VISITADO (perfil.php?id=123): un usuario o empresa
//     viendo el perfil de OTRO técnico. El "123" es el id_tecnico
//     (no el id_usuario). No aparece el botón "+".
//
// Ya conectado a la base de datos real (antes usaba un array
// hardcodeado como ejemplo).

session_start();
require_once __DIR__ . '/conexion.php';
require __DIR__ . '/includes/funciones_mensajes.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_especialidad.php';
require_once __DIR__ . '/includes/funciones_certificados.php';

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$viendo_id = $_GET['id'] ?? null;
$es_propio = $viendo_id === null;

/* =========================================================
   BUSCAR AL TÉCNICO
   ========================================================= */

// El LEFT JOIN a preferencia_usuario trae si el técnico eligió
// ocultar su CV; si nunca guardó preferencias, se toma como visible
// (mismo default que tiene la columna en la base).
if ($es_propio) {
	// Perfil propio: el id_tecnico se busca a partir del id_usuario
	// logueado (no todos los tipos de cuenta tienen fila en "tecnico").
	$stmt = $conexion->prepare(
		'SELECT t.id_tecnico, t.especialidad, t.descripcion, t.promedio_calificacion, t.cv_archivo,
		        t.certificado_archivo, t.estado_verificacion,
		        u.id_usuario AS id_usuario_tecnico, u.nombre AS nombre_usuario, u.foto_perfil,
		        COALESCE(pu.privacidad_cv_visible, 1) AS cv_visible,
		        ' . sql_insignia('u.id_usuario') . ' AS verificado
		 FROM tecnico t
		 JOIN usuario u ON u.id_usuario = t.id_usuario
		 LEFT JOIN preferencia_usuario pu ON pu.id_usuario = t.id_usuario
		 WHERE t.id_usuario = ?'
	);
	$stmt->bind_param('i', $_SESSION['usuario_id']);
} else {
	// Perfil visitado: $viendo_id es el id_tecnico
	$stmt = $conexion->prepare(
		'SELECT t.id_tecnico, t.especialidad, t.descripcion, t.promedio_calificacion, t.cv_archivo,
		        t.certificado_archivo, t.estado_verificacion,
		        u.id_usuario AS id_usuario_tecnico, u.nombre AS nombre_usuario, u.foto_perfil,
		        COALESCE(pu.privacidad_cv_visible, 1) AS cv_visible,
		        ' . sql_insignia('u.id_usuario') . ' AS verificado
		 FROM tecnico t
		 JOIN usuario u ON u.id_usuario = t.id_usuario
		 LEFT JOIN preferencia_usuario pu ON pu.id_usuario = t.id_usuario
		 WHERE t.id_tecnico = ?'
	);
	$stmt->bind_param('i', $viendo_id);
}

$stmt->execute();
$resultado = $stmt->get_result();
$tecnico   = $resultado->fetch_assoc();
$stmt->close();

/* =========================================================
   CASO 1: es el perfil propio, pero esta cuenta todavía no es
   técnico (es cliente o empresa) → no hay fila en "tecnico".
   En vez de romper la página, la mandamos a convertirse en técnico.
   ========================================================= */
if ($es_propio && !$tecnico) {
	$page_title = "Mi perfil";
	$extra_css  = ['css/feed.css', 'css/panel.css'];
	include 'includes/header_hogar.php';
	?>
	<main>
		<section class="panel-page">
			<div class="panel-page-inner">

			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>
				<div class="perfil-card" style="justify-content:center; text-align:center; display:block; padding:48px 28px;">
					<p style="font-family:var(--font-heading); font-weight:700; font-size:1.1rem; color:var(--navy-900); margin-bottom:10px;">
						Todavía no tenés un perfil de técnico
					</p>
					<p style="color:#6b7280; font-size:0.92rem; margin-bottom:22px;">
						Para tener perfil público, portfolio y calificaciones, primero convertí tu cuenta en cuenta de técnico.
					</p>
					<a href="cambiar_a_tecnico.php" class="reg-submit" style="display:inline-block;">Convertirme en técnico</a>
				</div>
			</div>
		</section>
	</main>
	<?php
	include 'includes/footer.php';
	exit;
}

/* =========================================================
   CASO 2: perfil visitado que no existe (id inválido)
   ========================================================= */
if (!$es_propio && !$tecnico) {
	$page_title = "Perfil no encontrado";
	$extra_css  = ['css/feed.css', 'css/panel.css'];
	include 'includes/header_hogar.php';
	?>
	<main>
		<section class="panel-page">
			<div class="panel-page-inner">

			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>
				<div class="perfil-card" style="justify-content:center; text-align:center; display:block; padding:48px 28px;">
					<p style="font-family:var(--font-heading); font-weight:700; font-size:1.1rem; color:var(--navy-900);">
						No encontramos ese perfil
					</p>
				</div>
			</div>
		</section>
	</main>
	<?php
	include 'includes/footer.php';
	exit;
}

/* =========================================================
   PORTFOLIO: publicaciones de trabajos de este técnico (solo se ven
   acá, no en el inicio). Cada una es un "proyecto" con su foto.
   ========================================================= */
$stmt = $conexion->prepare(
	'SELECT p.id_proyecto, p.descripcion, p.fecha_realizacion, fp.imagen
	 FROM proyecto p
	 JOIN foto_proyecto fp ON fp.id_proyecto = p.id_proyecto
	 WHERE p.id_tecnico = ?
	 ORDER BY p.id_proyecto DESC, fp.id_foto ASC'
);
$stmt->bind_param('i', $tecnico['id_tecnico']);
$stmt->execute();
$filas_portfolio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Una publicación puede tener varias fotos: se juntan por publicación (en el perfil
// es un solo casillero y al abrirla se ven como una ruleta).
$portfolio = [];
foreach ($filas_portfolio as $fila) {
	$id_p = (int) $fila['id_proyecto'];
	if (!isset($portfolio[$id_p])) {
		$portfolio[$id_p] = [
			'id_proyecto'       => $id_p,
			'descripcion'       => $fila['descripcion'],
			'fecha_realizacion' => $fila['fecha_realizacion'],
			'fotos'             => [],
		];
	}
	$portfolio[$id_p]['fotos'][] = (string) $fila['imagen'];
}
$portfolio = array_values($portfolio);

// Todas las especialidades del técnico y sus certificados adicionales
$especialidades_perfil = especialidades_de_tecnico($conexion, $tecnico['id_tecnico']);
if (empty($especialidades_perfil)) {
	$especialidades_perfil = [['especialidad' => $tecnico['especialidad'], 'clave' => clave_especialidad($tecnico['especialidad'])]];
}

$certificados_extra = [];
try {
	// Solo los certificados APROBADOS por un administrador se ven en el perfil público.
	// El dueño del perfil ve todos, cada uno con su estado (pendiente / aprobado / rechazado).
	$certificados_extra = certificados_del_tecnico($conexion, (int) $tecnico['id_tecnico'], !$es_propio);
} catch (Throwable $e) {
	$certificados_extra = [];   // todavía no se corrió la migración
}

$avisos_portfolio = [
	'subido'    => ['ok',    '¡Publicaste tu trabajo!'],
	'eliminado' => ['ok',    'Publicación eliminada.'],
	'formato'   => ['error', 'Las fotos tienen que ser JPG, PNG o WEBP.'],
	'grande'    => ['error', 'Las fotos pesan demasiado (máximo 5 MB cada una).'],
	'limite'    => ['error', 'Podés subir hasta 10 fotos por publicación.'],
	'error'     => ['error', 'No pudimos guardar la publicación. Probá de nuevo.'],
];
$aviso_portfolio = $es_propio ? ($avisos_portfolio[$_GET['portfolio'] ?? ''] ?? null) : null;

/* =========================================================
   CALIFICACIONES: promedio (1 a 5) y cantidad, sacados de las
   calificaciones reales que dejaron clientes y empresas. Se muestra
   como estrellas llenas en proporción al promedio y el número, ej: 3,8.
   ========================================================= */
$stmt = $conexion->prepare('SELECT COUNT(*) AS cantidad, AVG(puntaje) AS promedio FROM calificacion WHERE id_tecnico = ?');
$stmt->bind_param('i', $tecnico['id_tecnico']);
$stmt->execute();
$stats_cal = $stmt->get_result()->fetch_assoc();
$stmt->close();

$cantidad_calificaciones = (int) ($stats_cal['cantidad'] ?? 0);
$promedio_calificacion   = $cantidad_calificaciones > 0 ? (float) $stats_cal['promedio'] : 0.0;
$promedio_texto          = number_format($promedio_calificacion, 1, ',', '');   // ej: 4,5

// Las estrellas se llenan proporcionalmente al promedio, sin redondear:
// 3,8 = tres estrellas llenas y la cuarta llena al 80%.

// Certificado: puede ser imagen o PDF (el formulario de registro acepta
// image/* y .pdf). Si es imagen se muestra dentro del cuadro; si es PDF
// se muestra un ícono, porque un PDF no entra bien en un cuadro chico.
$cert_archivo = $tecnico['certificado_archivo'];
$cert_url     = $cert_archivo ? 'uploads/certificados/' . $cert_archivo : null;
$cert_es_pdf  = $cert_archivo && strtolower(pathinfo($cert_archivo, PATHINFO_EXTENSION)) === 'pdf';


$page_title = $es_propio ? "Mi perfil" : "Perfil de " . $tecnico['nombre_usuario'];
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/portfolio.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">

			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>

			<div class="perfil-card">

				<div class="perfil-encabezado">
					<div class="perfil-identidad">
						<span class="perfil-avatar">
							<img src="<?php echo htmlspecialchars($tecnico['foto_perfil'] ?: 'img/logo.png'); ?>" alt="">
						</span>
						<div>
							<p class="perfil-nombre">@<?php echo htmlspecialchars($tecnico['nombre_usuario']); ?><?php if (!empty($tecnico['verificado'])) { echo insignia_verificada(); } ?></p>
							<div class="perfil-calificacion">
								<div class="perfil-estrellas" aria-label="Calificación: <?php echo $cantidad_calificaciones > 0 ? $promedio_texto . ' de 5' : 'sin calificaciones'; ?>">
									<?php for ($i = 1; $i <= 5; $i++): ?>
									<?php
									// Cuánto de esta estrella está llena (0 a 1). El dibujo de la estrella
									// ocupa del 2 al 22 de un ancho de 24, así que el recorte se ajusta a eso.
									$relleno = max(0, min(1, $promedio_calificacion - ($i - 1)));
									$ancho_relleno = $relleno >= 1 ? 100 : round((2 + $relleno * 20) / 24 * 100, 1);
									?>
									<span class="perfil-estrella">
										<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14.9 8.6 L22 9.3 L16.7 14 L18.2 21 L12 17.3 L5.8 21 L7.3 14 L2 9.3 L9.1 8.6 Z"/></svg>
										<?php if ($relleno > 0): ?>
										<span class="perfil-estrella-relleno" style="width: <?php echo $ancho_relleno; ?>%;">
											<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14.9 8.6 L22 9.3 L16.7 14 L18.2 21 L12 17.3 L5.8 21 L7.3 14 L2 9.3 L9.1 8.6 Z"/></svg>
										</span>
										<?php endif; ?>
									</span>
									<?php endfor; ?>
								</div>
								<?php if ($cantidad_calificaciones > 0): ?>
								<span class="perfil-puntaje"><?php echo $promedio_texto; ?></span>
								<span class="perfil-cantidad"><?php echo $cantidad_calificaciones; ?> <?php echo $cantidad_calificaciones === 1 ? 'calificación' : 'calificaciones'; ?></span>
								<?php else: ?>
								<span class="perfil-cantidad">Sin calificaciones todavía</span>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<div class="perfil-tipo">
						<span class="perfil-tipo-label">TÉCNICO</span>
						<?php
												// Solo se ve la especialidad principal; las demás van en una solapa que se despliega.
												$esp_principal = $especialidades_perfil[0];
												$esp_otras     = array_slice($especialidades_perfil, 1);
												?>
												<div class="perfil-especialidades-lista">
													<span class="perfil-especialidad-box"><?php echo htmlspecialchars(etiqueta_especialidad($esp_principal['especialidad'])); ?></span>

													<?php if (!empty($esp_otras)): ?>
													<button type="button" class="perfil-solapa" id="perfil-esp-toggle" aria-expanded="false" aria-controls="perfil-esp-mas">
														<span>+<?php echo count($esp_otras); ?> <?php echo count($esp_otras) === 1 ? 'especialidad más' : 'especialidades más'; ?></span>
														<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"/></svg>
													</button>
													<div class="perfil-solapa-panel" id="perfil-esp-mas" hidden>
														<?php foreach ($esp_otras as $esp_p): ?>
														<span class="perfil-esp-chip"><?php echo htmlspecialchars(etiqueta_especialidad($esp_p['especialidad'])); ?></span>
														<?php endforeach; ?>
													</div>
													<?php endif; ?>
												</div>
					</div>

					<?php if (!$es_propio): ?>
					<a href="chat.php?destino=<?php echo (int) $tecnico['id_usuario_tecnico']; ?>" class="perfil-btn-mensaje">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5 H20 V16 H9 L4 20 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
						Enviar mensaje
					</a>
					<?php endif; ?>
				</div>

				<p class="perfil-bio"><?php echo htmlspecialchars($tecnico['descripcion'] ?: 'Todavía no agregaste una descripción.'); ?></p>

				<div class="perfil-documentos">

					<?php if ($cert_url): ?>
					<a href="<?php echo htmlspecialchars($cert_url); ?>" target="_blank" rel="noopener" class="perfil-imagen-principal perfil-imagen-principal--certificado" title="Ver certificado en tamaño completo">
						<?php if ($cert_es_pdf): ?>
						<span class="perfil-cert-pdf">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2 H15 L19 6 V22 H6 Z" stroke-linejoin="round"/><path d="M15 2 V6 H19" stroke-linejoin="round"/></svg>
							PDF
						</span>
						<?php else: ?>
						<img src="<?php echo htmlspecialchars($cert_url); ?>" alt="Certificado de técnico">
						<?php endif; ?>
					</a>

					<p class="perfil-cert-estado">
						Certificado
						<?php if ($tecnico['estado_verificacion'] === 'verificado'): ?>
						<span class="perfil-cv-verificado">· Verificado</span>
						<?php elseif ($es_propio && $tecnico['estado_verificacion'] === 'pendiente'): ?>
						<span class="perfil-cv-oculto">· Pendiente de revisión</span>
						<?php elseif ($es_propio && $tecnico['estado_verificacion'] === 'rechazado'): ?>
						<span class="perfil-cv-rechazado">· Rechazado</span>
						<?php endif; ?>
					</p>
					<?php else: ?>
					<div class="perfil-imagen-principal">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/></svg>
					</div>
					<?php endif; ?>

					<?php
									// Se muestra el certificado principal resumido; el resto y el detalle se ven con "Revisar".
									$total_certs  = ($cert_url ? 1 : 0) + count($certificados_extra);
									$cant_extra   = count($certificados_extra);
									?>
									<?php if ($total_certs > 0): ?>
									<div class="perfil-cert-resumen">
										<?php if ($cant_extra > 0): ?>
										<span class="perfil-cert-mas">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8 V5 a2 2 0 0 0 -2 -2 H5 a2 2 0 0 0 -2 2 V14 a2 2 0 0 0 2 2 H8" stroke-linecap="round" stroke-linejoin="round"/></svg>
											Hay <?php echo $cant_extra; ?> certificado<?php echo $cant_extra === 1 ? '' : 's'; ?> más
										</span>
										<?php endif; ?>
										<button type="button" class="perfil-cert-revisar" id="perfil-certs-abrir" aria-haspopup="dialog">Revisar</button>
									</div>
									<?php endif; ?>

					<?php if ($tecnico['cv_archivo'] && ($es_propio || $tecnico['cv_visible'])): ?>
					<a href="uploads/cv/<?php echo htmlspecialchars($tecnico['cv_archivo']); ?>" target="_blank" rel="noopener" class="perfil-cv">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 H15 L19 6 V22 H6 Z" stroke-linejoin="round"/><path d="M15 2 V6 H19" stroke-linejoin="round"/></svg>
						Ver CV
						<?php if ($es_propio && !$tecnico['cv_visible']): ?>
						<span class="perfil-cv-oculto">(oculto para otros)</span>
						<?php endif; ?>
					</a>
					<?php elseif ($es_propio && !$tecnico['cv_archivo']): ?>
					<a href="ajustes.php#privacidad" class="perfil-cv perfil-cv--vacio">Todavía no subiste tu CV</a>
					<?php endif; ?>

				</div>

			</div>

			<?php
			/* =========================================================
			   GALERÍA DE TRABAJOS
			   Recorre $portfolio (publicaciones reales de la base). Si es
			   el perfil propio, el primer casillero es el botón de
			   publicar. Al tocar una foto se abre en grande con su
			   descripción (y, si es la tuya, el botón de eliminar).
			   ========================================================= */
			?>
<h2 class="perfil-seccion-titulo">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
	Trabajos realizados
</h2>

<?php if ($aviso_portfolio): ?>
<p class="perfil-aviso perfil-aviso--<?php echo $aviso_portfolio[0]; ?>" role="status"><?php echo htmlspecialchars($aviso_portfolio[1]); ?></p>
<?php endif; ?>

			<div class="perfil-portfolio">

				<?php if ($es_propio): ?>
<button type="button" class="perfil-portfolio-item perfil-portfolio-add" id="portfolio-abrir-subir" aria-label="Publicar foto de un trabajo">
	<span class="perfil-portfolio-add-icono">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5 V19 M5 12 H19" stroke-linecap="round"/></svg>
	</span>
	<span class="perfil-portfolio-add-texto">Publicar</span>
</button>
				<?php endif; ?>

				<?php if (empty($portfolio)): ?>
					<?php
					// Sin publicaciones todavía: 3 casilleros de ejemplo con el
					// ícono genérico, igual que en el diseño.
					for ($i = 0; $i < 3; $i++):
					?>
					<div class="perfil-portfolio-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/></svg>
					</div>
					<?php endfor; ?>
				<?php else: ?>
					<?php foreach ($portfolio as $post): ?>
					<button type="button" class="perfil-portfolio-item perfil-portfolio-post"
						data-id="<?php echo (int) $post['id_proyecto']; ?>"
						data-fotos="<?php echo htmlspecialchars(json_encode($post['fotos'], JSON_UNESCAPED_SLASHES)); ?>"
						data-desc="<?php echo htmlspecialchars($post['descripcion'] ?? ''); ?>"
						data-fecha="<?php echo $post['fecha_realizacion'] ? (new DateTime($post['fecha_realizacion']))->format('d/m/Y') : ''; ?>"
						aria-label="Ver publicación<?php echo count($post['fotos']) > 1 ? ' (' . count($post['fotos']) . ' fotos)' : ''; ?>">
						<img src="<?php echo htmlspecialchars($post['fotos'][0]); ?>" alt="Foto de trabajo realizado">
						<?php if (count($post['fotos']) > 1): ?>
						<span class="perfil-portfolio-multi" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8 V5 a2 2 0 0 0 -2 -2 H5 a2 2 0 0 0 -2 2 V14 a2 2 0 0 0 2 2 H8" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php echo count($post['fotos']); ?>
						</span>
						<?php endif; ?>
					</button>
					<?php endforeach; ?>
				<?php endif; ?>

			</div>

			<?php if ($es_propio): ?>
			<dialog id="portfolio-subir" class="portfolio-dialog" aria-labelledby="portfolio-titulo">
				<form action="procesar/procesar_portfolio.php" method="POST" enctype="multipart/form-data" class="portfolio-form" id="portfolio-form">
					<input type="hidden" name="accion" value="subir">

					<div class="portfolio-cabecera">
						<h3 id="portfolio-titulo">Nueva publicación</h3>
						<button type="button" class="portfolio-cerrar" data-cerrar aria-label="Cerrar">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6 L18 18 M18 6 L6 18" stroke-linecap="round"/></svg>
						</button>
					</div>

					<div class="portfolio-cuerpo">

						<!-- Las fotos llegan por el selector de archivos, ya sea elegidas de la galería
						     o sacadas con la cámara de la app (que las deja cargadas en este mismo campo).
						     Se pueden juntar hasta 10 fotos en una publicación: en el perfil se ven como una ruleta. -->
						<input type="file" name="foto[]" id="portfolio-foto" accept="image/jpeg,image/png,image/webp" multiple hidden>
						<!-- Plan B si el navegador no deja abrir la cámara dentro de la página: abre la cámara del celular. -->
						<input type="file" id="portfolio-foto-camara" accept="image/*" capture="environment" hidden>

						<p class="portfolio-ayuda" id="portfolio-ayuda">Mostrá un trabajo que hiciste. Podés subir hasta 10 fotos y se ven como una ruleta.</p>

						<div class="portfolio-elegir" id="portfolio-elegir">
							<button type="button" class="portfolio-opcion" id="portfolio-btn-camara">
								<span class="portfolio-opcion-icono">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8 H7.5 L9 5.5 H15 L16.5 8 H20 V19 H4 Z" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5"/></svg>
								</span>
								Tomar foto
								<small>Abrí la cámara</small>
							</button>
							<button type="button" class="portfolio-opcion" id="portfolio-btn-galeria">
								<span class="portfolio-opcion-icono">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/></svg>
								</span>
								Elegir fotos
								<small>Una o varias</small>
							</button>
						</div>

						<!-- Cámara dentro de la app -->
						<div class="portfolio-camara" id="portfolio-camara" hidden>
							<video id="portfolio-video" class="portfolio-video" autoplay playsinline muted></video>
							<div class="portfolio-camara-controles">
								<button type="button" class="portfolio-camara-lateral" id="portfolio-cam-cancelar" aria-label="Cerrar cámara">Volver</button>
								<button type="button" class="portfolio-disparador" id="portfolio-cam-capturar" aria-label="Sacar foto"></button>
								<button type="button" class="portfolio-camara-lateral" id="portfolio-cam-girar" aria-label="Cambiar de cámara">Girar</button>
							</div>
						</div>

						<!-- Fotos elegidas: miniaturas con su botón para quitarlas -->
						<div class="portfolio-miniaturas" id="portfolio-miniaturas" hidden></div>
						<p class="portfolio-miniaturas-cuenta" id="portfolio-miniaturas-cuenta" hidden></p>

						<div class="portfolio-mas" id="portfolio-mas" hidden>
							<button type="button" class="portfolio-cambiar" id="portfolio-mas-camara">+ Tomar otra foto</button>
							<button type="button" class="portfolio-cambiar" id="portfolio-mas-galeria">+ Agregar del dispositivo</button>
							<button type="button" class="portfolio-cambiar" id="portfolio-cambiar">Quitar todas</button>
						</div>

						<p class="portfolio-mensaje" id="portfolio-mensaje" role="alert" hidden></p>

						<div class="portfolio-campo">
							<label for="portfolio-descripcion">Descripción <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9aa3b5;">(opcional)</span></label>
							<textarea name="descripcion" id="portfolio-descripcion" maxlength="500" rows="3" placeholder="Contá qué trabajo hiciste..."></textarea>
							<span class="portfolio-contador" id="portfolio-contador">0 / 500</span>
						</div>
					</div>

					<div class="portfolio-acciones">
						<button type="button" class="portfolio-btn" data-cerrar>Cancelar</button>
						<button type="submit" class="portfolio-btn portfolio-btn--primario">Publicar</button>
					</div>
				</form>
			</dialog>

			<?php endif; ?>

			<?php if ($total_certs > 0): ?>
			<!-- Revisar certificados: vista detallada con todos los certificados del técnico -->
			<dialog id="perfil-certs-dialog" class="portfolio-dialog" aria-labelledby="perfil-certs-titulo">
				<div class="portfolio-cabecera">
					<h3 id="perfil-certs-titulo">Certificados</h3>
					<button type="button" class="portfolio-cerrar" data-cerrar aria-label="Cerrar">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6 L18 18 M18 6 L6 18" stroke-linecap="round"/></svg>
					</button>
				</div>

				<div class="portfolio-cuerpo">
					<ul class="perfil-cert-detalle-lista">
						<?php if ($cert_url): ?>
						<li class="perfil-cert-detalle">
							<a class="perfil-cert-detalle-mini" href="<?php echo htmlspecialchars($cert_url); ?>" target="_blank" rel="noopener" title="Abrir en tamaño completo">
								<?php if ($cert_es_pdf): ?><span>PDF</span><?php else: ?><img src="<?php echo htmlspecialchars($cert_url); ?>" alt="Certificado principal"><?php endif; ?>
							</a>
							<div class="perfil-cert-detalle-info">
								<strong>Certificado principal</strong>
								<span>
									Con el que se registró
									<?php if ($tecnico['estado_verificacion'] === 'verificado'): ?>
									<span class="perfil-cv-verificado">· Verificado</span>
									<?php elseif ($es_propio && $tecnico['estado_verificacion'] === 'pendiente'): ?>
									<span class="perfil-cv-oculto">· Pendiente de revisión</span>
									<?php elseif ($es_propio && $tecnico['estado_verificacion'] === 'rechazado'): ?>
									<span class="perfil-cv-rechazado">· Rechazado</span>
									<?php endif; ?>
								</span>
							</div>
							<a class="perfil-cert-detalle-abrir" href="<?php echo htmlspecialchars($cert_url); ?>" target="_blank" rel="noopener">Abrir</a>
						</li>
						<?php endif; ?>

						<?php foreach ($certificados_extra as $cert_e): ?>
						<?php
						$cert_e_url = 'uploads/certificados/' . $cert_e['archivo'];
						$cert_e_pdf = strtolower(pathinfo($cert_e['archivo'], PATHINFO_EXTENSION)) === 'pdf';
						$cert_e_tit = $cert_e['nombre'] !== '' ? $cert_e['nombre'] : (!empty($cert_e['especialidad']) ? etiqueta_especialidad($cert_e['especialidad']) : 'Certificado técnico');
						$cert_e_datos = array_filter([
							!empty($cert_e['especialidad']) ? etiqueta_especialidad($cert_e['especialidad']) : '',
							!empty($cert_e['anio']) ? 'Año ' . (int) $cert_e['anio'] : '',
							!empty($cert_e['institucion']) ? $cert_e['institucion'] : '',
						]);
						?>
						<li class="perfil-cert-detalle">
							<a class="perfil-cert-detalle-mini" href="<?php echo htmlspecialchars($cert_e_url); ?>" target="_blank" rel="noopener" title="Abrir en tamaño completo">
								<?php if ($cert_e_pdf): ?><span>PDF</span><?php else: ?><img src="<?php echo htmlspecialchars($cert_e_url); ?>" alt="<?php echo htmlspecialchars($cert_e_tit); ?>"><?php endif; ?>
							</a>
							<div class="perfil-cert-detalle-info">
								<strong><?php echo htmlspecialchars($cert_e_tit); ?></strong>
								<span>
									<?php echo htmlspecialchars(!empty($cert_e_datos) ? implode(' · ', $cert_e_datos) : 'Certificado adicional'); ?>
									<?php if ($cert_e['estado'] === 'aprobado'): ?>
									<span class="perfil-cv-verificado">· Verificado</span>
									<?php elseif ($es_propio && $cert_e['estado'] === 'pendiente'): ?>
									<span class="perfil-cv-oculto">· Pendiente de aprobación</span>
									<?php elseif ($es_propio && $cert_e['estado'] === 'rechazado'): ?>
									<span class="perfil-cv-rechazado">· Rechazado</span>
									<?php endif; ?>
								</span>
							</div>
							<a class="perfil-cert-detalle-abrir" href="<?php echo htmlspecialchars($cert_e_url); ?>" target="_blank" rel="noopener">Abrir</a>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="portfolio-acciones">
					<button type="button" class="portfolio-btn" data-cerrar>Cerrar</button>
				</div>
			</dialog>
			<?php endif; ?>

			<script>
				// Solapa de especialidades y botón "Revisar" de certificados
				(function () {
					var toggle = document.getElementById('perfil-esp-toggle');
					var panel  = document.getElementById('perfil-esp-mas');

					if (toggle && panel) {
						toggle.addEventListener('click', function () {
							var abierto = toggle.getAttribute('aria-expanded') === 'true';
							toggle.setAttribute('aria-expanded', abierto ? 'false' : 'true');
							panel.hidden = abierto;
						});
					}

					var revisar = document.getElementById('perfil-certs-abrir');
					var dialogo = document.getElementById('perfil-certs-dialog');

					if (revisar && dialogo) {
						revisar.addEventListener('click', function () { dialogo.showModal(); });
					}
				})();
			</script>

			<!-- Ver una publicación: si tiene varias fotos se pasan como una ruleta (deslizando, con las flechas o con los puntitos) -->
			<dialog id="portfolio-ver" class="portfolio-dialog portfolio-dialog--ver" aria-label="Publicación">
				<div class="portfolio-ver-marco">
					<div class="portfolio-carrusel" id="portfolio-carrusel">
						<div class="portfolio-carrusel-pista" id="portfolio-carrusel-pista" tabindex="0" aria-live="polite"></div>
						<button type="button" class="portfolio-carrusel-flecha portfolio-carrusel-flecha--ant" id="portfolio-carrusel-ant" aria-label="Foto anterior">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><polyline points="15 5 8 12 15 19" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<button type="button" class="portfolio-carrusel-flecha portfolio-carrusel-flecha--sig" id="portfolio-carrusel-sig" aria-label="Foto siguiente">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><polyline points="9 5 16 12 9 19" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<span class="portfolio-carrusel-contador" id="portfolio-carrusel-contador"></span>
					</div>
					<div class="portfolio-carrusel-puntos" id="portfolio-carrusel-puntos"></div>
					<button type="button" class="portfolio-ver-cerrar" data-cerrar aria-label="Cerrar">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6 L18 18 M18 6 L6 18" stroke-linecap="round"/></svg>
					</button>
				</div>
				<div class="portfolio-ver-info">
					<p id="portfolio-ver-desc" class="portfolio-ver-desc"></p>
					<p id="portfolio-ver-fecha" class="portfolio-ver-fecha"></p>
				</div>
				<div class="portfolio-acciones">
					<?php if ($es_propio): ?>
					<form action="procesar/procesar_portfolio.php" method="POST" onsubmit="return confirm('¿Eliminar esta publicación?');">
						<input type="hidden" name="accion" value="eliminar">
						<input type="hidden" name="id_proyecto" id="portfolio-ver-id" value="">
						<button type="submit" class="portfolio-btn portfolio-btn--peligro">Eliminar</button>
					</form>
					<?php endif; ?>
					<button type="button" class="portfolio-btn" data-cerrar>Cerrar</button>
				</div>
			</dialog>


			<script>
			(function () {
				var dlgVer   = document.getElementById('portfolio-ver');
				var dlgSubir = document.getElementById('portfolio-subir');

				// Cerrar con los botones "Cancelar" / "Cerrar" y al tocar el fondo.
				document.querySelectorAll('.portfolio-dialog').forEach(function (dlg) {
					dlg.addEventListener('click', function (e) {
						if (e.target === dlg || e.target.closest('[data-cerrar]')) { dlg.close(); }
					});
				});

				/* ---------- Ver una publicación (ruleta de fotos) ---------- */
				var carrusel  = document.getElementById('portfolio-carrusel');
				var pista     = document.getElementById('portfolio-carrusel-pista');
				var btnAnt    = document.getElementById('portfolio-carrusel-ant');
				var btnSig    = document.getElementById('portfolio-carrusel-sig');
				var contadorC = document.getElementById('portfolio-carrusel-contador');
				var puntos    = document.getElementById('portfolio-carrusel-puntos');
				var totalFotos = 0;

				function fotoActual() {
					return pista.clientWidth ? Math.round(pista.scrollLeft / pista.clientWidth) : 0;
				}
				function irAFoto(i) {
					i = Math.max(0, Math.min(totalFotos - 1, i));
					pista.scrollTo({ left: i * pista.clientWidth, behavior: 'smooth' });
				}
				function actualizarRuleta() {
					var i = fotoActual();
					contadorC.textContent = (i + 1) + ' / ' + totalFotos;
					btnAnt.disabled = i <= 0;
					btnSig.disabled = i >= totalFotos - 1;
					puntos.querySelectorAll('button').forEach(function (p, n) {
						p.classList.toggle('portfolio-carrusel-punto--activo', n === i);
					});
				}

				pista.addEventListener('scroll', actualizarRuleta);
				btnAnt.addEventListener('click', function () { irAFoto(fotoActual() - 1); });
				btnSig.addEventListener('click', function () { irAFoto(fotoActual() + 1); });
				dlgVer.addEventListener('keydown', function (e) {
					if (e.key === 'ArrowLeft')  { irAFoto(fotoActual() - 1); }
					if (e.key === 'ArrowRight') { irAFoto(fotoActual() + 1); }
				});
				dlgVer.addEventListener('close', function () {
					pista.innerHTML = '';
					puntos.innerHTML = '';
				});

				document.querySelectorAll('.perfil-portfolio-post').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var fotos = [];
						try { fotos = JSON.parse(btn.dataset.fotos || '[]'); } catch (e) { fotos = []; }
						if (!fotos.length) { return; }

						pista.innerHTML = '';
						puntos.innerHTML = '';
						totalFotos = fotos.length;

						fotos.forEach(function (src, n) {
							var img = document.createElement('img');
							img.src = src;
							img.alt = 'Foto ' + (n + 1) + ' de ' + fotos.length + ' del trabajo realizado';
							img.className = 'portfolio-carrusel-foto';
							img.draggable = false;
							pista.appendChild(img);

							var punto = document.createElement('button');
							punto.type = 'button';
							punto.className = 'portfolio-carrusel-punto';
							punto.setAttribute('aria-label', 'Ir a la foto ' + (n + 1));
							punto.addEventListener('click', function () { irAFoto(n); });
							puntos.appendChild(punto);
						});

						// Con una sola foto no hace falta nada de la ruleta.
						carrusel.classList.toggle('portfolio-carrusel--una', fotos.length === 1);
						puntos.hidden = fotos.length === 1;

						document.getElementById('portfolio-ver-desc').textContent = btn.dataset.desc;
						document.getElementById('portfolio-ver-fecha').textContent = btn.dataset.fecha ? 'Publicado el ' + btn.dataset.fecha : '';
						var campoId = document.getElementById('portfolio-ver-id');
						if (campoId) { campoId.value = btn.dataset.id; }

						dlgVer.showModal();
						pista.scrollLeft = 0;
						actualizarRuleta();
					});
				});

				/* ---------- Publicar: cámara propia o fotos del dispositivo ---------- */
				var abrir = document.getElementById('portfolio-abrir-subir');
				if (abrir && dlgSubir) {
					var MAX_FOTOS   = 10;
					var MAX_BYTES   = 5 * 1024 * 1024;
					var form        = document.getElementById('portfolio-form');
					var inputFoto   = document.getElementById('portfolio-foto');
					var inputCamara = document.getElementById('portfolio-foto-camara');
					var miniaturas  = document.getElementById('portfolio-miniaturas');
					var cuenta      = document.getElementById('portfolio-miniaturas-cuenta');
					var elegir      = document.getElementById('portfolio-elegir');
					var mas         = document.getElementById('portfolio-mas');
					var panelCam    = document.getElementById('portfolio-camara');
					var video       = document.getElementById('portfolio-video');
					var btnCambiar  = document.getElementById('portfolio-cambiar');
					var btnMasCam   = document.getElementById('portfolio-mas-camara');
					var btnMasGal   = document.getElementById('portfolio-mas-galeria');
					var mensaje     = document.getElementById('portfolio-mensaje');
					var textoDesc   = document.getElementById('portfolio-descripcion');
					var contador    = document.getElementById('portfolio-contador');
					var ayuda       = document.getElementById('portfolio-ayuda');
					var stream      = null;
					var facing      = 'environment';   // cámara trasera primero (es para fotos de trabajos)
					var seleccion   = [];              // las fotos elegidas hasta ahora (File)
					var urls        = [];              // para liberar las vistas previas

					function actualizarContador() {
						var n = textoDesc.value.length, max = textoDesc.maxLength;
						contador.textContent = n + ' / ' + max;
						contador.classList.toggle('portfolio-contador--limite', n >= max - 30);
					}
					textoDesc.addEventListener('input', actualizarContador);

					function avisar(texto) {
						mensaje.textContent = texto || '';
						mensaje.hidden = !texto;
					}

					function apagarCamara() {
						if (stream) {
							stream.getTracks().forEach(function (t) { t.stop(); });
							stream = null;
						}
						video.srcObject = null;
						panelCam.hidden = true;
						form.classList.remove('portfolio-form--camara');
					}

					// Deja las fotos en el campo "foto[]" del formulario, igual que si se hubieran elegido a mano.
					function sincronizarInput() {
						var dt = new DataTransfer();
						seleccion.forEach(function (f) { dt.items.add(f); });
						inputFoto.files = dt.files;
					}

					function dibujar() {
						urls.forEach(function (u) { URL.revokeObjectURL(u); });
						urls = [];
						miniaturas.innerHTML = '';

						seleccion.forEach(function (archivo, n) {
							var url = URL.createObjectURL(archivo);
							urls.push(url);

							var caja = document.createElement('div');
							caja.className = 'portfolio-miniatura';

							var img = document.createElement('img');
							img.src = url;
							img.alt = 'Foto ' + (n + 1);
							caja.appendChild(img);

							var quitar = document.createElement('button');
							quitar.type = 'button';
							quitar.className = 'portfolio-miniatura-quitar';
							quitar.setAttribute('aria-label', 'Quitar la foto ' + (n + 1));
							quitar.textContent = '×';
							quitar.addEventListener('click', function () {
								seleccion.splice(n, 1);
								sincronizarInput();
								dibujar();
							});
							caja.appendChild(quitar);

							miniaturas.appendChild(caja);
						});

						var hay = seleccion.length > 0;
						miniaturas.hidden = !hay;
						cuenta.hidden = !hay;
						cuenta.textContent = seleccion.length + ' / ' + MAX_FOTOS + ' fotos';
						elegir.hidden = hay || !panelCam.hidden;
						mas.hidden = !hay;
						btnMasCam.hidden = seleccion.length >= MAX_FOTOS;
						btnMasGal.hidden = seleccion.length >= MAX_FOTOS;
						ayuda.hidden = hay;
					}

					function agregarArchivos(lista) {
						avisar('');
						Array.prototype.forEach.call(lista, function (archivo) {
							if (seleccion.length >= MAX_FOTOS) {
								avisar('Podés subir hasta ' + MAX_FOTOS + ' fotos por publicación.');
								return;
							}
							if (!/^image\/(jpeg|png|webp)$/.test(archivo.type) && !/\.(jpe?g|png|webp)$/i.test(archivo.name)) {
								avisar('Las fotos tienen que ser JPG, PNG o WEBP.');
								return;
							}
							if (archivo.size > MAX_BYTES) {
								avisar('Una de las fotos pesa más de 5 MB y no se agregó.');
								return;
							}
							seleccion.push(archivo);
						});
						sincronizarInput();
						dibujar();
					}

					function reiniciar() {
						apagarCamara();
						seleccion = [];
						inputFoto.value = '';
						inputCamara.value = '';
						textoDesc.value = '';
						actualizarContador();
						avisar('');
						dibujar();
					}

					function iniciarCamara() {
						apagarCamara();
						return navigator.mediaDevices.getUserMedia({
							video: { facingMode: { ideal: facing }, width: { ideal: 1920 }, height: { ideal: 1920 } },
							audio: false
						}).then(function (flujo) {
							stream = flujo;
							video.srcObject = flujo;
							// La cámara frontal se ve como espejo, como en Instagram.
							video.classList.toggle('portfolio-video--espejo', facing === 'user');
							elegir.hidden = true;
							mas.hidden = true;
							panelCam.hidden = false;
							form.classList.add('portfolio-form--camara');
							avisar('');
						});
					}

					function abrirCamara() {
						// La cámara dentro de la página solo funciona con https o en localhost.
						if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.isSecureContext) {
							inputCamara.click();   // plan B: cámara nativa del celular
							return;
						}
						iniciarCamara().catch(function (err) {
							if (err && err.name === 'NotAllowedError') {
								avisar('No diste permiso para usar la cámara. Activalo en el navegador o elegí fotos del dispositivo.');
								dibujar();
							} else {
								inputCamara.click();   // sin cámara disponible: plan B
							}
						});
					}

					function abrirGaleria() { inputFoto.click(); }

					abrir.addEventListener('click', function () {
						reiniciar();
						dlgSubir.showModal();
					});

					// Elegir fotos que ya están en el dispositivo (una o varias). El selector
					// reemplaza el campo, así que se vuelven a juntar con las que ya había.
					document.getElementById('portfolio-btn-galeria').addEventListener('click', abrirGaleria);
					btnMasGal.addEventListener('click', abrirGaleria);
					inputFoto.addEventListener('change', function () {
						var nuevas = Array.prototype.slice.call(inputFoto.files);
						sincronizarInput();                 // vuelve a dejar solo las que ya estaban...
						agregarArchivos(nuevas);            // ...y suma las recién elegidas
					});

					// Tomar fotos con la cámara de la app (se puede tomar una tras otra).
					document.getElementById('portfolio-btn-camara').addEventListener('click', abrirCamara);
					btnMasCam.addEventListener('click', abrirCamara);
					inputCamara.addEventListener('change', function () {
						if (inputCamara.files[0]) { agregarArchivos([inputCamara.files[0]]); }
						inputCamara.value = '';
					});

					document.getElementById('portfolio-cam-girar').addEventListener('click', function () {
						facing = facing === 'environment' ? 'user' : 'environment';
						iniciarCamara().catch(function () {
							facing = facing === 'environment' ? 'user' : 'environment';
							avisar('No se pudo cambiar de cámara.');
							iniciarCamara().catch(function () {});
						});
					});

					document.getElementById('portfolio-cam-cancelar').addEventListener('click', function () {
						apagarCamara();
						dibujar();
					});

					// Sacar la foto: se dibuja el cuadro actual del video y se guarda como JPG.
					document.getElementById('portfolio-cam-capturar').addEventListener('click', function () {
						if (!video.videoWidth) { return; }
						var ancho = video.videoWidth, alto = video.videoHeight;
						var maximo = 1920;                       // se achica para no pasar el límite de peso
						if (Math.max(ancho, alto) > maximo) {
							var escala = maximo / Math.max(ancho, alto);
							ancho = Math.round(ancho * escala);
							alto  = Math.round(alto * escala);
						}
						var lienzo = document.createElement('canvas');
						lienzo.width = ancho;
						lienzo.height = alto;
						lienzo.getContext('2d').drawImage(video, 0, 0, ancho, alto);
						lienzo.toBlob(function (blob) {
							if (!blob) { avisar('No se pudo sacar la foto. Probá de nuevo.'); return; }
							apagarCamara();
							agregarArchivos([new File([blob], 'foto_' + Date.now() + '.jpg', { type: 'image/jpeg' })]);
						}, 'image/jpeg', 0.9);
					});

					btnCambiar.addEventListener('click', function () {
						seleccion = [];
						sincronizarInput();
						avisar('');
						dibujar();
					});

					// Sin ninguna foto cargada no se envía el formulario.
					form.addEventListener('submit', function (e) {
						if (!seleccion.length) {
							e.preventDefault();
							avisar('Sacá una foto o elegí una del dispositivo antes de publicar.');
						}
					});

					// Al cerrar el cuadro (Cancelar, fondo o Esc) la cámara se apaga.
					dlgSubir.addEventListener('close', apagarCamara);
				}
			})();
			</script>


		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>