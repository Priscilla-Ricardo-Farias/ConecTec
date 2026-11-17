<?php
// admin_trabajos.php
//
// Lista todas las publicaciones (tabla "trabajo") con quién la
// publicó, para que el admin pueda eliminar las que ya están
// terminadas, muy viejas, o son inapropiadas. El botón "Eliminar"
// hace un DELETE real: como trabajo_cliente, trabajo_empleador y
// postulacion tienen ON DELETE CASCADE hacia trabajo, se limpian
// solas junto con la publicación.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$sql = "SELECT tr.id_trabajo, tr.titulo, tr.categoria, tr.ubicacion, tr.fecha_publicacion, tr.estado,

               COALESCE(uc.nombre, ue.nombre) AS publicado_por,
               CASE WHEN te.id_empleador IS NOT NULL THEN 'empresa' ELSE 'cliente' END AS tipo_publicante

        FROM trabajo tr
        LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
        LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente
        LEFT JOIN usuario uc         ON uc.id_usuario = c.id_usuario

        LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
        LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador
        LEFT JOIN usuario ue           ON ue.id_usuario = emp.id_usuario

        ORDER BY tr.fecha_publicacion DESC";

$trabajos = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);

$etiquetas_estado = [
	'abierto'     => ['texto' => 'Abierto',     'clase' => 'admin-estado--activa'],
	'en_progreso' => ['texto' => 'En progreso', 'clase' => 'admin-estado--suspendida'],
	'cerrado'     => ['texto' => 'Cerrado',     'clase' => 'admin-estado--eliminada'],
];

$page_title      = "Publicaciones";
$admin_active    = 'publicaciones';
$admin_titulo    = 'Publicaciones';
$admin_subtitulo = 'Todas las publicaciones de trabajos de la plataforma';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($_GET['actualizado'])): ?>
<p class="reg-aviso reg-aviso-ok">Publicación eliminada.</p>
<?php endif; ?>

<div class="admin-tabla-wrap">
	<?php if (empty($trabajos)): ?>

	<p class="admin-vacio">No hay publicaciones todavía.</p>

	<?php else: ?>

	<table class="admin-tabla">
		<thead>
			<tr>
				<th>Título</th>
				<th>Publicado por</th>
				<th>Categoría</th>
				<th>Ubicación</th>
				<th>Fecha</th>
				<th>Estado</th>
				<th>Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($trabajos as $t): ?>
			<tr>
				<td><?php echo htmlspecialchars($t['titulo']); ?></td>
				<td>
					<?php echo htmlspecialchars($t['publicado_por'] ?? '—'); ?>
					<span class="admin-tag"><?php echo $t['tipo_publicante'] === 'empresa' ? 'Empresa' : 'Cliente'; ?></span>
				</td>
				<td><?php echo htmlspecialchars($t['categoria']); ?></td>
				<td><?php echo htmlspecialchars($t['ubicacion']); ?></td>
				<td><?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha_publicacion']))); ?></td>
				<td>
					<span class="admin-estado <?php echo $etiquetas_estado[$t['estado']]['clase']; ?>">
						<?php echo $etiquetas_estado[$t['estado']]['texto']; ?>
					</span>
				</td>
				<td>
					<div class="admin-acciones">
						<form action="procesar_admin_trabajo.php" method="POST" onsubmit="return confirm('¿Eliminar la publicación &quot;<?php echo htmlspecialchars(addslashes($t['titulo'])); ?>&quot;? No se puede deshacer.');">
							<input type="hidden" name="id_trabajo" value="<?php echo (int) $t['id_trabajo']; ?>">
							<input type="hidden" name="accion" value="eliminar">
							<button type="submit" class="admin-btn admin-btn-danger">Eliminar</button>
						</form>
					</div>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
