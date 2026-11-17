<?php
// calendario.php
//
// Arma el calendario de un mes con PHP (calcula cuántos días tiene el
// mes y qué día de la semana cae el día 1) y lo llena con los eventos
// reales de evento_agenda.
//
// Quién ve qué:
//   - TÉCNICO: ve los eventos agendados a su nombre (id_tecnico), tanto
//     trabajos de clientes como reuniones con empresas.
//   - CLIENTE / EMPRESA: ve los eventos de los trabajos que ELLOS
//     publicaron (trabajo_cliente / trabajo_empleador). Antes esto
//     quedaba vacío porque la agenda solo se buscaba por id_tecnico,
//     y por eso no aparecía la reunión cuando una empresa aceptaba a
//     un técnico.
//   - TODOS: además ven los eventos que ellos mismos agregaron desde un
//     mensaje del chat ("Agregar al calendario"), tabla evento_personal.
//
// Se puede navegar entre meses con ?anio=YYYY&mes=M.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

require_once __DIR__ . '/conexion.php';

$id_usuario = (int) $_SESSION['usuario_id'];

$page_title = "Calendario";
$extra_css  = ['css/feed.css', 'css/panel.css'];
include 'includes/header_hogar.php';

/* =========================================================
   MES A MOSTRAR (por defecto el actual)
   ========================================================= */

$nombres_dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$nombres_meses = [
	1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
	7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

$hoy         = new DateTime();
$anio_hoy    = (int) $hoy->format('Y');
$mes_hoy     = (int) $hoy->format('n');
$dia_de_hoy  = (int) $hoy->format('j');

$anio_actual = (int) ($_GET['anio'] ?? $anio_hoy);
$mes_actual  = (int) ($_GET['mes'] ?? $mes_hoy);

if ($mes_actual < 1 || $mes_actual > 12 || $anio_actual < 2000 || $anio_actual > 2100) {
	$anio_actual = $anio_hoy;
	$mes_actual  = $mes_hoy;
}

$es_mes_de_hoy = ($anio_actual === $anio_hoy && $mes_actual === $mes_hoy);

$primer_dia  = new DateTime(sprintf('%04d-%02d-01', $anio_actual, $mes_actual));
$dias_en_mes = (int) $primer_dia->format('t');
$nombre_mes  = $nombres_meses[$mes_actual] . ' ' . $anio_actual;

// format('N') devuelve 1 (lunes) a 7 (domingo); como la semana en la
// grilla empieza en lunes, se usa directamente como cantidad de
// casilleros vacíos antes del día 1.
$casilleros_vacios = ((int) $primer_dia->format('N')) - 1;

$mes_anterior = (clone $primer_dia)->modify('-1 month');
$mes_siguiente = (clone $primer_dia)->modify('+1 month');
$link_anterior = 'calendario.php?anio=' . $mes_anterior->format('Y') . '&mes=' . $mes_anterior->format('n');
$link_siguiente = 'calendario.php?anio=' . $mes_siguiente->format('Y') . '&mes=' . $mes_siguiente->format('n');

/* =========================================================
   EVENTOS DEL USUARIO
   Un evento le "pertenece" al usuario si:
     - es el técnico agendado (tecnico.id_usuario), o
     - es el cliente que publicó el trabajo, o
     - es la empresa que publicó el trabajo.
   ========================================================= */

function eventos_del_usuario($conexion, $id_usuario, $desde, $hasta, $limite = null) {
	$sql = "SELECT ea.id_evento, ea.fecha, ea.hora_inicio, ea.tipo,
	               tr.titulo, tr.archivo,
	               t.id_usuario AS id_usuario_tecnico,
	               tu.nombre AS tecnico_nombre, tu.apellido AS tecnico_apellido,
	               cu.nombre AS cliente_nombre, cu.apellido AS cliente_apellido,
	               emp.empresa AS empleador_empresa
	        FROM evento_agenda ea
	        JOIN tecnico t                   ON t.id_tecnico = ea.id_tecnico
	        JOIN usuario tu                  ON tu.id_usuario = t.id_usuario
	        LEFT JOIN trabajo tr             ON tr.id_trabajo = ea.id_trabajo
	        LEFT JOIN trabajo_cliente tc     ON tc.id_trabajo = tr.id_trabajo
	        LEFT JOIN cliente cl             ON cl.id_cliente = tc.id_cliente
	        LEFT JOIN usuario cu             ON cu.id_usuario = cl.id_usuario
	        LEFT JOIN trabajo_empleador te   ON te.id_trabajo = tr.id_trabajo
	        LEFT JOIN empleador emp          ON emp.id_empleador = te.id_empleador
	        WHERE (t.id_usuario = ? OR cl.id_usuario = ? OR emp.id_usuario = ?)
	          AND ea.fecha >= ? AND ea.fecha < ?
	        ORDER BY ea.fecha ASC, ea.hora_inicio ASC";

	if ($limite !== null) {
		$sql .= ' LIMIT ' . (int) $limite;
	}

	$stmt = $conexion->prepare($sql);
	$stmt->bind_param('iiiss', $id_usuario, $id_usuario, $id_usuario, $desde, $hasta);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Los eventos que la persona agregó desde un mensaje del chat se mezclan con los demás.
	$filas = array_merge($filas, eventos_personales($conexion, $id_usuario, $desde, $hasta, $limite));
	usort($filas, function ($a, $b) {
		return strcmp($a['fecha'] . ' ' . ($a['hora_inicio'] ?? '00:00:00'), $b['fecha'] . ' ' . ($b['hora_inicio'] ?? '00:00:00'));
	});
	if ($limite !== null) {
		$filas = array_slice($filas, 0, (int) $limite);
	}

	return $filas;
}

// Eventos propios (tabla evento_personal), con la misma forma que los de evento_agenda.
function eventos_personales($conexion, $id_usuario, $desde, $hasta, $limite = null) {
	$sql = 'SELECT id_evento_personal, fecha, hora_inicio, titulo
	        FROM evento_personal
	        WHERE id_usuario = ? AND fecha >= ? AND fecha < ?
	        ORDER BY fecha ASC, hora_inicio ASC';

	if ($limite !== null) {
		$sql .= ' LIMIT ' . (int) $limite;
	}

	try {
		$stmt = $conexion->prepare($sql);
		$stmt->bind_param('iss', $id_usuario, $desde, $hasta);
		$stmt->execute();
		$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
	} catch (Throwable $e) {
		return [];   // todavía no se corrió sql/migracion_cambios_21oct.sql
	}

	foreach ($filas as $i => $f) {
		$filas[$i] += [
			'id_evento'          => null,
			'tipo'               => 'personal',
			'archivo'            => null,
			'id_usuario_tecnico' => 0,
			'tecnico_nombre'     => '', 'tecnico_apellido' => '',
			'cliente_nombre'     => '', 'cliente_apellido' => '',
			'empleador_empresa'  => '',
		];
	}

	return $filas;
}

// "Reunión", "Trabajo" o "Evento" (el que la persona agregó desde un mensaje).
function etiqueta_tipo_evento($evento) {
	if ($evento['tipo'] === 'reunion') {
		return 'Reunión';
	}
	return $evento['tipo'] === 'personal' ? 'Evento' : 'Trabajo';
}

// Con quién es el evento, según quién lo esté mirando: el técnico ve
// al cliente/empresa; el cliente/empresa ve al técnico.
function contraparte_evento($evento, $id_usuario) {
	if ($evento['tipo'] === 'personal') {
		return '';   // un evento propio no tiene "contraparte"
	}
	if ((int) $evento['id_usuario_tecnico'] === (int) $id_usuario) {
		if (!empty($evento['empleador_empresa'])) {
			return $evento['empleador_empresa'] . ' (Empresa)';
		}
		if (!empty($evento['cliente_nombre'])) {
			return trim($evento['cliente_nombre'] . ' ' . ($evento['cliente_apellido'] ?? '')) . ' (Cliente)';
		}
		return '';
	}
	return trim($evento['tecnico_nombre'] . ' ' . ($evento['tecnico_apellido'] ?? '')) . ' (Técnico)';
}

function texto_hora($hora) {
	return empty($hora) ? '' : substr($hora, 0, 5) . ' hs';
}

$desde_mes = $primer_dia->format('Y-m-d');
$hasta_mes = $mes_siguiente->format('Y-m-d'); // exclusivo

$eventos_mes = eventos_del_usuario($conexion, $id_usuario, $desde_mes, $hasta_mes);

$eventos_por_dia = []; // [dia => [eventos...]]
$trabajos        = []; // lista para el panel "Tus trabajos"

foreach ($eventos_mes as $evento) {
	$dia_evento = (int) (new DateTime($evento['fecha']))->format('j');
	$eventos_por_dia[$dia_evento][] = $evento;

	$etiqueta = etiqueta_tipo_evento($evento);
	$titulo   = $evento['titulo'] ?? $etiqueta;
	$linea    = $etiqueta . ': ' . $titulo . ' — ' . (new DateTime($evento['fecha']))->format('d/m');
	if (!empty($evento['hora_inicio'])) {
		$linea .= ', ' . texto_hora($evento['hora_inicio']);
	}
	$contraparte = contraparte_evento($evento, $id_usuario);
	if ($contraparte !== '') {
		$linea .= ' · ' . $contraparte;
	}
	// Los eventos propios llevan su id para poder quitarlos desde la lista.
	$trabajos[] = ['linea' => $linea, 'id_personal' => $evento['tipo'] === 'personal' ? (int) $evento['id_evento_personal'] : null];
}

// Próximo evento: el más cercano desde hoy en adelante, caiga en el
// mes que se está mirando o no.
$proximos = eventos_del_usuario($conexion, $id_usuario, $hoy->format('Y-m-d'), '9999-12-31', 1);
$trabajo_proximo = null;

if (!empty($proximos)) {
	$p = $proximos[0];
	$fecha_texto = (new DateTime($p['fecha']))->format('d/m/Y');
	if (!empty($p['hora_inicio'])) {
		$fecha_texto .= ', ' . texto_hora($p['hora_inicio']);
	}
	$etiqueta = etiqueta_tipo_evento($p);

	$trabajo_proximo = [
		'titulo'  => $etiqueta . ': ' . ($p['titulo'] ?? $etiqueta),
		'fecha'   => $fecha_texto,
		'cliente' => contraparte_evento($p, $id_usuario),
		'archivo' => $p['archivo'] ?? null, // foto o video que subió el cliente (las reuniones no tienen)
	];
}
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">
			<?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?>

			<div class="calendario-layout">

				<div class="calendario-card">

					<div class="calendario-mes">
						<a href="<?php echo $link_anterior; ?>" class="calendario-nav" aria-label="Mes anterior">&#8249;</a>
						<h2><?php echo htmlspecialchars($nombre_mes); ?></h2>
						<a href="<?php echo $link_siguiente; ?>" class="calendario-nav" aria-label="Mes siguiente">&#8250;</a>
					</div>

					<div class="calendario-grilla">
						<?php foreach ($nombres_dias as $nombre_dia): ?>
						<div class="calendario-dia-nombre"><?php echo mb_strtoupper($nombre_dia); ?></div>
						<?php endforeach; ?>

						<?php for ($i = 0; $i < $casilleros_vacios; $i++): ?>
						<div class="calendario-celda calendario-celda-vacia"></div>
						<?php endfor; ?>

						<?php for ($dia = 1; $dia <= $dias_en_mes; $dia++): ?>
						<?php
							$clases = 'calendario-celda';
							if ($es_mes_de_hoy && $dia === $dia_de_hoy) {
								$clases .= ' calendario-celda-hoy';
							}
							$eventos_dia = $eventos_por_dia[$dia] ?? [];
							if ($eventos_dia) {
								$clases .= ' calendario-celda-evento';
							}
						?>
						<div class="<?php echo $clases; ?>">
							<span class="calendario-numero"><?php echo $dia; ?></span>
							<?php foreach (array_slice($eventos_dia, 0, 2) as $ev): ?>
							<?php
								$es_reunion = $ev['tipo'] === 'reunion';
								$etiq_ev    = etiqueta_tipo_evento($ev);
								$clase_chip = $es_reunion ? 'calendario-chip-reunion' : ($ev['tipo'] === 'personal' ? 'calendario-chip-personal' : 'calendario-chip-trabajo');
								$titulo_ev  = $ev['titulo'] ?? $etiq_ev;
								$contra     = contraparte_evento($ev, $id_usuario);
								$tooltip    = $etiq_ev . ': ' . $titulo_ev
									. (!empty($ev['hora_inicio']) ? ' - ' . texto_hora($ev['hora_inicio']) : '')
									. ($contra !== '' ? ' - ' . $contra : '');
							?>
							<span class="calendario-chip <?php echo $clase_chip; ?>" title="<?php echo htmlspecialchars($tooltip); ?>">
								<?php if (!empty($ev['hora_inicio'])): ?><?php echo htmlspecialchars(substr($ev['hora_inicio'], 0, 5)); ?> <?php endif; ?><?php echo htmlspecialchars($es_reunion ? 'Reunión' : $titulo_ev); ?>
							</span>
							<?php endforeach; ?>
							<?php if (count($eventos_dia) > 2): ?>
							<span class="calendario-chip-mas">+<?php echo count($eventos_dia) - 2; ?> más</span>
							<?php endif; ?>
						</div>
						<?php endfor; ?>
					</div>

					<div class="calendario-footer-bar"></div>
				</div>

				<div class="calendario-sidebar">

					<div class="calendario-panel">
						<h3>Tus trabajos</h3>
						<ul>
							<?php if (empty($trabajos)): ?>
							<li>No tenés trabajos, reuniones ni eventos agendados este mes.</li>
							<?php else: ?>
							<?php foreach ($trabajos as $trabajo): ?>
							<li>
								<?php echo htmlspecialchars($trabajo['linea']); ?>
								<?php if ($trabajo['id_personal']): ?>
								<form action="procesar/procesar_evento_personal.php" method="POST" class="calendario-quitar-form" onsubmit="return confirm('¿Quitar este evento de tu calendario?');">
									<input type="hidden" name="accion" value="quitar">
									<input type="hidden" name="id_evento_personal" value="<?php echo (int) $trabajo['id_personal']; ?>">
									<input type="hidden" name="anio" value="<?php echo (int) $anio_actual; ?>">
									<input type="hidden" name="mes" value="<?php echo (int) $mes_actual; ?>">
									<button type="submit" class="calendario-quitar" aria-label="Quitar este evento">Quitar</button>
								</form>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
							<?php endif; ?>
						</ul>
						<button type="button" class="calendario-panel-flecha" aria-label="Ver más trabajos">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M15 18 L9 12 L15 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
					</div>

					<div class="calendario-panel">
						<h3>Trabajo próximo</h3>
						<ul>
							<?php if ($trabajo_proximo === null): ?>
							<li>No tenés ningún trabajo o reunión próximos agendados.</li>
							<?php else: ?>
							<li><?php echo htmlspecialchars($trabajo_proximo['titulo']); ?></li>
							<li><?php echo htmlspecialchars($trabajo_proximo['fecha']); ?></li>
							<?php if ($trabajo_proximo['cliente'] !== ''): ?>
							<li><?php echo htmlspecialchars($trabajo_proximo['cliente']); ?></li>
							<?php endif; ?>
							<?php endif; ?>
						</ul>
						<button type="button" class="calendario-panel-flecha" aria-label="Ver detalle del trabajo">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M15 18 L9 12 L15 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="calendario-proximo-imagen">
							<?php
								$archivo_proximo = $trabajo_proximo['archivo'] ?? null;
								$ext_proximo     = strtolower(pathinfo((string) $archivo_proximo, PATHINFO_EXTENSION));
								$es_video        = in_array($ext_proximo, ['mp4', 'webm', 'mov', 'ogg', 'm4v'], true);
							?>
							<?php if ($archivo_proximo && $es_video): ?>
							<video src="uploads/publicaciones/<?php echo htmlspecialchars($archivo_proximo); ?>" muted controls preload="metadata"></video>
							<?php elseif ($archivo_proximo): ?>
							<img src="uploads/publicaciones/<?php echo htmlspecialchars($archivo_proximo); ?>" alt="Imagen del trabajo próximo">
							<?php else: ?>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2" stroke-linejoin="round"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15l-5-5-4 4-3-3-6 6" stroke-linejoin="round" stroke-linecap="round"/></svg>
							<?php endif; ?>
						</div>
					</div>

				</div>

			</div>

		</div>
	</section>
</main>

<?php include 'includes/lightbox.php'; ?>
<?php include 'includes/footer.php'; ?>