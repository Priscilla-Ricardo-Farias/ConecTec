<?php
// admin_tecnicos.php
//
// Sección "Técnicos" del sidebar: roster de técnicos YA aprobados
// (tecnico.estado_verificacion = 'verificado'). Los pendientes de
// aprobar viven en verificaciones.php, no acá — esta pantalla es de
// solo consulta (ver quién es técnico activo, y suspender/eliminar
// la cuenta si hace falta, igual que en admin_usuarios.php).

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';

$busqueda = trim($_GET['q'] ?? '');

$sql = "SELECT t.id_tecnico, t.especialidad, t.certificado_archivo, t.fecha_graduacion, t.promedio_calificacion,
               u.id_usuario, u.nombre, u.email, u.fecha_registro, u.estado_cuenta
        FROM tecnico t
        JOIN usuario u ON u.id_usuario = t.id_usuario
        WHERE t.estado_verificacion = 'verificado'";

if ($busqueda !== '') {
	$sql .= " AND u.nombre LIKE ?";
	$stmt = $conexion->prepare($sql . " ORDER BY u.fecha_registro DESC");
	$like = $busqueda . '%';
	$stmt->bind_param('s', $like);
	$stmt->execute();
	$tecnicos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
} else {
	$tecnicos = $conexion->query($sql . " ORDER BY u.fecha_registro DESC")->fetch_all(MYSQLI_ASSOC);
}

$etiquetas_estado = [
	'activa'     => ['texto' => 'Activa',     'clase' => 'admin-estado--activa'],
	'suspendida' => ['texto' => 'Suspendida', 'clase' => 'admin-estado--suspendida'],
	'eliminada'  => ['texto' => 'Eliminada',  'clase' => 'admin-estado--eliminada'],
];

$page_title      = "Técnicos";
$admin_active    = 'tecnicos';
$admin_titulo    = 'Técnicos';
$admin_subtitulo = 'Técnicos aprobados y activos en la plataforma';
$raiz = '../';   // la página vive en admin/: sube un nivel para css/, img/, uploads/
include __DIR__ . '/../includes/admin_layout_top.php';
?>

<?php if (isset($_GET['actualizado'])): ?>
<p class="reg-aviso reg-aviso-ok">Cambio guardado.</p>
<?php endif; ?>

<div class="admin-toolbar">
	<form action="admin_tecnicos.php" method="GET">
		<input type="text" name="q" placeholder="Buscar por nombre" value="<?php echo htmlspecialchars($busqueda); ?>">
		<button type="submit">Buscar</button>
	</form>
</div>

<div class="admin-tabla-wrap">
	<?php if (empty($tecnicos)): ?>

	<p class="admin-vacio">Todavía no hay técnicos aprobados.</p>

	<?php else: ?>

	<table class="admin-tabla">
		<thead>
			<tr>
				<th>Nombre</th>
				<th>Correo</th>
				<th>Especialidad</th>
				<th>Calificación</th>
				<th>Certificado</th>
				<th>Estado</th>
				<th>Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($tecnicos as $t): ?>
			<?php $estado = isset($etiquetas_estado[$t['estado_cuenta']]) ? $t['estado_cuenta'] : 'suspendida'; ?>
			<tr>
				<td><?php echo htmlspecialchars($t['nombre']); ?></td>
				<td><?php echo htmlspecialchars($t['email']); ?></td>
				<td><?php echo htmlspecialchars($t['especialidad']); ?></td>
				<td><?php echo number_format((float) $t['promedio_calificacion'], 1); ?> ★</td>
				<td>
					<?php if ($t['certificado_archivo']): ?>
					<a href="../uploads/certificados/<?php echo htmlspecialchars($t['certificado_archivo']); ?>" target="_blank" rel="noopener" class="admin-cert-link">Ver archivo</a>
					<?php else: ?>
					—
					<?php endif; ?>
				</td>
				<td>
					<span class="admin-estado <?php echo $etiquetas_estado[$estado]['clase']; ?>">
						<?php echo $etiquetas_estado[$estado]['texto']; ?>
					</span>
				</td>
				<td>
					<div class="admin-acciones">
						<?php if ($estado === 'activa'): ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $t['id_usuario']; ?>">
							<input type="hidden" name="accion" value="suspender">
							<input type="hidden" name="volver_a" value="admin_tecnicos.php">
							<button type="submit" class="admin-btn">Suspender</button>
						</form>
						<?php elseif ($estado === 'suspendida'): ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $t['id_usuario']; ?>">
							<input type="hidden" name="accion" value="reactivar">
							<input type="hidden" name="volver_a" value="admin_tecnicos.php">
							<button type="submit" class="admin-btn">Reactivar</button>
						</form>
						<?php endif; ?>

						<?php if ($estado !== 'eliminada'): ?>
						<form action="procesar_admin_usuario.php" method="POST" onsubmit="return confirm('¿Eliminar la cuenta de <?php echo htmlspecialchars(addslashes($t['nombre'])); ?>? Va a dejar de poder iniciar sesión.');">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $t['id_usuario']; ?>">
							<input type="hidden" name="accion" value="eliminar">
							<input type="hidden" name="volver_a" value="admin_tecnicos.php">
							<button type="submit" class="admin-btn admin-btn-danger">Eliminar</button>
						</form>
						<?php else: ?>
						<form action="procesar_admin_usuario.php" method="POST">
							<input type="hidden" name="id_usuario" value="<?php echo (int) $t['id_usuario']; ?>">
							<input type="hidden" name="accion" value="reactivar">
							<input type="hidden" name="volver_a" value="admin_tecnicos.php">
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
