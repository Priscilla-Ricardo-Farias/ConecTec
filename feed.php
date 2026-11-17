<?php
// feed.php
//
// Pantalla que ve cualquier usuario ya logueado. Ahora trae las
// publicaciones reales de la tabla "trabajo", en vez de las dos
// tarjetas fijas de ejemplo.
//
// Cada trabajo puede estar publicado por un cliente (trabajo_cliente)
// o por un empleador (trabajo_empleador) — nunca los dos — así que se
// hace un LEFT JOIN con las dos tablas de relación y se elige la
// plantilla de tarjeta según cuál de las dos vino con datos.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/includes/funciones_mensajes.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_especialidad.php';
require_once __DIR__ . '/includes/funciones_publicaciones.php';

// Las empresas sin abono activo no entran al inicio: van a pagar.
exigir_abono_empresa($conexion);

$tipo_propio = $_SESSION['usuario_tipo'] ?? 'cliente';

// Foto de perfil propia para la barra "Postea tu trabajo" (si no tiene, se usa el logo).
$foto_propia = '';
if (isset($_SESSION['usuario_id'])) {
	$stmt_foto = $conexion->prepare('SELECT foto_perfil FROM usuario WHERE id_usuario = ?');
	$id_foto = (int) $_SESSION['usuario_id'];
	$stmt_foto->bind_param('i', $id_foto);
	$stmt_foto->execute();
	$fila_foto = $stmt_foto->get_result()->fetch_assoc();
	$stmt_foto->close();
	$foto_propia = $fila_foto['foto_perfil'] ?? '';
}

$page_title = "Inicio";
$extra_css  = ['css/feed.css'];
include 'includes/header_interno.php';

/* =========================================================
   TRAER LAS PUBLICACIONES
   ========================================================= */

$resultado = $conexion->query(
	"SELECT tr.id_trabajo, tr.titulo, tr.descripcion, tr.caracteristicas,
	        tr.categoria, tr.ubicacion, tr.archivo, tr.fecha_trabajo, tr.fecha_publicacion, tr.estado,

	        tc.id_cliente,
	        uc.id_usuario AS id_usuario_cliente,
	        uc.nombre AS nombre_cliente,
	        " . sql_insignia('uc.id_usuario') . " AS cliente_verificado,

	        te.id_empleador,
	        ue.id_usuario AS id_usuario_empleador,
	        ue.nombre AS nombre_empleador,
	        emp.empresa,
	        emp.verificada,
	        " . sql_insignia('ue.id_usuario') . " AS empresa_verificada

	 FROM trabajo tr
	 LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
	 LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente
	 LEFT JOIN usuario uc         ON uc.id_usuario = c.id_usuario

	 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
	 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador
	 LEFT JOIN usuario ue           ON ue.id_usuario = emp.id_usuario

	 ORDER BY tr.fecha_publicacion DESC"
);

$publicaciones = $resultado->fetch_all(MYSQLI_ASSOC);

// Trae los postulantes de un trabajo puntual, con lo necesario para
// poder linkear al perfil de cada uno y, si el que mira es el dueño
// del trabajo, poder aceptarlo.
function postulantes_de($conexion, $id_trabajo) {
	$stmt = $conexion->prepare(
		"SELECT p.id_postulacion, p.estado AS estado_postulacion,
		        t.id_tecnico, t.id_usuario AS id_usuario_tecnico, u.nombre,
		        " . sql_insignia('t.id_usuario') . " AS verificado
		 FROM postulacion p
		 JOIN tecnico t  ON t.id_tecnico = p.id_tecnico
		 JOIN usuario u ON u.id_usuario = t.id_usuario
		 WHERE p.id_trabajo = ?
		 ORDER BY p.fecha_postulacion DESC"
	);
	$stmt->bind_param('i', $id_trabajo);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}

// Fotos/videos de una publicación (carrusel), en orden. Si la publicación
// es vieja y no tiene filas en trabajo_archivo, se usa trabajo.archivo.
function archivos_de($conexion, $id_trabajo, $archivo_portada = null) {
	$lista = [];
	try {
		$stmt = $conexion->prepare('SELECT archivo FROM trabajo_archivo WHERE id_trabajo = ? ORDER BY orden, id_archivo');
		$stmt->bind_param('i', $id_trabajo);
		$stmt->execute();
		$res = $stmt->get_result();
		while ($f = $res->fetch_assoc()) {
			$lista[] = $f['archivo'];
		}
		$stmt->close();
	} catch (Throwable $e) {
		// si todavía no se corrió migracion_carrusel.sql, se cae a la foto única
	}
	if (empty($lista) && !empty($archivo_portada)) {
		$lista[] = $archivo_portada;
	}
	return $lista;
}

// Datos del usuario logueado para decidir qué mostrar: si es técnico
// (para el botón "Postularme") y a qué trabajos ya se postuló.
$es_tecnico           = ($_SESSION['usuario_tipo'] ?? '') === 'tecnico';
$id_tecnico_actual    = null;
$especialidad_tecnico = '';
$especialidades_tecnico = [];   // todas las especialidades del técnico logueado
$claves_tecnico         = [];
$trabajos_postulados  = [];

if ($es_tecnico) {
	$stmt = $conexion->prepare('SELECT id_tecnico, especialidad FROM tecnico WHERE id_usuario = ?');
	$stmt->bind_param('i', $_SESSION['usuario_id']);
	$stmt->execute();
	$fila_tecnico = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($fila_tecnico) {
		$id_tecnico_actual   = $fila_tecnico['id_tecnico'];
		$especialidad_tecnico = trim($fila_tecnico['especialidad'] ?? '');

		// Un técnico puede tener varias especialidades (Ajustes → Especialidades y certificados).
		$especialidades_tecnico = especialidades_de_tecnico($conexion, $id_tecnico_actual);
		$claves_tecnico         = array_values(array_unique(array_column($especialidades_tecnico, 'clave')));

		$stmt = $conexion->prepare('SELECT id_trabajo FROM postulacion WHERE id_tecnico = ?');
		$stmt->bind_param('i', $id_tecnico_actual);
		$stmt->execute();
		$trabajos_postulados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_trabajo');
		$stmt->close();
	}
}

/* =========================================================
   FILTRO POR ESPECIALIDAD (solo para técnicos)
   Un técnico puede tener varias especialidades y solo puede postularse
   a trabajos de ALGUNA de ellas, así que por defecto el inicio le muestra
   esos. Desde el desplegable "Filtro" puede ver:
     - todas las suyas (por defecto),
     - una sola de las suyas (?esp=informatica),
     - todas las especialidades (?filtro=todas), solo para mirar.
   Clientes y empresas ya no tienen filtro: ven todas las publicaciones.
   (Si algún día se lo quieren devolver, alcanza con poner la variable de
   abajo en true: el código del desplegable para ellos sigue más abajo.)
   ========================================================= */
$filtro_para_no_tecnicos = false;

$tiene_filtro_especialidad = $es_tecnico && $id_tecnico_actual && !empty($claves_tecnico);
$esp_elegida               = clave_especialidad($_GET['esp'] ?? '');
$esp_elegida               = in_array($esp_elegida, $claves_tecnico, true) ? $esp_elegida : '';
$filtrando_especialidad    = $tiene_filtro_especialidad && ($_GET['filtro'] ?? '') !== 'todas';

// Nombres de las especialidades del técnico, para los textos ("Informática, Electricista")
$nombres_especialidades_tecnico = array_map(fn($e) => etiqueta_especialidad($e['especialidad']), $especialidades_tecnico);
$texto_especialidades_tecnico   = implode(', ', $nombres_especialidades_tecnico);

if ($filtrando_especialidad) {
	$claves_a_mostrar = $esp_elegida !== '' ? [$esp_elegida] : $claves_tecnico;
	$publicaciones = array_values(array_filter(
		$publicaciones,
		fn($p) => in_array(clave_especialidad($p['categoria']), $claves_a_mostrar, true)
	));

	$texto_filtro_activo = $esp_elegida !== '' ? etiqueta_especialidad($esp_elegida) : $texto_especialidades_tecnico;
	$etiqueta_boton      = $esp_elegida !== '' ? etiqueta_especialidad($esp_elegida) : (count($claves_tecnico) > 1 ? 'Mis especialidades' : 'Mi especialidad');
} else {
	$texto_filtro_activo = '';
	$etiqueta_boton      = 'Todas';
}

// Un técnico puede postularse a un trabajo si es de alguna de sus especialidades.
function tecnico_puede_postularse($pub, $claves_tecnico) {
	return in_array(clave_especialidad($pub['categoria']), $claves_tecnico, true);
}

/* =========================================================
   FILTRO POR ESPECIALIDAD PARA CLIENTES Y EMPRESAS (desactivado)
   Solo se usa si $filtro_para_no_tecnicos está en true.
   ========================================================= */
$especialidad_filtro = '';

if (!$es_tecnico && $filtro_para_no_tecnicos) {
	$pedida = clave_especialidad($_GET['especialidad'] ?? '');
	if ($pedida === 'otro' || es_especialidad_de_lista($pedida)) {
		$especialidad_filtro = $pedida;
	}
}

if ($especialidad_filtro !== '') {
	$publicaciones = array_values(array_filter(
		$publicaciones,
		fn($p) => categoria_coincide_filtro($p['categoria'], $especialidad_filtro)
	));
}

$etiqueta_filtro = $especialidad_filtro === ''
	? 'Todas'
	: ($especialidad_filtro === 'otro' ? 'Otras' : especialidades_conectec()[$especialidad_filtro]);

/* =========================================================
   TÉCNICO PLUS: los trabajos cercanos a su zona van primero
   Se compara la zona que cargó en Ajustes (pref_ubicacion) contra
   la dirección de cada trabajo; sin GPS, es una coincidencia de
   texto (ej: "Mar del Plata" dentro de "Luro 1234, Mar del Plata").
   ========================================================= */
$es_plus_tecnico = false;
$zona_tecnico    = '';

if ($es_tecnico && $id_tecnico_actual) {
	$es_plus_tecnico = tiene_plan_activo($conexion, (int) $_SESSION['usuario_id']);

	if ($es_plus_tecnico) {
		$stmt = $conexion->prepare('SELECT pref_ubicacion FROM preferencia_usuario WHERE id_usuario = ?');
		$stmt->bind_param('i', $_SESSION['usuario_id']);
		$stmt->execute();
		$fila_zona = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		$zona_tecnico = trim($fila_zona['pref_ubicacion'] ?? '');

		if ($zona_tecnico !== '') {
			foreach ($publicaciones as $i => $p) {
				$publicaciones[$i]['_orden'] = $i;
				$publicaciones[$i]['cerca']  = mb_stripos($p['ubicacion'] ?? '', $zona_tecnico) !== false;
			}
			// Cercanos primero; entre iguales se mantiene el orden por fecha.
			usort($publicaciones, fn($a, $b) => [(int) $b['cerca'], $a['_orden']] <=> [(int) $a['cerca'], $b['_orden']]);
		}
	}
}
?>

<main>
	<section class="feed-page">

		<a href="publicar.php" class="post-bar">
			<span class="post-bar-avatar">
				<img src="<?php echo htmlspecialchars($foto_propia ?: 'img/logo.png'); ?>" alt="">
			</span>
			<span class="post-bar-placeholder">Postea tu trabajo</span>
			<span class="post-bar-add" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8 V16 M8 12 H16" stroke-linecap="round"/></svg>
			</span>
		</a>

		<div class="feed-list">

			<?php if ($es_plus_tecnico && $zona_tecnico === ''): ?>
			<p class="feed-aviso-plus">
				Cargá tu zona en <a href="ajustes.php#preferencias">Ajustes</a> para ver primero los trabajos cerca tuyo.
			</p>
			<?php endif; ?>

			<?php if (($_GET['error'] ?? '') === 'otra_especialidad'): ?>
			<p class="feed-aviso-error">Solo podés postularte a trabajos de tus especialidades.</p>
			<?php elseif (($_GET['error'] ?? '') === 'falta_fecha_trabajo'): ?>
						<p class="feed-aviso-error">Para aceptar al técnico, elegí la fecha acordada para el trabajo.</p>
						<?php elseif (($_GET['error'] ?? '') === 'trabajo_no_disponible'): ?>
									<p class="feed-aviso-error">Ese trabajo ya no está disponible.</p>
									<?php elseif (($_GET['error'] ?? '') === 'no_editable'): ?>
									<p class="feed-aviso-error">Esa publicación no se puede editar ni eliminar: no es tuya o ya se aceptó como trabajo.</p>
									<?php elseif (($_GET['ok'] ?? '') === 'publicacion_editada'): ?>
									<p class="feed-aviso-ok">Guardamos los cambios de tu publicación.</p>
									<?php elseif (($_GET['ok'] ?? '') === 'publicacion_eliminada'): ?>
									<p class="feed-aviso-ok">Eliminamos tu publicación.</p>
									<?php elseif (isset($_GET['postulado'])): ?>
			<p class="feed-aviso-ok">¡Listo! Te postulaste al trabajo.</p>
			<?php endif; ?>

			<div class="feed-toolbar">
					<?php if ($tiene_filtro_especialidad): ?>
					<div class="filtro-wrap">
						<button type="button" class="filtro-btn" id="filtro-btn" aria-haspopup="true" aria-expanded="false" aria-controls="filtro-menu">
							<?php echo htmlspecialchars($etiqueta_boton); ?>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="filtro-menu" id="filtro-menu" hidden>
							<a href="feed.php" class="filtro-opcion<?php echo ($filtrando_especialidad && $esp_elegida === '') ? ' filtro-opcion--activa' : ''; ?>">
								<?php echo count($claves_tecnico) > 1 ? 'Mis especialidades' : 'Mi especialidad'; ?>
								<small><?php echo htmlspecialchars($texto_especialidades_tecnico); ?></small>
							</a>
							<?php if (count($especialidades_tecnico) > 1): ?>
							<?php foreach ($especialidades_tecnico as $esp_t): ?>
							<a href="feed.php?esp=<?php echo urlencode($esp_t['clave']); ?>" class="filtro-opcion<?php echo ($filtrando_especialidad && $esp_elegida === $esp_t['clave']) ? ' filtro-opcion--activa' : ''; ?>">
								Solo <?php echo htmlspecialchars(etiqueta_especialidad($esp_t['especialidad'])); ?>
							</a>
							<?php endforeach; ?>
							<?php endif; ?>
							<a href="feed.php?filtro=todas" class="filtro-opcion<?php echo !$filtrando_especialidad ? ' filtro-opcion--activa' : ''; ?>">
								Todas las especialidades
								<small>Solo para ver: te podés postular a las tuyas</small>
							</a>
						</div>
					</div>
					<?php elseif (!$es_tecnico && $filtro_para_no_tecnicos): ?>
					<div class="filtro-wrap">
						<button type="button" class="filtro-btn" id="filtro-btn" aria-haspopup="true" aria-expanded="false" aria-controls="filtro-menu">
							<?php echo htmlspecialchars($etiqueta_filtro); ?>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="filtro-menu" id="filtro-menu" hidden>
							<a href="feed.php" class="filtro-opcion<?php echo $especialidad_filtro === '' ? ' filtro-opcion--activa' : ''; ?>">Todas las especialidades</a>
							<?php foreach (especialidades_conectec() as $clave_esp => $nombre_esp): ?>
							<a href="feed.php?especialidad=<?php echo urlencode($clave_esp); ?>" class="filtro-opcion<?php echo $especialidad_filtro === $clave_esp ? ' filtro-opcion--activa' : ''; ?>"><?php echo htmlspecialchars($nombre_esp); ?></a>
							<?php endforeach; ?>
							<a href="feed.php?especialidad=otro" class="filtro-opcion<?php echo $especialidad_filtro === 'otro' ? ' filtro-opcion--activa' : ''; ?>">Otras</a>
						</div>
					</div>
					<?php endif; ?>
				</div>

			<?php if (empty($publicaciones)): ?>

			<p style="text-align:center; color:#6b7280; padding:40px 0;">
				<?php if ($filtrando_especialidad): ?>
				Por ahora no hay trabajos de <?php echo htmlspecialchars($texto_filtro_activo); ?>. Cuando se publique uno, te va a aparecer acá.
				<?php elseif ($especialidad_filtro !== ''): ?>
				Por ahora no hay publicaciones de <?php echo htmlspecialchars(mb_strtolower($etiqueta_filtro)); ?>. <a href="feed.php">Ver todas</a>
				<?php else: ?>
				Todavía no hay publicaciones. ¡Sé el primero en postear un trabajo!
				<?php endif; ?>
			</p>

			<?php else: ?>
			<?php foreach ($publicaciones as $pub): ?>

				<?php $es_empresa = $pub['id_empleador'] !== null; ?>

				<?php
				$es_dueno =
					(int) $_SESSION['usuario_id'] === (int) ($pub['id_usuario_cliente'] ?? 0) ||
					(int) $_SESSION['usuario_id'] === (int) ($pub['id_usuario_empleador'] ?? 0);
				?>

				<?php if ($es_empresa): ?>

				<!-- Publicación de una EMPRESA -->
				<article class="post-card post-card--empresa">
					<header class="post-card-header">
						<div class="post-card-quien">
							<h3><?php echo htmlspecialchars($pub['empresa']); ?><?php if (!empty($pub['empresa_verificada'])) { echo insignia_verificada('empresa'); } ?>
							</h3>
							<span class="post-card-fecha-pub">Publicado el <?php echo htmlspecialchars((new DateTime($pub['fecha_publicacion']))->format('d/m/Y, H:i')); ?> hs</span>
						</div>
						<?php if (!empty($pub['cerca'])): ?><span class="post-card-tag-cerca">Cerca tuyo</span><?php endif; ?>
						<?php if (!$es_dueno && !empty($pub['id_usuario_empleador']) && pueden_conversar($tipo_propio, 'empresa')): ?>
						<a href="chat.php?destino=<?php echo (int) $pub['id_usuario_empleador']; ?>" class="post-card-btn-mensaje" aria-label="Enviar mensaje">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5 H20 V16 H9 L4 20 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
						</a>
						<?php endif; ?>
					</header>

					<div class="post-card-body">
						<p class="post-card-titulo">Tipo de técnico <em><?php echo htmlspecialchars(etiqueta_especialidad($pub['categoria'])); ?></em></p>

						<div class="post-card-seccion">
							<h4>Características necesarias</h4>
							<p><?php echo htmlspecialchars($pub['caracteristicas']); ?></p>
						</div>

						<div class="post-card-seccion">
							<h4>Dirección</h4>
							<p><?php echo htmlspecialchars($pub['ubicacion']); ?></p>
						</div>
					</div>

					<?php else: ?>

				<!-- Publicación de un USUARIO / CLIENTE -->
				<article class="post-card post-card--cliente">
					<header class="post-card-header">
						<div class="post-card-quien">
							<h3><?php echo htmlspecialchars($pub['nombre_cliente']); ?><?php if (!empty($pub['cliente_verificado'])) { echo insignia_verificada(); } ?> <span class="post-card-tag">(Cliente)</span></h3>
							<span class="post-card-fecha-pub">Publicado el <?php echo htmlspecialchars((new DateTime($pub['fecha_publicacion']))->format('d/m/Y, H:i')); ?> hs</span>
						</div>
						<?php if (!empty($pub['cerca'])): ?><span class="post-card-tag-cerca">Cerca tuyo</span><?php endif; ?>
						<?php if (!$es_dueno && !empty($pub['id_usuario_cliente']) && pueden_conversar($tipo_propio, 'cliente')): ?>
						<a href="chat.php?destino=<?php echo (int) $pub['id_usuario_cliente']; ?>" class="post-card-btn-mensaje" aria-label="Enviar mensaje">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5 H20 V16 H9 L4 20 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
						</a>
						<?php endif; ?>
					</header>

					<?php $archivos_pub = archivos_de($conexion, (int) $pub['id_trabajo'], $pub['archivo']); $n_arch = count($archivos_pub); ?>
					<div class="post-card-image<?php echo $n_arch > 1 ? ' carrusel' : ''; ?>">
						<?php if ($n_arch > 0): ?>
						<div class="carrusel-pista">
							<?php foreach ($archivos_pub as $i_arch => $nombre_arch):
								$ext_arch = strtolower(pathinfo($nombre_arch, PATHINFO_EXTENSION));
								$es_video_arch = in_array($ext_arch, ['mp4', 'webm', 'mov'], true); ?>
							<div class="carrusel-slide">
								<?php if ($es_video_arch): ?>
								<video src="uploads/publicaciones/<?php echo htmlspecialchars($nombre_arch); ?>" controls preload="metadata"></video>
								<?php else: ?>
								<img src="uploads/publicaciones/<?php echo htmlspecialchars($nombre_arch); ?>" alt="Foto <?php echo $i_arch + 1; ?> del problema" loading="lazy">
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>

						<?php if ($n_arch > 1): ?>
						<span class="carrusel-contador">1 / <?php echo $n_arch; ?></span>
						<button type="button" class="carrusel-flecha carrusel-prev" aria-label="Anterior" disabled>
							<svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>
						</button>
						<button type="button" class="carrusel-flecha carrusel-next" aria-label="Siguiente">
							<svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
						</button>
						<div class="carrusel-puntos">
							<?php for ($pt = 0; $pt < $n_arch; $pt++): ?><span class="carrusel-punto<?php echo $pt === 0 ? ' activo' : ''; ?>"></span><?php endfor; ?>
						</div>
						<?php endif; ?>
						<?php else: ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
							<rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/>
							<circle cx="8.5" cy="8.5" r="1.6"/>
							<path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/>
						</svg>
						<?php endif; ?>
					</div>

					<div class="post-card-body">
						<p class="post-card-titulo">Necesito Técnico de <em><?php echo htmlspecialchars(etiqueta_especialidad($pub['categoria'])); ?></em></p>
						<p class="post-card-desc"><?php echo htmlspecialchars($pub['descripcion']); ?></p>
						<?php if (!empty($pub['fecha_trabajo'])): ?>
						<p class="post-card-fecha-trabajo">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
							Fecha límite: <strong><?php echo htmlspecialchars((new DateTime($pub['fecha_trabajo']))->format('d/m/Y')); ?></strong>
						</p>
						<?php endif; ?>
					</div>

					<?php endif; ?>

					<?php $postulantes = postulantes_de($conexion, $pub['id_trabajo']); ?>

					<footer class="post-card-footer">
						<span class="post-card-postulantes-label">Postulantes</span>
					</footer>

					<ul class="post-card-postulantes-list">
						<?php if (empty($postulantes)): ?>
						<li>Todavía no hay postulantes</li>
						<?php else: ?>
							<?php foreach ($postulantes as $postulante): ?>
							<li class="post-card-postulante-item">
								<a href="perfil.php?id=<?php echo (int) $postulante['id_tecnico']; ?>" class="post-card-postulante-link">
									<?php echo htmlspecialchars($postulante['nombre']); ?><?php if (!empty($postulante['verificado'])) { echo insignia_verificada(); } ?>
								</a>

								<div class="post-card-postulante-derecha">
									<?php if ($postulante['estado_postulacion'] === 'aceptada'): ?>
									<span class="post-card-tag-aceptado"><?php echo $es_empresa ? 'Reunión agendada' : 'Aceptado'; ?></span>

									<?php elseif ($es_dueno && $pub['estado'] === 'abierto' && $postulante['estado_postulacion'] === 'pendiente'): ?>
									<form action="procesar/procesar_aceptar_postulacion.php" method="POST" class="post-card-aceptar-form">
										<input type="hidden" name="id_postulacion" value="<?php echo (int) $postulante['id_postulacion']; ?>">
										<?php if ($es_empresa): ?>
										<input type="date" name="fecha_reunion" required title="Fecha de la reunión">
										<input type="time" name="hora_reunion" required title="Hora de la reunión">
										<button type="submit" class="post-card-aceptar-btn">Agendar reunión</button>
										<?php else: ?>
																<?php if (empty($pub['fecha_trabajo'])): ?>
																<input type="date" name="fecha_trabajo" required min="<?php echo date('Y-m-d'); ?>" title="Fecha acordada para el trabajo (el cliente no puso fecha límite)">
																<?php endif; ?>
																<button type="submit" class="post-card-aceptar-btn">Aceptar</button>
																<?php endif; ?>
									</form>
									<?php endif; ?>

									<?php if ($es_dueno): ?>
									<a href="chat.php?destino=<?php echo (int) $postulante['id_usuario_tecnico']; ?>" class="post-card-icon-btn post-card-postulante-chat" aria-label="Enviar mensaje a <?php echo htmlspecialchars($postulante['nombre']); ?>" title="Enviar mensaje">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5 H20 V16 H9 L4 20 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
									</a>
									<?php endif; ?>
								</div>
							</li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>

					<?php // El dueño puede editar o eliminar su publicación mientras no se haya aceptado como trabajo. ?>
					<?php if ($es_dueno && publicacion_propia_editable($conexion, $pub['id_trabajo'], $_SESSION['usuario_id'])): ?>
					<div class="post-card-acciones-dueno">
						<a href="editar_publicacion.php?id=<?php echo (int) $pub['id_trabajo']; ?>" class="post-card-accion">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20 H8 L19 9 a2.1 2.1 0 0 0 -3 -3 L5 17 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
							Editar
						</a>
						<form action="procesar/procesar_eliminar_publicacion.php" method="POST" onsubmit="return confirm('¿Eliminar esta publicación? Se borra para todos y se les avisa a los técnicos que ya se postularon.');">
							<input type="hidden" name="id_trabajo" value="<?php echo (int) $pub['id_trabajo']; ?>">
							<button type="submit" class="post-card-accion post-card-accion--peligro">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7 H20 M10 11 V17 M14 11 V17 M6 7 L7 20 H17 L18 7 M9 7 V4 H15 V7" stroke-linecap="round" stroke-linejoin="round"/></svg>
								Eliminar
							</button>
						</form>
					</div>
					<?php endif; ?>

					<?php if ($es_tecnico && !$es_dueno): ?>
						<?php if ($pub['estado'] !== 'abierto'): ?>
						<p class="post-card-cerrado-aviso">Este trabajo ya no está disponible</p>
						<?php elseif (!tecnico_puede_postularse($pub, $claves_tecnico)): ?>
									<p class="post-card-cerrado-aviso">Es de otra especialidad: solo podés postularte a trabajos de <?php echo htmlspecialchars($texto_especialidades_tecnico); ?></p>
						<?php else: ?>
							<?php $ya_postulado = in_array($pub['id_trabajo'], $trabajos_postulados); ?>
							<form action="procesar/procesar_postulacion.php" method="POST">
								<input type="hidden" name="id_trabajo" value="<?php echo (int) $pub['id_trabajo']; ?>">
								<button type="submit" class="post-card-btn-postularse" <?php echo $ya_postulado ? 'disabled' : ''; ?>>
									<?php echo $ya_postulado ? 'Ya te postulaste' : 'Postularme'; ?>
								</button>
							</form>
						<?php endif; ?>
					<?php endif; ?>
				</article>

			<?php endforeach; ?>
			<?php endif; ?>

		</div>

	</section>
</main>

<script>
(function () {
	var btn  = document.getElementById('filtro-btn');
	var menu = document.getElementById('filtro-menu');
	if (!btn || !menu) { return; }

	function cerrar() {
		menu.hidden = true;
		btn.setAttribute('aria-expanded', 'false');
	}

	btn.addEventListener('click', function (e) {
		e.stopPropagation();
		var abrir = menu.hidden;
		menu.hidden = !abrir;
		btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
	});
	document.addEventListener('click', cerrar);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrar(); } });
})();
</script>

<script>
// Carrusel de fotos: flechas, puntitos y contador "2 / 5"
(function () {
	document.querySelectorAll('.post-card-image.carrusel').forEach(function (caja) {
		var pista  = caja.querySelector('.carrusel-pista');
		var prev   = caja.querySelector('.carrusel-prev');
		var next   = caja.querySelector('.carrusel-next');
		var puntos = caja.querySelectorAll('.carrusel-punto');
		var cuenta = caja.querySelector('.carrusel-contador');
		var total  = puntos.length;

		function indice() { return Math.round(pista.scrollLeft / pista.clientWidth); }

		function actualizar() {
			var i = Math.min(Math.max(indice(), 0), total - 1);
			puntos.forEach(function (p, n) { p.classList.toggle('activo', n === i); });
			cuenta.textContent = (i + 1) + ' / ' + total;
			prev.disabled = i === 0;
			next.disabled = i === total - 1;
		}

		function ir(i) { pista.scrollTo({ left: i * pista.clientWidth, behavior: 'smooth' }); }

		prev.addEventListener('click', function () { ir(indice() - 1); });
		next.addEventListener('click', function () { ir(indice() + 1); });
		pista.addEventListener('scroll', function () { window.requestAnimationFrame(actualizar); });
	});
})();
</script>

<?php include 'includes/calificaciones_pendientes.php'; ?>
<?php include 'includes/lightbox.php'; ?>
<?php include 'includes/footer.php'; ?>