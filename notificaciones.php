<?php
// notificaciones.php
//
// Lista de notificaciones reales del usuario logueado (tabla
// "notificacion"). Cada tipo tiene su propio ícono:
//   - 'trabajo'      → nuevo trabajo de tu especialidad (ícono de foto)
//   - 'mensaje'      → te escribieron / conviene escribirle a alguien (sobre)
//   - 'postulacion'  → postulación recibida o enviada (persona con tilde)
//   - 'cambio'       → cambió el estado de tu postulación (flechas)
//   - 'alerta'       → trabajo que coincide con tus preferencias (campana)
//
// La estrellita fija la notificación: las fijadas quedan siempre arriba
// (como destacar un mail en Gmail). Funciona con JavaScript (sin recargar)
// y sin él (formulario normal), ver procesar/procesar_notificacion_favorito.php.
//
// "Ver más" (o tocar la fila) abre la notificación completa, como abrir un
// mail en Gmail (?ver=ID): se marca como leída y se muestra con quién la
// generó, el texto entero, la fecha y un botón para ir al chat (tipo
// 'mensaje') o al inicio (el resto). Desde ahí también se puede fijar,
// borrar o volver a la bandeja.
//
// "Marcar como leídas" marca TODAS las notificaciones del usuario como
// leídas en la base (vía ?marcar_leidas=1). Las leídas pasan a una sección
// aparte ("Leídas"), debajo de las nuevas.
//
// El tachito borra una notificación; "Borrar leídas" vacía la sección de
// leídas. Ver procesar/procesar_notificacion_borrar.php.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

require_once __DIR__ . '/includes/funciones_mensajes.php';

$id_usuario_propio = (int) $_SESSION['usuario_id'];

if (isset($_GET['marcar_leidas'])) {
	$stmt = $conexion->prepare('UPDATE notificacion SET leida = 1 WHERE id_usuario = ? AND (visible_desde IS NULL OR visible_desde <= NOW())');
	$stmt->bind_param('i', $id_usuario_propio);
	$stmt->execute();
	$stmt->close();

	header('Location: notificaciones.php');
	exit;
}

// Abrir el detalle (?ver=ID) la marca como leída antes de dibujar la barra de
// arriba, así el puntito de "notificaciones sin leer" ya no la cuenta.
if (isset($_GET['ver'])) {
	$id_ver_leer = (int) $_GET['ver'];
	$stmt = $conexion->prepare('UPDATE notificacion SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ? AND (visible_desde IS NULL OR visible_desde <= NOW())');
	$stmt->bind_param('ii', $id_ver_leer, $id_usuario_propio);
	$stmt->execute();
	$stmt->close();
}

// "Ver más": marca esa notificación como leída y redirige a donde corresponde.
if (isset($_GET['abrir'])) {
	$id_abrir = (int) $_GET['abrir'];

	$stmt = $conexion->prepare('SELECT tipo, id_usuario_origen FROM notificacion WHERE id_notificacion = ? AND id_usuario = ?');
	$stmt->bind_param('ii', $id_abrir, $id_usuario_propio);
	$stmt->execute();
	$abierta = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($abierta) {
		$stmt = $conexion->prepare('UPDATE notificacion SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?');
		$stmt->bind_param('ii', $id_abrir, $id_usuario_propio);
		$stmt->execute();
		$stmt->close();

		if ($abierta['tipo'] === 'mensaje' && $abierta['id_usuario_origen']) {
			header('Location: chat.php?destino=' . (int) $abierta['id_usuario_origen']);
		} else {
			header('Location: feed.php');
		}
		exit;
	}

	header('Location: notificaciones.php');
	exit;
}

$page_title = "Notificaciones";
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css', 'css/notificaciones.css'];
include 'includes/header_hogar.php';

$etiquetas_tipo = ['tecnico' => 'Técnico', 'empresa' => 'Empresa', 'cliente' => 'Cliente'];

// Un ícono por tipo de notificación
$iconos_notificacion = [
	'mensaje'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5 H21 V19 H3 Z M3 5 L12 13 L21 5"/></svg>',
	'postulacion' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 11l2 2 4-4"/></svg>',
	'cambio'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4"/></svg>',
	'alerta'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>',
	'trabajo'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/></svg>',
	'certificado' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5.5"/><path d="M9.5 9l2 2 3.5-3.5"/><path d="M8.5 14l-1.5 7 5-2.5 5 2.5-1.5-7"/></svg>',
];

/* =========================================================
   DETALLE DE UNA NOTIFICACIÓN (?ver=ID)
   ========================================================= */
if (isset($_GET['ver'])) {
	$id_ver = (int) $_GET['ver'];

	$stmt = $conexion->prepare(
		'SELECT id_notificacion, id_usuario_origen, tipo, texto, leida, favorito, fecha
		 FROM notificacion
		 WHERE id_notificacion = ? AND id_usuario = ?
		   AND (visible_desde IS NULL OR visible_desde <= NOW())'
	);
	$stmt->bind_param('ii', $id_ver, $id_usuario_propio);
	$stmt->execute();
	$detalle = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$detalle) {
		header('Location: notificaciones.php');
		exit;
	}

	// Abrirla la marca como leída
	if (!$detalle['leida']) {
		$stmt = $conexion->prepare('UPDATE notificacion SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?');
		$stmt->bind_param('ii', $id_ver, $id_usuario_propio);
		$stmt->execute();
		$stmt->close();
	}

	$origen_d = $detalle['id_usuario_origen'] ? datos_usuario_para_mensajes($conexion, $detalle['id_usuario_origen']) : null;
	$nombre_d = $origen_d
		? ($origen_d['tipo'] === 'empresa' && $origen_d['empresa'] ? $origen_d['empresa'] : trim($origen_d['nombre'] . ' ' . ($origen_d['apellido'] ?? '')))
		: 'Conectec';
	$foto_d   = $origen_d && !empty($origen_d['foto_perfil']) ? $origen_d['foto_perfil'] : 'img/logo.png';
	$rol_d    = $origen_d ? $etiquetas_tipo[$origen_d['tipo']] : 'Aviso del sistema';

	$tipos_nombre = [
		'mensaje'     => 'Mensaje nuevo',
		'postulacion' => 'Postulación',
		'cambio'      => 'Cambio en una postulación',
		'alerta'      => 'Alerta según tus preferencias',
		'trabajo'     => 'Nuevo trabajo',
		'certificado' => 'Revisión de certificado',
	];

	if ($detalle['tipo'] === 'mensaje' && $detalle['id_usuario_origen']) {
		$accion_href  = 'chat.php?destino=' . (int) $detalle['id_usuario_origen'];
		$accion_texto = 'Ir al chat';
	} elseif ($detalle['tipo'] === 'certificado') {
		$accion_href  = 'ajustes.php#certificados';
		$accion_texto = 'Ir a mis certificados';
	} else {
		$accion_href  = 'feed.php';
		$accion_texto = 'Ir al inicio';
	}

	$fecha_d  = new DateTime($detalle['fecha']);
	$fijada_d = (bool) $detalle['favorito'];
	$volver_ver = 'notificaciones.php?ver=' . $id_ver;
	?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">
			<?php $volver_a = 'notificaciones.php'; $volver_texto = 'Volver a notificaciones'; include 'includes/boton_volver.php'; ?>

			<?php if (isset($_GET['limite'])): ?>
			<p class="notificaciones-aviso" role="alert">Solo podés fijar hasta 5 notificaciones. Soltá una para fijar otra.</p>
			<?php endif; ?>

			<article class="notificacion-detalle">
				<header class="notificacion-detalle-encabezado">
					<span class="notificacion-detalle-tipo">
						<span class="notificacion-icono"><?php echo $iconos_notificacion[$detalle['tipo']] ?? $iconos_notificacion['trabajo']; ?></span>
						<?php echo htmlspecialchars($tipos_nombre[$detalle['tipo']] ?? 'Notificación'); ?>
					</span>

					<div class="notificacion-detalle-acciones">
						<form action="procesar/procesar_notificacion_favorito.php" method="POST">
							<input type="hidden" name="id_notificacion" value="<?php echo $id_ver; ?>">
							<input type="hidden" name="volver" value="<?php echo $volver_ver; ?>">
							<button type="submit" class="notificacion-favorito<?php echo $fijada_d ? ' notificacion-favorito--activo' : ''; ?>"
								aria-label="<?php echo $fijada_d ? 'Quitar de fijadas' : 'Fijar notificación'; ?>"
								title="<?php echo $fijada_d ? 'Quitar de fijadas' : 'Fijar notificación'; ?>">
								<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14.9 8.6 L22 9.3 L16.7 14 L18.2 21 L12 17.3 L5.8 21 L7.3 14 L2 9.3 L9.1 8.6 Z"/></svg>
							</button>
						</form>
						<form action="procesar/procesar_notificacion_borrar.php" method="POST" onsubmit="return confirm('¿Borrar esta notificación?');">
							<input type="hidden" name="id_notificacion" value="<?php echo $id_ver; ?>">
							<button type="submit" class="notificacion-borrar" aria-label="Borrar notificación" title="Borrar notificación">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
							</button>
						</form>
					</div>
				</header>

				<div class="notificacion-detalle-remitente">
					<span class="notificacion-avatar notificacion-avatar--grande">
						<img src="<?php echo htmlspecialchars($foto_d); ?>" alt="" onerror="this.onerror=null;this.src='img/logo.png';">
					</span>
					<div>
						<p class="notificacion-nombre"><?php echo htmlspecialchars($nombre_d); ?></p>
						<p class="notificacion-detalle-meta"><?php echo htmlspecialchars($rol_d); ?> · <?php echo $fecha_d->format('d/m/Y, H:i'); ?> hs</p>
					</div>
				</div>

				<p class="notificacion-detalle-texto"><?php echo htmlspecialchars($detalle['texto']); ?></p>

				<footer class="notificacion-detalle-pie">
					<a href="<?php echo htmlspecialchars($accion_href); ?>" class="ajustes-btn ajustes-btn--primario"><?php echo $accion_texto; ?></a>
					<a href="notificaciones.php" class="notificacion-detalle-secundario">Volver a la bandeja</a>
				</footer>
			</article>
		</div>
	</section>
</main>

<?php
	include 'includes/footer.php';
	exit;
}

// Fijadas primero, y dentro de cada grupo las más nuevas arriba
$stmt = $conexion->prepare(
	'SELECT id_notificacion, id_usuario_origen, tipo, texto, leida, favorito, UNIX_TIMESTAMP(fecha) AS ts
	 FROM notificacion
	 WHERE id_usuario = ?
	   AND (visible_desde IS NULL OR visible_desde <= NOW())
	 ORDER BY favorito DESC, fecha DESC'
);
$stmt->bind_param('i', $id_usuario_propio);
$stmt->execute();
$filas_notificaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$notificaciones = [];

foreach ($filas_notificaciones as $fila) {

	$origen = $fila['id_usuario_origen'] ? datos_usuario_para_mensajes($conexion, $fila['id_usuario_origen']) : null;

	$notificaciones[] = [
		'id'                 => (int) $fila['id_notificacion'],
		'nombre'             => $origen
			? ($origen['tipo'] === 'empresa' && $origen['empresa'] ? $origen['empresa'] : trim($origen['nombre'] . ' ' . ($origen['apellido'] ?? '')))
			: 'Conectec',
		'foto'               => $origen && !empty($origen['foto_perfil']) ? $origen['foto_perfil'] : 'img/logo.png',
		'tipo_contacto'      => $origen ? $etiquetas_tipo[$origen['tipo']] : '',
		'texto'              => $fila['texto'],
		'icono'              => $fila['tipo'],
		'leida'              => (bool) $fila['leida'],
		'favorito'           => (bool) $fila['favorito'],
		'ts'                 => (int) $fila['ts'],
	];
}
?>

<?php
// Tres secciones: fijadas (siempre arriba, máx. 5), nuevas y leídas.
// Una notificación fijada se queda en "Fijadas" esté leída o no.
$fijadas = array_values(array_filter($notificaciones, function ($n) { return $n['favorito']; }));
$nuevas  = array_values(array_filter($notificaciones, function ($n) { return !$n['favorito'] && !$n['leida']; }));
$leidas  = array_values(array_filter($notificaciones, function ($n) { return !$n['favorito'] && $n['leida']; }));
$sin_leer = count($nuevas) + count(array_filter($fijadas, function ($n) { return !$n['leida']; }));

// Una fila de notificación (se usa para las dos secciones)
function dibujar_notificacion($n, $iconos_notificacion) {
	?>
	<div class="notificacion-fila<?php echo !$n['leida'] ? ' notificacion-fila--no-leida' : ''; ?><?php echo $n['favorito'] ? ' notificacion-fila--fijada' : ''; ?>"
		data-href="notificaciones.php?ver=<?php echo $n['id']; ?>" data-fav="<?php echo $n['favorito'] ? 1 : 0; ?>" data-leida="<?php echo $n['leida'] ? 1 : 0; ?>" data-ts="<?php echo $n['ts']; ?>">

		<span class="notificacion-avatar">
			<img src="<?php echo htmlspecialchars($n['foto']); ?>" alt="" onerror="this.onerror=null;this.src='img/logo.png';">
		</span>

		<div class="notificacion-info">
			<p class="notificacion-nombre"><?php echo htmlspecialchars($n['nombre']); ?><?php if ($n['tipo_contacto'] !== ''): ?> (<?php echo htmlspecialchars($n['tipo_contacto']); ?>)<?php endif; ?></p>
			<p class="notificacion-texto"><?php echo htmlspecialchars($n['texto']); ?></p>
		</div>

		<a href="notificaciones.php?ver=<?php echo $n['id']; ?>" class="notificacion-ver-mas">Ver más</a>

		<span class="notificacion-icono">
			<?php echo $iconos_notificacion[$n['icono']] ?? $iconos_notificacion['trabajo']; ?>
		</span>

		<form class="notificacion-favorito-form" action="procesar/procesar_notificacion_favorito.php" method="POST">
			<input type="hidden" name="id_notificacion" value="<?php echo $n['id']; ?>">
			<button type="submit" class="notificacion-favorito<?php echo $n['favorito'] ? ' notificacion-favorito--activo' : ''; ?>"
				aria-label="<?php echo $n['favorito'] ? 'Quitar de fijadas' : 'Fijar notificación'; ?>"
				title="<?php echo $n['favorito'] ? 'Quitar de fijadas' : 'Fijar notificación'; ?>">
				<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14.9 8.6 L22 9.3 L16.7 14 L18.2 21 L12 17.3 L5.8 21 L7.3 14 L2 9.3 L9.1 8.6 Z"/></svg>
			</button>
		</form>

		<form class="notificacion-borrar-form" action="procesar/procesar_notificacion_borrar.php" method="POST">
			<input type="hidden" name="id_notificacion" value="<?php echo $n['id']; ?>">
			<button type="submit" class="notificacion-borrar" aria-label="Borrar notificación" title="Borrar notificación">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
			</button>
		</form>

	</div>
	<?php
}
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">
			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>

			<div class="notificaciones-encabezado">
				<h1 class="panel-titulo">Notificaciones</h1>
				<a href="notificaciones.php?marcar_leidas=1" class="notificaciones-marcar-leidas" id="btn-marcar-leidas"<?php echo $sin_leer === 0 ? ' hidden' : ''; ?>>Marcar como leídas</a>
			</div>

			<?php if (empty($notificaciones)): ?>
			<div class="notificaciones-card">
				<p class="notificaciones-vacio">Todavía no tenés notificaciones.</p>
			</div>
			<?php else: ?>

			<p class="notificaciones-aviso" id="aviso-limite" role="alert"<?php echo isset($_GET['limite']) ? '' : ' hidden'; ?>>Solo podés fijar hasta 5 notificaciones. Soltá una para fijar otra.</p>

			<!-- FIJADAS -->
			<div class="notificaciones-seccion notificaciones-seccion--fijadas" id="seccion-fijadas"<?php echo empty($fijadas) ? ' hidden' : ''; ?>>
				<div class="notificaciones-seccion-titulo">
					<h2>Fijadas <span class="notificaciones-contador"><span id="contador-fijadas"><?php echo count($fijadas); ?></span>/5</span></h2>
				</div>
				<div class="notificaciones-card" id="card-fijadas">
					<?php foreach ($fijadas as $n) { dibujar_notificacion($n, $iconos_notificacion); } ?>
				</div>
			</div>

			<!-- NUEVAS -->
			<div class="notificaciones-seccion" id="seccion-nuevas">
				<div class="notificaciones-seccion-titulo">
					<h2>Nuevas <span class="notificaciones-contador" id="contador-nuevas"><?php echo count($nuevas); ?></span></h2>
				</div>
				<div class="notificaciones-card" id="card-nuevas">
					<?php foreach ($nuevas as $n) { dibujar_notificacion($n, $iconos_notificacion); } ?>
					<p class="notificaciones-vacio"<?php echo !empty($nuevas) ? ' hidden' : ''; ?>>No tenés notificaciones nuevas.</p>
				</div>
			</div>

			<!-- LEÍDAS -->
			<div class="notificaciones-seccion" id="seccion-leidas"<?php echo empty($leidas) ? ' hidden' : ''; ?>>
				<div class="notificaciones-seccion-titulo">
					<h2>Leídas <span class="notificaciones-contador" id="contador-leidas"><?php echo count($leidas); ?></span></h2>
					<form id="form-borrar-leidas" action="procesar/procesar_notificacion_borrar.php" method="POST">
						<input type="hidden" name="alcance" value="leidas">
						<button type="submit" class="notificaciones-borrar-todas">Borrar leídas</button>
					</form>
				</div>
				<div class="notificaciones-card" id="card-leidas">
					<?php foreach ($leidas as $n) { dibujar_notificacion($n, $iconos_notificacion); } ?>
				</div>
			</div>

			<?php endif; ?>

		</div>
	</section>
</main>

<script>
// Sin recargar la página:
//  - la estrellita fija/suelta: la fila pasa a "Fijadas" (máx. 5) o vuelve
//    a "Nuevas"/"Leídas" según si está leída;
//  - el tachito borra la notificación;
//  - "Borrar leídas" vacía la sección de leídas (las fijadas no se borran).
// Si un pedido falla, se manda el formulario normal y la página se recarga.
(function () {
	var cards = {
		fijadas: document.getElementById('card-fijadas'),
		nuevas:  document.getElementById('card-nuevas'),
		leidas:  document.getElementById('card-leidas')
	};
	if (!cards.nuevas || !cards.leidas) { return; }
	var MAX = 5;

	// Tocar la fila abre la notificación (menos si se toca un botón o un enlace)
	[cards.fijadas, cards.nuevas, cards.leidas].forEach(function (card) {
		if (!card) { return; }
		card.addEventListener('click', function (e) {
			if (e.target.closest('a, button, form')) { return; }
			var fila = e.target.closest('.notificacion-fila');
			if (fila && fila.dataset.href) { window.location.href = fila.dataset.href; }
		});
	});

	function filas(card) {
		return Array.prototype.slice.call(card.querySelectorAll('.notificacion-fila'));
	}

	function ordenar(card) {
		filas(card).sort(function (a, b) { return b.dataset.ts - a.dataset.ts; })
			.forEach(function (fila) { card.appendChild(fila); });
	}

	function destinoDe(fila) {
		if (fila.dataset.fav === '1') { return cards.fijadas; }
		return fila.dataset.leida === '1' ? cards.leidas : cards.nuevas;
	}

	function aviso(mostrar) {
		var el = document.getElementById('aviso-limite');
		if (el) { el.hidden = !mostrar; }
	}

	function refrescar() {
		var f = filas(cards.fijadas).length;
		var n = filas(cards.nuevas).length;
		var l = filas(cards.leidas).length;
		var sinLeer = n + filas(cards.fijadas).filter(function (x) { return x.dataset.leida === '0'; }).length;

		document.getElementById('contador-fijadas').textContent = f;
		document.getElementById('contador-nuevas').textContent = n;
		document.getElementById('contador-leidas').textContent = l;
		cards.nuevas.querySelector('.notificaciones-vacio').hidden = n > 0;
		document.getElementById('btn-marcar-leidas').hidden = sinLeer === 0;
		document.getElementById('seccion-fijadas').hidden = f === 0;
		document.getElementById('seccion-leidas').hidden = l === 0;
	}

	function pedir(form) {
		return fetch(form.action, {
			method: 'POST',
			body: new FormData(form),
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
		}).then(function (r) {
			if (!r.ok) { throw new Error('http'); }
			return r.json();
		});
	}

	document.querySelectorAll('.notificacion-favorito-form').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var fila  = form.closest('.notificacion-fila');
			var boton = form.querySelector('.notificacion-favorito');

			// Tope de 5 fijadas
			if (fila.dataset.fav === '0' && filas(cards.fijadas).length >= MAX) {
				aviso(true);
				return;
			}

			pedir(form).then(function (datos) {
				if (datos.limite) { aviso(true); return; }
				if (!datos.ok) { throw new Error('rechazado'); }
				aviso(false);

				var fijada = datos.favorito === 1;
				var texto  = fijada ? 'Quitar de fijadas' : 'Fijar notificación';

				fila.dataset.fav = fijada ? 1 : 0;
				fila.classList.toggle('notificacion-fila--fijada', fijada);
				boton.classList.toggle('notificacion-favorito--activo', fijada);
				boton.setAttribute('aria-label', texto);
				boton.setAttribute('title', texto);

				var destino = destinoDe(fila);
				destino.appendChild(fila);
				ordenar(destino);
				refrescar();
			}).catch(function () { form.submit(); });
		});
	});

	document.querySelectorAll('.notificacion-borrar-form').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var fila = form.closest('.notificacion-fila');

			pedir(form).then(function (datos) {
				if (!datos.ok) { throw new Error('rechazado'); }
				fila.remove();
				refrescar();
			}).catch(function () { form.submit(); });
		});
	});

	var formBorrarLeidas = document.getElementById('form-borrar-leidas');
	formBorrarLeidas.addEventListener('submit', function (e) {
		e.preventDefault();
		if (!window.confirm('¿Borrar todas las notificaciones leídas? (Las fijadas se conservan)')) { return; }

		pedir(formBorrarLeidas).then(function (datos) {
			if (!datos.ok) { throw new Error('rechazado'); }
			filas(cards.leidas).forEach(function (fila) { fila.remove(); });
			refrescar();
		}).catch(function () { formBorrarLeidas.submit(); });
	});
})();
</script>

<?php include 'includes/footer.php'; ?>
