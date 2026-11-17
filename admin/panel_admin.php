<?php
// panel_admin.php
//
// "Tablero de control": pantalla de entrada del panel admin (login_admin
// redirige acá). Junta, acotado, lo mismo que se ve completo en
// verificaciones.php (pendientes) y admin_trabajos.php (publicaciones),
// para tener un pantallazo rápido del estado de la plataforma.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';

/* ---- Verificaciones pendientes (máx. 4 más recientes) ---- */

$tecnicos_pendientes = $conexion->query(
	"SELECT t.id_tecnico AS id, t.especialidad, t.certificado_archivo, u.nombre, u.fecha_registro
	 FROM tecnico t
	 JOIN usuario u ON u.id_usuario = t.id_usuario
	 WHERE t.estado_verificacion = 'pendiente' AND u.estado_cuenta = 'activa'
	 ORDER BY u.fecha_registro DESC"
)->fetch_all(MYSQLI_ASSOC);

$empresas_pendientes = $conexion->query(
	"SELECT e.id_empleador AS id, e.empresa, e.cuit, u.nombre, u.fecha_registro
	 FROM empleador e
	 JOIN usuario u ON u.id_usuario = e.id_usuario
	 WHERE e.verificada = 0 AND u.estado_cuenta = 'activa'
	 ORDER BY u.fecha_registro DESC"
)->fetch_all(MYSQLI_ASSOC);

$cuentas_pendientes = verificaciones_cuenta_pendientes($conexion);

$total_pendientes = count($tecnicos_pendientes) + count($empresas_pendientes) + count($cuentas_pendientes);

$items_verificacion = [];
foreach ($tecnicos_pendientes as $t) {
	$items_verificacion[] = [
		'tipo' => 'tecnico', 'id' => $t['id'], 'nombre' => $t['nombre'],
		'sub' => $t['especialidad'], 'certificado_archivo' => $t['certificado_archivo'],
		'fecha' => $t['fecha_registro'],
	];
}
foreach ($empresas_pendientes as $e) {
	$items_verificacion[] = [
		'tipo' => 'empresa', 'id' => $e['id'], 'nombre' => $e['empresa'],
		'sub' => 'CUIT ' . $e['cuit'], 'fecha' => $e['fecha_registro'],
	];
}
foreach ($cuentas_pendientes as $c) {
	$items_verificacion[] = $c;
}
usort($items_verificacion, fn($a, $b) => strtotime($b['fecha']) <=> strtotime($a['fecha']));
$items_verificacion = array_slice($items_verificacion, 0, 4);

$volver_a = 'panel_admin.php';

/* ---- Publicaciones (últimas 8) ---- */

$trabajos = $conexion->query(
	"SELECT tr.id_trabajo, tr.titulo, tr.fecha_publicacion, tr.estado,
	        COALESCE(uc.nombre, ue.nombre) AS publicado_por,
	        CASE WHEN te.id_empleador IS NOT NULL THEN 'empresa' ELSE 'cliente' END AS tipo_publicante
	 FROM trabajo tr
	 LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
	 LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente
	 LEFT JOIN usuario uc         ON uc.id_usuario = c.id_usuario
	 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
	 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador
	 LEFT JOIN usuario ue           ON ue.id_usuario = emp.id_usuario
	 ORDER BY tr.fecha_publicacion DESC
	 LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

$total_trabajos = $conexion->query("SELECT COUNT(*) AS c FROM trabajo")->fetch_assoc()['c'];

$etiquetas_estado = [
	'abierto'     => ['texto' => 'Abierto',     'clase' => 'admin-estado--activa'],
	'en_progreso' => ['texto' => 'En progreso', 'clase' => 'admin-estado--suspendida'],
	'cerrado'     => ['texto' => 'Cerrado',     'clase' => 'admin-estado--eliminada'],
];

$page_title      = "Panel de administración";
$admin_active    = 'tablero';
$admin_titulo    = 'Panel de Administración';
$admin_subtitulo = 'Gestiona las verificaciones y publicaciones de la plataforma';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($_GET['actualizado'])): ?>
<p class="reg-aviso reg-aviso-ok">Cambio guardado.</p>
<?php endif; ?>

<section class="admin-section">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">
			Verificaciones pendientes
			<span class="admin-badge-count"><?php echo $total_pendientes; ?> pendientes</span>
		</h2>
		<a href="verificaciones.php" class="admin-section-link">Ver todas →</a>
	</div>

	<?php include __DIR__ . '/../includes/admin_verif_cards.php'; ?>
</section>

<section class="admin-section">
	<div class="admin-section-header">
		<h2 class="admin-section-titulo">
			Publicaciones de trabajos
			<span class="admin-badge-count" style="background:#eaf1fc;color:var(--blue-accent);"><?php echo $total_trabajos; ?> publicaciones</span>
		</h2>
		<a href="admin_trabajos.php" class="admin-section-link">Ver todas →</a>
	</div>

	<div class="admin-tabla-wrap">
		<?php if (empty($trabajos)): ?>

		<p class="admin-vacio">No hay publicaciones todavía.</p>

		<?php else: ?>

		<table class="admin-tabla">
			<thead>
				<tr>
					<th>Título</th>
					<th>Publicado por</th>
					<th>Tipo de usuario</th>
					<th>Fecha</th>
					<th>Estado</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($trabajos as $t): ?>
				<tr>
					<td><?php echo htmlspecialchars($t['titulo']); ?></td>
					<td><?php echo htmlspecialchars($t['publicado_por'] ?? '—'); ?></td>
					<td><span class="admin-tag"><?php echo $t['tipo_publicante'] === 'empresa' ? 'Empresa' : 'Cliente'; ?></span></td>
					<td><?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha_publicacion']))); ?></td>
					<td>
						<span class="admin-estado <?php echo $etiquetas_estado[$t['estado']]['clase']; ?>">
							<?php echo $etiquetas_estado[$t['estado']]['texto']; ?>
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php endif; ?>
	</div>
</section>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>