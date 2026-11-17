<?php
// buscar.php
//
// Resultado del buscador "Buscar técnico" del inicio. Cliente, técnico
// y empresa pueden buscar técnicos por especialidad (ej: "electricista")
// o por nombre de usuario (ej: "juan" o "@juan"). Cada resultado lleva
// al perfil del técnico (perfil.php?id=ID_TECNICO).
//
// Insignias: cliente y técnico llevan el tilde azul; la empresa, el
// escudo naranja (ver insignia_verificada() en funciones_suscripcion.php).

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/includes/funciones_mensajes.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_especialidad.php';

// Las empresas sin abono / sin aprobar no entran acá: van a verificar_cuenta.php.
exigir_empresa_habilitada($conexion);

$tipo_propio = $_SESSION['usuario_tipo'] ?? 'cliente';
$id_propio   = (int) $_SESSION['usuario_id'];

$q = mb_substr(ltrim(trim($_GET['q'] ?? ''), '@'), 0, 60);

$resultados = [];

if ($q !== '') {
	// Los % y _ que escriba el usuario se buscan tal cual, no como comodines.
	$like = '%' . addcslashes($q, '\\%_') . '%';

	// La colación de la base ignora mayúsculas y tildes: "mecanico" encuentra "Mecánico".
	// Además la búsqueda entiende las variaciones: "informático", "informatica/o" o
	// "técnico en informática" encuentran a todos los de Informática, y también a los
	// técnicos que la tengan entre sus varias especialidades.
	$clave_buscada = clave_especialidad($q);

	$sql_base = 'SELECT t.id_tecnico, t.especialidad, t.promedio_calificacion,
		        u.id_usuario, u.nombre, u.foto_perfil,
		        ' . sql_insignia('u.id_usuario') . ' AS verificado
		 FROM tecnico t
		 JOIN usuario u ON u.id_usuario = t.id_usuario
		 WHERE u.estado_cuenta = \'activa\'
		   AND u.id_usuario <> ?
		   AND (u.nombre LIKE ? OR t.especialidad LIKE ?%s)
		 ORDER BY verificado DESC, t.promedio_calificacion DESC, u.nombre ASC
		 LIMIT 50';

	try {
		$stmt = $conexion->prepare(sprintf($sql_base,
			' OR EXISTS (SELECT 1 FROM tecnico_especialidad te WHERE te.id_tecnico = t.id_tecnico AND (te.clave = ? OR te.especialidad LIKE ?))'
		));
		$stmt->bind_param('issss', $id_propio, $like, $like, $clave_buscada, $like);
	} catch (Throwable $e) {
		// todavía no se corrió la migración: se busca como antes
		$stmt = $conexion->prepare(sprintf($sql_base, ''));
		$stmt->bind_param('iss', $id_propio, $like, $like);
	}
	$stmt->execute();
	$resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Todas las especialidades de cada técnico encontrado
	foreach ($resultados as $i => $r) {
		$nombres = array_map(fn($e) => etiqueta_especialidad($e['especialidad']), especialidades_de_tecnico($conexion, $r['id_tecnico']));
		$resultados[$i]['especialidades_texto'] = implode(' · ', $nombres);
	}
}

$page_title = 'Buscar técnicos';
$extra_css  = ['css/feed.css', 'css/panel.css'];
include 'includes/header_interno.php';
?>

<main>
	<section class="feed-page">
		<div class="feed-list">

			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>

			<h1 class="buscar-titulo">
				<?php echo $q === '' ? 'Buscar técnicos' : 'Resultados para “' . htmlspecialchars($q) . '”'; ?>
			</h1>

			<div class="buscar-chips" aria-label="Buscar por especialidad">
				<?php foreach (especialidades_conectec() as $nombre_esp): ?>
				<a href="buscar.php?q=<?php echo urlencode($nombre_esp); ?>" class="buscar-chip"><?php echo htmlspecialchars($nombre_esp); ?></a>
				<?php endforeach; ?>
			</div>

			<?php if ($q === ''): ?>

			<p class="buscar-vacio">Escribí una especialidad (por ejemplo “electricista”) o el nombre de usuario del técnico que buscás.</p>

			<?php elseif (empty($resultados)): ?>

			<p class="buscar-vacio">No encontramos técnicos para “<?php echo htmlspecialchars($q); ?>”. Probá con otra especialidad o revisá cómo escribiste el usuario.</p>

			<?php else: ?>

			<p class="buscar-cantidad"><?php echo count($resultados); ?> técnico<?php echo count($resultados) === 1 ? '' : 's'; ?> encontrado<?php echo count($resultados) === 1 ? '' : 's'; ?></p>

			<?php foreach ($resultados as $r): ?>
			<article class="buscar-card">
				<a href="perfil.php?id=<?php echo (int) $r['id_tecnico']; ?>" class="buscar-card-link">
					<span class="buscar-avatar">
						<img src="<?php echo htmlspecialchars($r['foto_perfil'] ?: 'img/logo.png'); ?>" alt="">
					</span>
					<span class="buscar-info">
						<span class="buscar-nombre">@<?php echo htmlspecialchars($r['nombre']); ?><?php if (!empty($r['verificado'])) { echo insignia_verificada(); } ?></span>
						<span class="buscar-especialidad"><?php echo htmlspecialchars($r['especialidades_texto'] !== '' ? $r['especialidades_texto'] : etiqueta_especialidad($r['especialidad'])); ?></span>
						<span class="buscar-calificacion">
							<?php if ((float) $r['promedio_calificacion'] > 0): ?>
							★ <?php echo number_format((float) $r['promedio_calificacion'], 1, ',', ''); ?>
							<?php else: ?>
							Sin calificaciones todavía
							<?php endif; ?>
						</span>
					</span>
				</a>

				<?php if (pueden_conversar($tipo_propio, 'tecnico')): ?>
				<a href="chat.php?destino=<?php echo (int) $r['id_usuario']; ?>" class="post-card-btn-mensaje" aria-label="Enviar mensaje a <?php echo htmlspecialchars($r['nombre']); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5 H20 V16 H9 L4 20 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
				</a>
				<?php endif; ?>
			</article>
			<?php endforeach; ?>

			<?php endif; ?>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>
