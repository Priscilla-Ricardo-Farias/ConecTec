<?php
// admin_reportes.php
//
// Sección "Reportes" del sidebar. Datos REALES de la plataforma para un
// rango de días a elegir (por defecto el que se guarda en
// Configuración → Reportes). Las consultas viven en
// includes/funciones_admin.php (admin_reporte_datos) para compartirlas
// con la exportación a CSV (admin_reportes_csv.php).
//
// Los gráficos son CSS puro (sin librerías): barras verticales para los
// últimos 6 meses y barras horizontales para los desgloses.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';

$rangos = admin_reporte_rangos();
$dias   = admin_reporte_dias($conexion, $_GET['dias'] ?? null);
$r      = admin_reporte_datos($conexion, $dias);

$num = fn($n) => number_format((float) $n, 0, ',', '.');

$nombres_plan = [];
foreach (planes_conectec() as $clave => $plan) {
	$nombres_plan[$clave] = $plan['nombre'];
}

$rango_texto = mb_strtolower($rangos[$dias]);

$page_title      = "Reportes";
$admin_active    = 'reportes';
$admin_titulo    = 'Reportes';
$admin_subtitulo = 'Actividad de la plataforma — ' . $rangos[$dias];
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-toolbar">
	<form action="admin_reportes.php" method="GET">
		<select name="dias" aria-label="Período">
			<?php foreach ($rangos as $valor => $texto): ?>
			<option value="<?php echo (int) $valor; ?>"<?php echo $valor === $dias ? ' selected' : ''; ?>><?php echo htmlspecialchars($texto); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit">Aplicar</button>
	</form>

	<a href="admin_reportes_csv.php?dias=<?php echo (int) $dias; ?>" class="admin-btn admin-btn-link">Exportar CSV</a>
</div>

<?php if (PAGOS_MODO_PRUEBA): ?>
<p class="reg-aviso reg-aviso-aviso">Los pagos están en <strong>modo de prueba</strong> (<code>PAGOS_MODO_PRUEBA</code> en <code>includes/funciones_suscripcion.php</code>): los ingresos de acá son simulados, no dinero cobrado.</p>
<?php endif; ?>

<!-- ---------- INDICADORES ---------- -->
<div class="admin-kpis">

	<div class="admin-kpi">
		<span class="admin-kpi-etiqueta">Usuarios nuevos</span>
		<strong class="admin-kpi-valor"><?php echo $num($r['usuarios_nuevos']); ?></strong>
		<span class="admin-kpi-nota"><?php echo $num($r['usuarios_activos']); ?> cuentas activas en total</span>
	</div>

	<div class="admin-kpi">
		<span class="admin-kpi-etiqueta">Publicaciones</span>
		<strong class="admin-kpi-valor"><?php echo $num($r['trabajos_nuevos']); ?></strong>
		<span class="admin-kpi-nota"><?php echo $num($r['trabajos_estado']['abierto']); ?> siguen abiertas</span>
	</div>

	<div class="admin-kpi">
		<span class="admin-kpi-etiqueta">Postulaciones</span>
		<strong class="admin-kpi-valor"><?php echo $num($r['postulaciones']); ?></strong>
		<span class="admin-kpi-nota">
			<?php echo $r['tasa_aceptacion'] === null
				? 'Sin postulaciones en el período'
				: 'Aceptadas: ' . number_format($r['tasa_aceptacion'], 1, ',', '.') . '%'; ?>
		</span>
	</div>

	<div class="admin-kpi">
		<span class="admin-kpi-etiqueta">Ingresos por planes</span>
		<strong class="admin-kpi-valor"><?php echo formatear_precio($r['ingresos']); ?></strong>
		<span class="admin-kpi-nota"><?php echo $num($r['suscripciones_activas']); ?> suscripciones activas ahora</span>
	</div>

	<div class="admin-kpi<?php echo $r['pendientes']['total'] > 0 ? ' admin-kpi--alerta' : ''; ?>">
		<span class="admin-kpi-etiqueta">Verificaciones pendientes</span>
		<strong class="admin-kpi-valor"><?php echo $num($r['pendientes']['total']); ?></strong>
		<span class="admin-kpi-nota">
			<?php if ($r['pendientes']['total'] > 0): ?>
			<a href="verificaciones.php">Revisar ahora →</a>
			<?php else: ?>
			Todo al día
			<?php endif; ?>
		</span>
	</div>

	<div class="admin-kpi">
		<span class="admin-kpi-etiqueta">Mensajes de soporte</span>
		<strong class="admin-kpi-valor"><?php echo $num($r['soporte']); ?></strong>
		<span class="admin-kpi-nota"><?php echo $num($r['soporte_prioritarios']); ?> prioritarios · <?php echo $num($r['soporte_reporte']); ?> reportes</span>
	</div>

	<div class="admin-kpi">
		<span class="admin-kpi-etiqueta">Calificación promedio</span>
		<strong class="admin-kpi-valor"><?php echo $r['calificacion_prom'] === null ? '—' : number_format($r['calificacion_prom'], 1, ',', '.') . ' / 5'; ?></strong>
		<span class="admin-kpi-nota"><?php echo $num($r['calificaciones']); ?> calificaciones</span>
	</div>

</div>

<!-- ---------- ÚLTIMOS 6 MESES ---------- -->
<div class="admin-grid-2">
	<section class="admin-section">
		<div class="admin-section-header">
			<h2 class="admin-section-titulo">Registros por mes</h2>
			<span class="admin-section-nota">Últimos 6 meses</span>
		</div>
		<?php echo admin_barras_v($r['meses'], 'usuarios'); ?>
	</section>

	<section class="admin-section">
		<div class="admin-section-header">
			<h2 class="admin-section-titulo">Publicaciones por mes</h2>
			<span class="admin-section-nota">Últimos 6 meses</span>
		</div>
		<?php echo admin_barras_v($r['meses'], 'trabajos'); ?>
	</section>
</div>

<!-- ---------- DESGLOSES DEL PERÍODO ---------- -->
<div class="admin-grid-2">
	<section class="admin-section">
		<div class="admin-section-header">
			<h2 class="admin-section-titulo">Publicaciones por categoría</h2>
			<span class="admin-section-nota"><?php echo htmlspecialchars($rango_texto); ?></span>
		</div>
		<?php
		$filas = [];
		foreach ($r['categorias'] as $c) {
			$filas[] = ['etiqueta' => $c['categoria'], 'valor' => $c['c']];
		}
		echo admin_barras_h($filas);
		?>
	</section>

	<section class="admin-section">
		<div class="admin-section-header">
			<h2 class="admin-section-titulo">Estado de las publicaciones</h2>
			<span class="admin-section-nota"><?php echo htmlspecialchars($rango_texto); ?></span>
		</div>
		<?php
		echo admin_barras_h([
			['etiqueta' => 'Abiertas',     'valor' => $r['trabajos_estado']['abierto']],
			['etiqueta' => 'En progreso',  'valor' => $r['trabajos_estado']['en_progreso']],
			['etiqueta' => 'Cerradas',     'valor' => $r['trabajos_estado']['cerrado']],
			['etiqueta' => 'De clientes',  'valor' => $r['trabajos_origen']['cliente']],
			['etiqueta' => 'De empresas',  'valor' => $r['trabajos_origen']['empresa']],
		]);
		?>
	</section>

	<section class="admin-section">
		<div class="admin-section-header">
			<h2 class="admin-section-titulo">Postulaciones</h2>
			<span class="admin-section-nota"><?php echo htmlspecialchars($rango_texto); ?></span>
		</div>
		<?php
		echo admin_barras_h([
			['etiqueta' => 'Pendientes', 'valor' => $r['postulaciones_estado']['pendiente']],
			['etiqueta' => 'Aceptadas',  'valor' => $r['postulaciones_estado']['aceptada']],
			['etiqueta' => 'Rechazadas', 'valor' => $r['postulaciones_estado']['rechazada']],
		]);
		?>
	</section>

	<section class="admin-section">
		<div class="admin-section-header">
			<h2 class="admin-section-titulo">Cuentas activas por tipo</h2>
			<span class="admin-section-nota">Total actual</span>
		</div>
		<?php
		echo admin_barras_h([
			['etiqueta' => 'Clientes', 'valor' => $r['clientes']],
			['etiqueta' => 'Técnicos', 'valor' => $r['tecnicos']],
			['etiqueta' => 'Empresas', 'valor' => $r['empresas']],
		]);
		?>
	</section>
</div>

<!-- ---------- INGRESOS POR PLAN ---------- -->
<section class="admin-section">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">Ingresos por plan</h2>
		<span class="admin-section-nota"><?php echo htmlspecialchars($rango_texto); ?></span>
	</div>

	<?php if (empty($r['ingresos_plan'])): ?>

	<p class="admin-vacio admin-vacio--chico">No hubo pagos en este período.</p>

	<?php else: ?>

	<div class="admin-tabla-wrap admin-tabla-wrap--plana">
		<table class="admin-tabla">
			<thead>
				<tr>
					<th>Plan</th>
					<th>Pagos</th>
					<th>Total</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($r['ingresos_plan'] as $p): ?>
				<tr>
					<td><?php echo htmlspecialchars($nombres_plan[$p['plan']] ?? $p['plan']); ?></td>
					<td><?php echo $num($p['c']); ?></td>
					<td><?php echo formatear_precio($p['total']); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
