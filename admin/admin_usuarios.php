<?php
// admin_usuarios.php
//
// Sección "Usuarios" del sidebar: acá solo se listan los clientes
// (usuario sin fila en tecnico ni en empleador). Los técnicos y las
// empresas ahora tienen su propia pantalla (admin_tecnicos.php /
// admin_empresas.php). Permite suspender, reactivar o dar de baja
// (eliminación lógica, vía estado_cuenta = 'eliminada' — no se borra
// la fila para no perder historial).

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$busqueda = trim($_GET['q'] ?? '');

$sql = "SELECT u.id_usuario, u.nombre, u.email, u.fecha_registro, u.estado_cuenta
        FROM usuario u
        LEFT JOIN tecnico t     ON t.id_usuario = u.id_usuario
        LEFT JOIN empleador emp ON emp.id_usuario = u.id_usuario
        WHERE t.id_tecnico IS NULL AND emp.id_empleador IS NULL";

if ($busqueda !== '') {
	$sql .= " AND u.nombre LIKE ?";
	$stmt = $conexion->prepare($sql . " ORDER BY u.fecha_registro DESC");
	$like = $busqueda . '%';
	$stmt->bind_param('s', $like);
	$stmt->execute();
	$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
} else {
	$usuarios = $conexion->query($sql . " ORDER BY u.fecha_registro DESC")->fetch_all(MYSQLI_ASSOC);
}

$etiquetas_estado = [
	'activa'     => ['texto' => 'Activa',     'clase' => 'admin-estado--activa'],
	'suspendida' => ['texto' => 'Suspendida', 'clase' => 'admin-estado--suspendida'],
	'eliminada'  => ['texto' => 'Eliminada',  'clase' => 'admin-estado--eliminada'],
];

$page_title      = "Usuarios";
$admin_active    = 'usuarios';
$admin_titulo    = 'Usuarios';
$admin_subtitulo = 'Clientes registrados en la plataforma';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($_GET['actualizado'])): ?>
<p class="reg-aviso reg-aviso-ok">Cambio guardado.</p>
<?php endif; ?>

<div class="admin-toolbar">
	<form action="admin_usuarios.php" method="GET">
		<input type="text" name="q" placeholder="Buscar por nombre" value="<?php echo htmlspecialchars($busqueda); ?>">
		<button type="submit">Buscar</button>
	</form>
</div>

<div class="admin-tabla-wrap">
	<?php if (empty($usuarios)): ?>

	<p class="admin-vacio">No se encontraron usuarios.</p>

	<?php else: ?>

	<table class="admin-tabla">
		<thead>
			<tr>
				<th>Nombre</th>
				<th>Correo</th>
				<th>Registrado</th>
				<th>Estado</th>
				<th>Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($usuarios as $u): ?>
			<?php $estado = isset($etiquetas_estado[$u['estado_cuenta']]) ? $u['estado_cuenta'] : 'suspendida'; ?>
			<tr>
				<td><?php echo htmlspecialchars($u['nombre']); ?></td>
				<td><?php echo htmlspecialchars($u['email']); ?></td>
				<td><?php echo htmlspecialchars(date('d/m/Y', strtotime($u['fecha_registro']))); ?></td>
				<td>
					<span class="admin-estado <?php echo $etiquetas_estado[$estado]['clase']; ?>">
						<?php echo $etiquetas_estado[$estado]['texto']; ?>
					</span>
				</td>
				<td>
					<div class="admin-acciones">

						<?php if ($estado === 'activa'): ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">
							<input type="hidden" name="accion" value="suspender">
							<button type="submit" class="admin-btn">Suspender</button>
						</form>
						<?php elseif ($estado === 'suspendida'): ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">
							<input type="hidden" name="accion" value="reactivar">
							<button type="submit" class="admin-btn">Reactivar</button>
						</form>
						<?php endif; ?>

						<?php if ($estado !== 'eliminada'): ?>
						<form action="procesar_admin_usuario.php" method="POST" onsubmit="return confirm('¿Eliminar la cuenta de <?php echo htmlspecialchars(addslashes($u['nombre'])); ?>? Va a dejar de poder iniciar sesión.');">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">
							<input type="hidden" name="accion" value="eliminar">
							<button type="submit" class="admin-btn admin-btn-danger">Eliminar</button>
						</form>
						<?php else: ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">
							<input type="hidden" name="accion" value="reactivar">
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
