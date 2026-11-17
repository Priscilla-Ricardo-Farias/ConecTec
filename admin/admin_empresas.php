<?php
// admin_empresas.php
//
// Sección "Empresas" del sidebar: roster de empresas YA aceptadas
// (empleador.verificada = 1), más las que están suspendidas o dadas de
// baja (incluidas las rechazadas), para poder reactivarlas o restaurarlas.
// Las pendientes de aprobar viven en verificaciones.php, no acá.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$busqueda = trim($_GET['q'] ?? '');

$sql = "SELECT e.id_empleador, e.empresa, e.cuit, e.descripcion_empresa,
               u.id_usuario, u.nombre, u.email, u.fecha_registro, u.estado_cuenta
        FROM empleador e
        JOIN usuario u ON u.id_usuario = e.id_usuario
        WHERE (e.verificada = 1 OR u.estado_cuenta <> 'activa')";

if ($busqueda !== '') {
	$sql .= " AND e.empresa LIKE ?";
	$stmt = $conexion->prepare($sql . " ORDER BY u.fecha_registro DESC");
	$like = $busqueda . '%';
	$stmt->bind_param('s', $like);
	$stmt->execute();
	$empresas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
} else {
	$empresas = $conexion->query($sql . " ORDER BY u.fecha_registro DESC")->fetch_all(MYSQLI_ASSOC);
}

$etiquetas_estado = [
	'activa'     => ['texto' => 'Activa',     'clase' => 'admin-estado--activa'],
	'suspendida' => ['texto' => 'Suspendida', 'clase' => 'admin-estado--suspendida'],
	'eliminada'  => ['texto' => 'Eliminada',  'clase' => 'admin-estado--eliminada'],
];

$page_title      = "Empresas";
$admin_active    = 'empresas';
$admin_titulo    = 'Empresas';
$admin_subtitulo = 'Empresas aceptadas en la plataforma';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($_GET['actualizado'])): ?>
<p class="reg-aviso reg-aviso-ok">Cambio guardado.</p>
<?php endif; ?>

<div class="admin-toolbar">
	<form action="admin_empresas.php" method="GET">
		<input type="text" name="q" placeholder="Buscar por empresa" value="<?php echo htmlspecialchars($busqueda); ?>">
		<button type="submit">Buscar</button>
	</form>
</div>

<div class="admin-tabla-wrap">
	<?php if (empty($empresas)): ?>

	<p class="admin-vacio">Todavía no hay empresas aceptadas.</p>

	<?php else: ?>

	<table class="admin-tabla">
		<thead>
			<tr>
				<th>Empresa</th>
				<th>CUIT</th>
				<th>Correo</th>
				<th>Registrada</th>
				<th>Estado</th>
				<th>Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($empresas as $e): ?>
			<?php $estado = isset($etiquetas_estado[$e['estado_cuenta']]) ? $e['estado_cuenta'] : 'suspendida'; ?>
			<tr>
				<td><?php echo htmlspecialchars($e['empresa']); ?></td>
				<td><?php echo htmlspecialchars($e['cuit']); ?></td>
				<td><?php echo htmlspecialchars($e['email']); ?></td>
				<td><?php echo htmlspecialchars(date('d/m/Y', strtotime($e['fecha_registro']))); ?></td>
				<td>
					<span class="admin-estado <?php echo $etiquetas_estado[$estado]['clase']; ?>">
						<?php echo $etiquetas_estado[$estado]['texto']; ?>
					</span>
				</td>
				<td>
					<div class="admin-acciones">
						<?php if ($estado === 'activa'): ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $e['id_usuario']; ?>">
							<input type="hidden" name="accion" value="suspender">
							<input type="hidden" name="volver_a" value="admin_empresas.php">
							<button type="submit" class="admin-btn">Suspender</button>
						</form>
						<?php elseif ($estado === 'suspendida'): ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $e['id_usuario']; ?>">
							<input type="hidden" name="accion" value="reactivar">
							<input type="hidden" name="volver_a" value="admin_empresas.php">
							<button type="submit" class="admin-btn">Reactivar</button>
						</form>
						<?php endif; ?>

						<?php if ($estado !== 'eliminada'): ?>
						<form action="procesar_admin_usuario.php" method="POST" onsubmit="return confirm('¿Eliminar la cuenta de <?php echo htmlspecialchars(addslashes($e['nombre'])); ?>? Va a dejar de poder iniciar sesión.');">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $e['id_usuario']; ?>">
							<input type="hidden" name="accion" value="eliminar">
							<input type="hidden" name="volver_a" value="admin_empresas.php">
							<button type="submit" class="admin-btn admin-btn-danger">Eliminar</button>
						</form>
						<?php else: ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $e['id_usuario']; ?>">
							<input type="hidden" name="accion" value="reactivar">
							<input type="hidden" name="volver_a" value="admin_empresas.php">
							<button type="submit" class="admin-btn">Restaurar</button>
						</form>
						<?php endif; ?>
					</div>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>
