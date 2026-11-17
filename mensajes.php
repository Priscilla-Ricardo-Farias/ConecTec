<?php
// mensajes.php
//
// Lista de conversaciones del usuario logueado, con el último
// mensaje de cada una. Cada fila lleva a chat.php?id=<id_conversacion>.
//
// El "visto" (doble check) es una simplificación: no hay en la base
// una columna que marque si el otro ya leyó, así que el doble check
// azul solo indica "el último mensaje lo mandé yo".

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/includes/funciones_mensajes.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';

exigir_abono_empresa($conexion);

$id_usuario_propio = (int) $_SESSION['usuario_id'];

// El último mensaje que se muestra salta los que el usuario eliminó "para mí" (tabla mensaje_oculto);
// los eliminados "para todos" se muestran como "Se eliminó este mensaje".
$sql = "SELECT uc.id_conversacion, ult.contenido, ult.fecha_hora, ult.id_usuario AS ultimo_autor, ult.leido, ult.eliminado_para_todos
        FROM usuario_conversacion uc
        LEFT JOIN (
            SELECT m1.id_conversacion, m1.contenido, m1.fecha_hora, m1.id_usuario, m1.leido, m1.eliminado_para_todos
            FROM mensaje m1
            INNER JOIN (
                SELECT mm.id_conversacion, MAX(mm.id_mensaje) AS id_ultimo
                FROM mensaje mm
                WHERE NOT EXISTS (SELECT 1 FROM mensaje_oculto mo WHERE mo.id_mensaje = mm.id_mensaje AND mo.id_usuario = ?)
                GROUP BY mm.id_conversacion
            ) m2 ON m2.id_conversacion = m1.id_conversacion AND m2.id_ultimo = m1.id_mensaje
        ) ult ON ult.id_conversacion = uc.id_conversacion
        WHERE uc.id_usuario = ?
        ORDER BY (ult.fecha_hora IS NULL), ult.fecha_hora DESC";

try {
	$stmt = $conexion->prepare($sql);
	$stmt->bind_param('ii', $id_usuario_propio, $id_usuario_propio);
} catch (Throwable $e) {
	// todavía no se corrió sql/migracion_cambios_21oct.sql: la lista funciona como antes
	$sql_viejo = "SELECT uc.id_conversacion, ult.contenido, ult.fecha_hora, ult.id_usuario AS ultimo_autor, ult.leido, 0 AS eliminado_para_todos
	        FROM usuario_conversacion uc
	        LEFT JOIN (
	            SELECT m1.id_conversacion, m1.contenido, m1.fecha_hora, m1.id_usuario, m1.leido
	            FROM mensaje m1
	            INNER JOIN (
	                SELECT id_conversacion, MAX(id_mensaje) AS id_ultimo
	                FROM mensaje
	                GROUP BY id_conversacion
	            ) m2 ON m2.id_conversacion = m1.id_conversacion AND m2.id_ultimo = m1.id_mensaje
	        ) ult ON ult.id_conversacion = uc.id_conversacion
	        WHERE uc.id_usuario = ?
	        ORDER BY (ult.fecha_hora IS NULL), ult.fecha_hora DESC";
	$stmt = $conexion->prepare($sql_viejo);
	$stmt->bind_param('i', $id_usuario_propio);
}
$stmt->execute();
$filas_conversaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$etiquetas_tipo = ['tecnico' => 'Técnico', 'empresa' => 'Empresa', 'cliente' => 'Cliente'];

$conversaciones = [];

foreach ($filas_conversaciones as $fila) {

	$stmt = $conexion->prepare('SELECT id_usuario FROM usuario_conversacion WHERE id_conversacion = ? AND id_usuario != ?');
	$stmt->bind_param('ii', $fila['id_conversacion'], $id_usuario_propio);
	$stmt->execute();
	$fila_contacto = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$fila_contacto) {
		continue; // no debería pasar, pero por las dudas
	}

	$contacto = datos_usuario_para_mensajes($conexion, $fila_contacto['id_usuario']);

	if (!$contacto) {
		continue;
	}

	$nombre_mostrado = $contacto['tipo'] === 'empresa' && $contacto['empresa']
		? $contacto['empresa']
		: trim($contacto['nombre'] . ' ' . ($contacto['apellido'] ?? ''));

	if ($fila['fecha_hora'] === null) {
		// Sin mensajes visibles: si la conversación tiene mensajes pero esta persona los eliminó
		// todos ("Eliminar chat"), no se muestra. Reaparece sola cuando llega un mensaje nuevo.
		// Si nunca tuvo mensajes (chat recién empezado) se muestra como antes.
		$stmt = $conexion->prepare('SELECT 1 FROM mensaje WHERE id_conversacion = ? LIMIT 1');
		$stmt->bind_param('i', $fila['id_conversacion']);
		$stmt->execute();
		$tuvo_mensajes = (bool) $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if ($tuvo_mensajes) {
			continue;
		}

		$hora           = '';
		$ultimo_mensaje = 'Todavía no hay mensajes';
	} else {
		$fecha_mensaje = new DateTime($fila['fecha_hora']);
		$hora          = $fecha_mensaje->format('d') === (new DateTime())->format('d')
			? $fecha_mensaje->format('H:i')
			: $fecha_mensaje->format('d/m');
		$ultimo_mensaje = !empty($fila['eliminado_para_todos'])
			? ((int) $fila['ultimo_autor'] === $id_usuario_propio ? 'Eliminaste este mensaje' : 'Se eliminó este mensaje')
			: $fila['contenido'];
	}

	$conversaciones[] = [
		'id_conversacion' => $fila['id_conversacion'],
		'nombre'          => $nombre_mostrado,
		'verificado'      => !empty($contacto['verificado']),
		'tipo'            => $etiquetas_tipo[$contacto['tipo']],
		'foto_perfil'     => $contacto['foto_perfil'],
		'ultimo_mensaje'  => $ultimo_mensaje,
		'hora'            => $hora,
		'lo_mande_yo'     => $fila['ultimo_autor'] !== null && (int) $fila['ultimo_autor'] === $id_usuario_propio,
		'leido'           => (bool) $fila['leido'],
	];
}

$page_title = "Mensajes";
$extra_css  = ['css/feed.css', 'css/panel.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">

			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo">Mensajes</h1>

			<?php if (($_GET['chat'] ?? '') === 'eliminado'): ?>
			<p class="chat-aviso chat-aviso--ok" role="status">Eliminaste el chat.</p>
			<?php endif; ?>

			<div class="mensajes-card">
				<?php if (empty($conversaciones)): ?>
				<p style="text-align:center; color:#6b7280; padding:32px 20px;">
					Todavía no tenés conversaciones. Escribile a un técnico desde su perfil, o a un postulante desde tu publicación.
				</p>
				<?php else: ?>
				<?php foreach ($conversaciones as $conversacion): ?>
				<a href="chat.php?id=<?php echo (int) $conversacion['id_conversacion']; ?>" class="mensaje-fila">
					<span class="mensaje-avatar">
						<img src="<?php echo htmlspecialchars($conversacion['foto_perfil'] ?: 'img/logo.png'); ?>" alt="">
					</span>

					<div class="mensaje-info">
						<p class="mensaje-nombre"><?php echo htmlspecialchars($conversacion['nombre']); ?><?php if ($conversacion['verificado']) { echo insignia_verificada($conversacion['tipo'] === 'Empresa' ? 'empresa' : 'persona'); } ?> (<?php echo htmlspecialchars($conversacion['tipo']); ?>)</p>
						<p class="mensaje-preview"><?php echo htmlspecialchars($conversacion['ultimo_mensaje']); ?></p>
					</div>

					<div class="mensaje-meta">
						<?php if ($conversacion['lo_mande_yo']): ?>
						<span class="mensaje-visto <?php echo $conversacion['leido'] ? 'mensaje-visto--leido' : ''; ?>" aria-label="<?php echo $conversacion['leido'] ? 'Visto' : 'Enviado'; ?>">
							<?php if ($conversacion['leido']): ?>
							<svg viewBox="0 0 24 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 8 L6 13 L14 3" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 8 L14 13 L22 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php else: ?>
							<svg viewBox="0 0 24 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 8 L14 13 L22 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php endif; ?>
						</span>
						<?php endif; ?>
						<span class="mensaje-hora"><?php echo htmlspecialchars($conversacion['hora']); ?></span>
					</div>
				</a>
				<?php endforeach; ?>
				<?php endif; ?>
			</div>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>