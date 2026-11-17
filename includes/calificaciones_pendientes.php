<?php
// includes/calificaciones_pendientes.php
//
// Popup "Por favor, calificá al técnico". Se incluye en feed.php.
//
// Busca los trabajos del usuario logueado (publicados por él, como cliente
// o como empresa) que ya tienen un técnico aceptado, cuyo día ya pasó y que
// todavía no fueron calificados. Si hay alguno, muestra un mensaje emergente
// con estrellas para calificar. Si hay más de uno, muestra de a uno: al
// enviar la calificación se vuelve al feed y aparece el siguiente.
//
// Necesita $conexion (conexion.php) y la sesión iniciada.
//
// OJO: usa calificacion.id_trabajo, que se agrega corriendo una sola vez
// migracion_calificaciones.sql. Si todavía no la corriste, el popup
// simplemente no aparece (el error queda en el log de PHP y como comentario
// HTML en la página; también podés abrir diagnostico_calificaciones.php).

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$ct_pendientes = [];
$ct_error     = '';

if (isset($_SESSION['usuario_id'], $conexion)) {
	try {
		$ct_uid = (int) $_SESSION['usuario_id'];

		$ct_stmt = $conexion->prepare(
			"SELECT tr.id_trabajo, tr.titulo, tr.fecha_trabajo,
			        ut.nombre AS nombre_tecnico, ut.foto_perfil AS foto_tecnico
			 FROM trabajo tr
			 JOIN postulacion p ON p.id_trabajo = tr.id_trabajo AND p.estado = 'aceptada'
			 JOIN tecnico t     ON t.id_tecnico = p.id_tecnico
			 JOIN usuario ut    ON ut.id_usuario = t.id_usuario

			 LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
			 LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente

			 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
			 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador

			 LEFT JOIN calificacion ca ON ca.id_trabajo = tr.id_trabajo

			 WHERE tr.estado = 'en_progreso'
			   AND tr.fecha_trabajo IS NOT NULL
			   AND tr.fecha_trabajo < CURDATE()
			   AND ca.id_calificacion IS NULL
			   AND (c.id_usuario = ? OR emp.id_usuario = ?)
			 ORDER BY tr.fecha_trabajo ASC"
		);

		if ($ct_stmt) {
			$ct_stmt->bind_param('ii', $ct_uid, $ct_uid);
			$ct_stmt->execute();
			$ct_pendientes = $ct_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
			$ct_stmt->close();
		}
	} catch (mysqli_sql_exception $e) {
		error_log('calificaciones_pendientes: ' . $e->getMessage());
		$ct_error      = $e->getMessage();
		$ct_pendientes = [];
	}
}

$ct_textos_puntaje = [
	5 => 'Excelente',
	4 => 'Muy bueno',
	3 => 'Bueno',
	2 => 'Regular',
	1 => 'Malo',
];
?>

<?php if ($ct_error !== ''): ?>
<!-- calificaciones_pendientes: error de base de datos: <?php echo htmlspecialchars(str_replace('--', '- -', $ct_error)); ?> (¿corriste migracion_calificaciones.sql? Abrí diagnostico_calificaciones.php) -->
<?php endif; ?>

<?php if (isset($_GET['calificado'])): ?>
<div class="cal-toast" role="status">¡Gracias! Tu calificación se guardó correctamente.</div>
<?php endif; ?>

<?php if (!empty($ct_pendientes)): ?>
<?php
$ct_actual = $ct_pendientes[0];
$ct_foto   = !empty($ct_actual['foto_tecnico']) ? $ct_actual['foto_tecnico'] : 'img/logo.png';
$ct_fecha  = (new DateTime($ct_actual['fecha_trabajo']))->format('d/m/Y');
?>
<div class="cal-overlay" id="cal-overlay" role="dialog" aria-modal="true" aria-labelledby="cal-titulo">
	<div class="cal-modal">

		<h2 id="cal-titulo">Por favor, califique al técnico</h2>

		<div class="cal-tecnico">
			<span class="cal-avatar">
				<img src="<?php echo htmlspecialchars($ct_foto); ?>" alt="">
			</span>
			<div>
				<p class="cal-tecnico-nombre">@<?php echo htmlspecialchars($ct_actual['nombre_tecnico']); ?></p>
				<p class="cal-tecnico-trabajo">
					<?php echo htmlspecialchars($ct_actual['titulo']); ?> &middot; <?php echo $ct_fecha; ?>
				</p>
			</div>
		</div>

		<p class="cal-texto">El trabajo ya finalizó. ¿Cómo fue tu experiencia?</p>

		<form action="procesar/procesar_calificacion.php" method="POST">
			<input type="hidden" name="id_trabajo" value="<?php echo (int) $ct_actual['id_trabajo']; ?>">

			<div class="cal-estrellas" role="radiogroup" aria-label="Puntaje de 1 a 5 estrellas">
				<?php foreach ($ct_textos_puntaje as $n => $texto): ?>
				<input type="radio" name="puntaje" id="cal-e<?php echo $n; ?>" value="<?php echo $n; ?>" <?php echo $n === 5 ? 'required' : ''; ?>>
				<label for="cal-e<?php echo $n; ?>" title="<?php echo $texto; ?>">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 L14.9 8.6 L22 9.3 L16.7 14 L18.2 21 L12 17.3 L5.8 21 L7.3 14 L2 9.3 L9.1 8.6 Z"/></svg>
					<span class="cal-sr"><?php echo $n; ?> — <?php echo $texto; ?></span>
				</label>
				<?php endforeach; ?>
			</div>

			<textarea name="comentario" class="cal-comentario" rows="3" maxlength="500" placeholder="Dejá un comentario (opcional)"></textarea>

			<div class="cal-acciones">
				<button type="submit" class="cal-btn-enviar">Enviar calificación</button>
				<button type="button" class="cal-btn-luego" id="cal-luego">Más tarde</button>
			</div>
		</form>

		<?php if (count($ct_pendientes) > 1): ?>
		<p class="cal-pendientes">Tenés <?php echo count($ct_pendientes); ?> trabajos por calificar.</p>
		<?php endif; ?>

	</div>
</div>

<script>
(function () {
	var overlay = document.getElementById('cal-overlay');
	var luego   = document.getElementById('cal-luego');
	if (!overlay) return;

	function cerrar() { overlay.style.display = 'none'; }

	luego.addEventListener('click', cerrar);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') cerrar();
	});
})();
</script>
<?php endif; ?>
