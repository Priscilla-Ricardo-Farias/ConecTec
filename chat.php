<?php
// chat.php
//
// Pantalla de una conversación puntual. Se llega acá de dos formas:
//
//   - chat.php?destino=123   → 123 es un id_usuario. Busca (o crea)
//     la conversación 1 a 1 con ese usuario, siempre y cuando el tipo
//     de cuenta de los dos lo permita (ver includes/funciones_mensajes.php).
//   - chat.php?id=45         → 45 es un id_conversacion ya existente.
//     Solo entra si el usuario logueado es uno de los dos participantes.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/includes/funciones_mensajes.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_fecha_mensaje.php';

exigir_abono_empresa($conexion);

$id_usuario_propio = (int) $_SESSION['usuario_id'];
$tipo_propio        = $_SESSION['usuario_tipo'] ?? 'cliente';

$id_conversacion = null;

if (isset($_GET['destino'])) {

	$id_destino = (int) $_GET['destino'];

	if ($id_destino === $id_usuario_propio) {
		header('Location: mensajes.php');
		exit;
	}

	$destino = datos_usuario_para_mensajes($conexion, $id_destino);

	if (!$destino || !pueden_conversar($tipo_propio, $destino['tipo'])) {
		header('Location: mensajes.php?error=no_permitido');
		exit;
	}

	$id_conversacion = conversacion_entre($conexion, $id_usuario_propio, $id_destino);

} elseif (isset($_GET['id'])) {

	$id_conversacion = (int) $_GET['id'];

	if (!pertenece_a_conversacion($conexion, $id_conversacion, $id_usuario_propio)) {
		header('Location: mensajes.php');
		exit;
	}
}

if ($id_conversacion === null) {
	header('Location: mensajes.php');
	exit;
}

/* =========================================================
   DATOS DEL CONTACTO (el otro participante de la conversación)
   ========================================================= */

$stmt = $conexion->prepare(
	'SELECT id_usuario FROM usuario_conversacion WHERE id_conversacion = ? AND id_usuario != ?'
);
$stmt->bind_param('ii', $id_conversacion, $id_usuario_propio);
$stmt->execute();
$fila_contacto = $stmt->get_result()->fetch_assoc();
$stmt->close();

$contacto = $fila_contacto ? datos_usuario_para_mensajes($conexion, $fila_contacto['id_usuario']) : null;

if (!$contacto) {
	header('Location: mensajes.php');
	exit;
}

$etiquetas_tipo   = ['tecnico' => 'Técnico', 'empresa' => 'Empresa', 'cliente' => 'Cliente'];
$nombre_contacto  = $contacto['tipo'] === 'empresa' && $contacto['empresa']
	? $contacto['empresa']
	: trim($contacto['nombre'] . ' ' . ($contacto['apellido'] ?? ''));

/* =========================================================
   MARCAR COMO LEÍDOS los mensajes que me mandó el otro
   ========================================================= */

$stmt = $conexion->prepare(
	'UPDATE mensaje SET leido = 1
	 WHERE id_conversacion = ? AND id_usuario != ? AND leido = 0'
);
$stmt->bind_param('ii', $id_conversacion, $id_usuario_propio);
$stmt->execute();
$stmt->close();

/* =========================================================
   MENSAJES DE LA CONVERSACIÓN
   ========================================================= */

// "Eliminar para mí" (tabla mensaje_oculto) esconde el mensaje solo para quien lo borró;
// "Eliminar para todos" deja el mensaje marcado y se ve como "Se eliminó este mensaje".
try {
	$stmt = $conexion->prepare(
		'SELECT m.id_mensaje, m.id_usuario, m.contenido, m.fecha_hora, m.leido, m.eliminado_para_todos
		 FROM mensaje m
		 WHERE m.id_conversacion = ?
		   AND NOT EXISTS (SELECT 1 FROM mensaje_oculto mo WHERE mo.id_mensaje = m.id_mensaje AND mo.id_usuario = ?)
		 ORDER BY m.fecha_hora ASC, m.id_mensaje ASC'
	);
	$stmt->bind_param('ii', $id_conversacion, $id_usuario_propio);
} catch (Throwable $e) {
	// todavía no se corrió sql/migracion_cambios_21oct.sql: el chat funciona como antes
	$stmt = $conexion->prepare(
		'SELECT id_mensaje, id_usuario, contenido, fecha_hora, leido, 0 AS eliminado_para_todos
		 FROM mensaje
		 WHERE id_conversacion = ?
		 ORDER BY fecha_hora ASC, id_mensaje ASC'
	);
	$stmt->bind_param('i', $id_conversacion);
}
$stmt->execute();
$mensajes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Avisos que vuelven de procesar_evento_personal.php / procesar_mensaje_accion.php
$aviso_chat = null;
if (($_GET['cal'] ?? '') === 'ok') {
	$cal_anio = (int) ($_GET['cal_anio'] ?? date('Y'));
	$cal_mes  = (int) ($_GET['cal_mes'] ?? date('n'));
	$aviso_chat = ['ok', 'Agregado a tu calendario.', 'calendario.php?anio=' . $cal_anio . '&mes=' . $cal_mes];
} elseif (($_GET['cal'] ?? '') === 'error' || ($_GET['msg'] ?? '') === 'error') {
	$aviso_chat = ['error', 'No pudimos completar la acción. Probá de nuevo.', null];
}

$page_title = $nombre_contacto;
$extra_css  = ['css/feed.css', 'css/panel.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner panel-page-inner-chat">

			<?php $volver_a = 'mensajes.php'; include 'includes/boton_volver.php'; ?>

			<div class="chat-card">

				<div class="chat-encabezado">
					<span class="mensaje-avatar">
						<img src="<?php echo htmlspecialchars($contacto['foto_perfil'] ?: 'img/logo.png'); ?>" alt="">
					</span>
					<div>
						<p class="mensaje-nombre"><?php echo htmlspecialchars($nombre_contacto); ?><?php if (!empty($contacto['verificado'])) { echo insignia_verificada($contacto['tipo'] === 'empresa' ? 'empresa' : 'persona'); } ?></p>
						<p class="chat-encabezado-tipo"><?php echo htmlspecialchars($etiquetas_tipo[$contacto['tipo']]); ?></p>
					</div>
					<button type="button" class="chat-encabezado-menu" id="chat-abrir-opciones" aria-label="Opciones del chat" aria-haspopup="dialog">
						<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
					</button>
				</div>

					<div class="chat-mensajes" id="chat-mensajes">
						<?php if (!empty($aviso_chat)): ?>
						<p class="chat-aviso chat-aviso--<?php echo $aviso_chat[0]; ?>" role="status">
							<?php echo htmlspecialchars($aviso_chat[1]); ?>
							<?php if (!empty($aviso_chat[2])): ?><a href="<?php echo htmlspecialchars($aviso_chat[2]); ?>">Ver en el calendario</a><?php endif; ?>
						</p>
						<?php endif; ?>

						<?php if (empty($mensajes)): ?>
						<p class="chat-vacio">Todavía no hay mensajes. ¡Escribí el primero!</p>
						<?php else: ?>
							<?php foreach ($mensajes as $m): ?>
							<?php
							$es_mio     = (int) $m['id_usuario'] === $id_usuario_propio;
							$eliminado  = !empty($m['eliminado_para_todos']);
							$fecha_det  = $eliminado ? null : detectar_fecha_en_texto($m['contenido'], $m['fecha_hora']);
							?>
							<div class="chat-burbuja-fila <?php echo $es_mio ? 'chat-burbuja-fila--mia' : ''; ?>">
								<div class="chat-burbuja <?php echo $es_mio ? 'chat-burbuja--mia' : 'chat-burbuja--otro'; ?><?php echo $eliminado ? ' chat-burbuja--eliminada' : ''; ?>"
									data-id="<?php echo (int) $m['id_mensaje']; ?>"
									data-mio="<?php echo $es_mio ? '1' : '0'; ?>"
									data-eliminado="<?php echo $eliminado ? '1' : '0'; ?>"
									data-texto="<?php echo htmlspecialchars($m['contenido']); ?>"
									<?php if ($fecha_det): ?>
									data-fecha="<?php echo htmlspecialchars($fecha_det['fecha']); ?>"
									data-hora="<?php echo htmlspecialchars($fecha_det['hora'] ?? ''); ?>"
									<?php endif; ?>>
									<?php if ($eliminado): ?>
									<p class="chat-burbuja-borrado">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6 L18.4 18.4" stroke-linecap="round"/></svg>
										<?php echo $es_mio ? 'Eliminaste este mensaje' : 'Se eliminó este mensaje'; ?>
									</p>
									<?php else: ?>
									<p><?php echo nl2br(htmlspecialchars($m['contenido'])); ?></p>
									<?php endif; ?>
									<span class="chat-burbuja-pie">
										<?php if ($fecha_det): ?>
										<button type="button" class="chat-burbuja-cal" data-accion-cal aria-label="Agregar al calendario" title="Agregar al calendario">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10 H21 M8 3 V7 M16 3 V7" stroke-linecap="round"/></svg>
										</button>
										<?php endif; ?>
										<span class="chat-burbuja-hora"><?php echo (new DateTime($m['fecha_hora']))->format('d/m H:i'); ?></span>
										<?php if ($es_mio && !$eliminado): ?>
										<span class="chat-burbuja-visto <?php echo $m['leido'] ? 'chat-burbuja-visto--leido' : ''; ?>" aria-label="<?php echo $m['leido'] ? 'Visto' : 'Enviado'; ?>">
											<?php if ($m['leido']): ?>
											<svg viewBox="0 0 24 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 8 L6 13 L14 3" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 8 L14 13 L22 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
											<?php else: ?>
											<svg viewBox="0 0 24 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 8 L14 13 L22 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
											<?php endif; ?>
										</span>
										<?php endif; ?>
									</span>
									<!-- En la computadora aparece al pasar el mouse; en el celular se mantiene apretado el mensaje. -->
									<button type="button" class="chat-burbuja-opciones" data-abrir-menu aria-label="Opciones del mensaje" aria-haspopup="dialog">
										<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
									</button>
								</div>
							</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<form action="procesar/procesar_mensajes.php" method="POST" class="chat-form">
						<input type="hidden" name="id_conversacion" value="<?php echo (int) $id_conversacion; ?>">
						<input type="text" name="contenido" class="chat-input" placeholder="Escribí un mensaje..." autocomplete="off" required maxlength="1000">
						<button type="submit" class="chat-enviar" aria-label="Enviar mensaje">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12 L21 4 L14 21 L11 13 L3 12 Z" stroke-linejoin="round" stroke-linecap="round"/></svg>
						</button>
					</form>

				</div>

			</div>
		</section>
	</main>

<!-- Opciones de un mensaje (se abre manteniendo apretado el mensaje, o con los tres puntitos en la computadora) -->
<dialog id="chat-menu" class="chat-menu" aria-label="Opciones del mensaje">

	<div id="chat-menu-vista-acciones">
		<p class="chat-menu-extracto" id="chat-menu-extracto"></p>
		<button type="button" class="chat-menu-opcion" id="chat-menu-copiar">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15 H4 a1 1 0 0 1 -1 -1 V4 a1 1 0 0 1 1 -1 H14 a1 1 0 0 1 1 1 V5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			Copiar
		</button>
		<button type="button" class="chat-menu-opcion" id="chat-menu-calendario" hidden>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10 H21 M8 3 V7 M16 3 V7" stroke-linecap="round"/></svg>
			Agregar al calendario
		</button>
		<button type="button" class="chat-menu-opcion chat-menu-opcion--peligro" id="chat-menu-eliminar">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7 H20 M10 11 V17 M14 11 V17 M6 7 L7 20 H17 L18 7 M9 7 V4 H15 V7" stroke-linecap="round" stroke-linejoin="round"/></svg>
			Eliminar
		</button>
		<button type="button" class="chat-menu-cancelar" data-cerrar-menu>Cancelar</button>
	</div>

	<div id="chat-menu-vista-eliminar" hidden>
		<p class="chat-menu-titulo">¿Eliminar mensaje?</p>
		<form action="procesar/procesar_mensaje_accion.php" method="POST" class="chat-menu-form">
			<input type="hidden" name="id_mensaje" class="chat-menu-id-mensaje" value="">
			<button type="submit" name="accion" value="eliminar_todos" class="chat-menu-opcion chat-menu-opcion--peligro" id="chat-menu-eliminar-todos">Eliminar para todos</button>
			<button type="submit" name="accion" value="ocultar" class="chat-menu-opcion chat-menu-opcion--peligro">Eliminar para mí</button>
		</form>
		<button type="button" class="chat-menu-cancelar" data-cerrar-menu>Cancelar</button>
	</div>

	<div id="chat-menu-vista-calendario" hidden>
		<p class="chat-menu-titulo">Agregar al calendario</p>
		<form action="procesar/procesar_evento_personal.php" method="POST" class="chat-menu-form">
			<input type="hidden" name="accion" value="agregar">
			<input type="hidden" name="id_conversacion" value="<?php echo (int) $id_conversacion; ?>">
			<input type="hidden" name="id_mensaje" class="chat-menu-id-mensaje" value="">

			<label class="chat-menu-campo">Título
				<input type="text" name="titulo" id="chat-cal-titulo" maxlength="150" required>
			</label>
			<div class="chat-menu-fila">
				<label class="chat-menu-campo">Fecha
					<input type="date" name="fecha" id="chat-cal-fecha" required>
				</label>
				<label class="chat-menu-campo">Hora <span>(opcional)</span>
					<input type="time" name="hora" id="chat-cal-hora">
				</label>
			</div>
			<button type="submit" class="chat-menu-opcion chat-menu-opcion--primaria">Guardar en el calendario</button>
		</form>
		<button type="button" class="chat-menu-cancelar" data-cerrar-menu>Cancelar</button>
	</div>

</dialog>

<!-- Opciones de todo el chat (tres puntitos del encabezado) -->
<dialog id="chat-menu-chat" class="chat-menu" aria-label="Opciones del chat">
	<div id="chat-chat-vista-acciones">
		<button type="button" class="chat-menu-opcion chat-menu-opcion--peligro" id="chat-chat-eliminar">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7 H20 M10 11 V17 M14 11 V17 M6 7 L7 20 H17 L18 7 M9 7 V4 H15 V7" stroke-linecap="round" stroke-linejoin="round"/></svg>
			Eliminar chat
		</button>
		<button type="button" class="chat-menu-cancelar" data-cerrar-chat>Cancelar</button>
	</div>

	<div id="chat-chat-vista-confirmar" hidden>
		<p class="chat-menu-titulo">¿Eliminar este chat?</p>
		<p class="chat-menu-extracto" style="border-bottom:none;-webkit-line-clamp:unset;">Se borran todos los mensajes para vos. <?php echo htmlspecialchars($nombre_contacto); ?> los sigue viendo. Si te escribe de nuevo, el chat vuelve a aparecer.</p>
		<form action="procesar/procesar_mensaje_accion.php" method="POST" class="chat-menu-form">
			<input type="hidden" name="accion" value="eliminar_chat">
			<input type="hidden" name="id_conversacion" value="<?php echo (int) $id_conversacion; ?>">
			<button type="submit" class="chat-menu-opcion chat-menu-opcion--peligro">Sí, eliminar chat</button>
		</form>
		<button type="button" class="chat-menu-cancelar" data-cerrar-chat>Cancelar</button>
	</div>
</dialog>

<script>
	(function () {
		var dlg = document.getElementById('chat-menu-chat');
		var acc = document.getElementById('chat-chat-vista-acciones');
		var conf = document.getElementById('chat-chat-vista-confirmar');
		var abierto = 0;
		document.getElementById('chat-abrir-opciones').addEventListener('click', function () {
			acc.hidden = false; conf.hidden = true;
			abierto = Date.now();
			dlg.showModal();
		});
		document.getElementById('chat-chat-eliminar').addEventListener('click', function () {
			acc.hidden = true; conf.hidden = false;
		});
		dlg.addEventListener('click', function (e) {
			if (Date.now() - abierto < 300) { return; }
			if (e.target === dlg || e.target.closest('[data-cerrar-chat]')) { dlg.close(); }
		});
	})();
</script>

<script>
	// Arranca el scroll del chat abajo del todo, donde está el mensaje más nuevo.
	var chatMensajes = document.getElementById('chat-mensajes');
	if (chatMensajes) {
		chatMensajes.scrollTop = chatMensajes.scrollHeight;
	}

	(function () {
		var menu        = document.getElementById('chat-menu');
		var vistaAcc    = document.getElementById('chat-menu-vista-acciones');
		var vistaElim   = document.getElementById('chat-menu-vista-eliminar');
		var vistaCal    = document.getElementById('chat-menu-vista-calendario');
		var extracto    = document.getElementById('chat-menu-extracto');
		var btnCopiar   = document.getElementById('chat-menu-copiar');
		var btnCal      = document.getElementById('chat-menu-calendario');
		var btnElim     = document.getElementById('chat-menu-eliminar');
		var btnElimTodos = document.getElementById('chat-menu-eliminar-todos');
		var burbujaActual = null;
		var abiertoEn = 0;      // para ignorar el "clic" que a veces llega al levantar el dedo tras mantener apretado

		if (!chatMensajes || !menu) { return; }

		function mostrarVista(vista) {
			[vistaAcc, vistaElim, vistaCal].forEach(function (v) { v.hidden = (v !== vista); });
		}

		function abrirMenu(burbuja, directoACalendario) {
			if (menu.open) { return; }
			burbujaActual = burbuja;

			var texto      = burbuja.dataset.texto || '';
			var eliminado  = burbuja.dataset.eliminado === '1';
			var esMio      = burbuja.dataset.mio === '1';
			var tieneFecha = !!burbuja.dataset.fecha;

			extracto.textContent = eliminado ? 'Mensaje eliminado' : (texto.length > 90 ? texto.slice(0, 90) + '…' : texto);
			btnCopiar.hidden = eliminado;
			btnCal.hidden    = !tieneFecha;
			btnElimTodos.hidden = !esMio || eliminado;

			menu.querySelectorAll('.chat-menu-id-mensaje').forEach(function (c) { c.value = burbuja.dataset.id; });

			// Datos del evento, ya cargados con lo que se detectó en el mensaje (se pueden cambiar)
			if (tieneFecha) {
				document.getElementById('chat-cal-titulo').value = texto.length > 100 ? texto.slice(0, 100) : texto;
				document.getElementById('chat-cal-fecha').value  = burbuja.dataset.fecha;
				document.getElementById('chat-cal-hora').value   = burbuja.dataset.hora || '';
			}

			mostrarVista(directoACalendario && tieneFecha ? vistaCal : vistaAcc);
			burbuja.classList.add('chat-burbuja--elegida');
			abiertoEn = Date.now();
			menu.showModal();
		}

		menu.addEventListener('close', function () {
			if (burbujaActual) { burbujaActual.classList.remove('chat-burbuja--elegida'); }
			burbujaActual = null;
		});

		// Cerrar con "Cancelar" y al tocar el fondo.
		menu.addEventListener('click', function (e) {
			if (Date.now() - abiertoEn < 400) { return; }
			if (e.target === menu || e.target.closest('[data-cerrar-menu]')) { menu.close(); }
		});

		btnElim.addEventListener('click', function () { mostrarVista(vistaElim); });
		btnCal.addEventListener('click',  function () { mostrarVista(vistaCal); });

		btnCopiar.addEventListener('click', function () {
			var texto = burbujaActual ? (burbujaActual.dataset.texto || '') : '';
			function listo() { menu.close(); }

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(texto).then(listo, listo);
			} else {
				var ta = document.createElement('textarea');
				ta.value = texto;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); } catch (e) {}
				document.body.removeChild(ta);
				listo();
			}
		});

		/* ---- Mantener apretado un mensaje (celular) ---- */
		var DURACION = 450;     // milisegundos
		var timer = null, inicioX = 0, inicioY = 0;

		function cancelarTimer() { if (timer) { clearTimeout(timer); timer = null; } }

		chatMensajes.addEventListener('touchstart', function (e) {
			var burbuja = e.target.closest('.chat-burbuja');
			if (!burbuja || e.target.closest('button, a')) { return; }
			inicioX = e.touches[0].clientX;
			inicioY = e.touches[0].clientY;
			cancelarTimer();
			timer = setTimeout(function () {
				timer = null;
				if (navigator.vibrate) { navigator.vibrate(15); }
				abrirMenu(burbuja, false);
			}, DURACION);
		}, { passive: true });

		chatMensajes.addEventListener('touchmove', function (e) {
			// Si el dedo se mueve (está haciendo scroll), no es un "mantener apretado".
			if (timer && (Math.abs(e.touches[0].clientX - inicioX) > 10 || Math.abs(e.touches[0].clientY - inicioY) > 10)) {
				cancelarTimer();
			}
		}, { passive: true });

		chatMensajes.addEventListener('touchend', cancelarTimer);
		chatMensajes.addEventListener('touchcancel', cancelarTimer);

		// Clic derecho en la computadora (y en algunos celulares, el mismo "mantener apretado").
		chatMensajes.addEventListener('contextmenu', function (e) {
			var burbuja = e.target.closest('.chat-burbuja');
			if (!burbuja) { return; }
			e.preventDefault();
			cancelarTimer();
			abrirMenu(burbuja, false);
		});

		// Los tres puntitos (computadora) y el icono de calendario de los mensajes con fecha.
		chatMensajes.addEventListener('click', function (e) {
			var boton = e.target.closest('[data-abrir-menu], [data-accion-cal]');
			if (!boton) { return; }
			var burbuja = boton.closest('.chat-burbuja');
			if (burbuja) { abrirMenu(burbuja, boton.hasAttribute('data-accion-cal')); }
		});
	})();
</script>

<?php include 'includes/footer.php'; ?>
