<?php
// verificaciones.php
//
// Pantalla "Verificaciones" del sidebar: junta técnicos con
// tecnico.estado_verificacion = 'pendiente' y empresas con
// empleador.verificada = 0, para que el admin las apruebe o
// rechace. A diferencia de admin_tecnicos.php (que ahora es el
// listado de técnicos YA aprobados), acá solo se muestran
// pendientes — no hay selector de estado.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';

$tecnicos_pendientes = $conexion->query(
	"SELECT t.id_tecnico AS id, t.especialidad, t.certificado_archivo, u.nombre, u.fecha_registro
	 FROM tecnico t
	 JOIN usuario u ON u.id_usuario = t.id_usuario
	 WHERE t.estado_verificacion = 'pendiente'
	 ORDER BY u.fecha_registro DESC"
)->fetch_all(MYSQLI_ASSOC);

$empresas_pendientes = $conexion->query(
	"SELECT e.id_empleador AS id, e.empresa, e.cuit, u.nombre, u.fecha_registro
	 FROM empleador e
	 JOIN usuario u ON u.id_usuario = e.id_usuario
	 WHERE e.verificada = 0
	 ORDER BY u.fecha_registro DESC"
)->fetch_all(MYSQLI_ASSOC);

$items_verificacion = [];
foreach ($tecnicos_pendientes as $t) {
	$items_verificacion[] = [
		'tipo'                 => 'tecnico',
		'id'                   => $t['id'],
		'nombre'               => $t['nombre'],
		'sub'                  => $t['especialidad'],
		'certificado_archivo'  => $t['certificado_archivo'],
		'fecha'                => $t['fecha_registro'],
	];
}
foreach ($empresas_pendientes as $e) {
	$items_verificacion[] = [
		'tipo'   => 'empresa',
		'id'     => $e['id'],
		'nombre' => $e['empresa'],
		'sub'    => 'CUIT ' . $e['cuit'],
		'fecha'  => $e['fecha_registro'],
	];
}
// Cuentas de cliente/técnico que pagaron Plus y piden la insignia.
foreach (verificaciones_cuenta_pendientes($conexion) as $c) {
	$items_verificacion[] = $c;
}
usort($items_verificacion, fn($a, $b) => strtotime($b['fecha']) <=> strtotime($a['fecha']));

$volver_a = 'verificaciones.php';

$page_title      = "Verificaciones";
$admin_active    = 'verificaciones';
$admin_titulo    = 'Verificaciones pendientes';
$admin_subtitulo = 'Técnicos, empresas y cuentas Plus esperando aprobación';
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
			<span class="admin-badge-count"><?php echo count($items_verificacion); ?> pendientes</span>
		</h2>
	</div>

	<?php include __DIR__ . '/../includes/admin_verif_cards.php'; ?>
</section>

<?php include __DIR__ . '/../includes/admin_layout_bottom.php'; ?>