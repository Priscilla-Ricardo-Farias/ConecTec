<?php
// diagnostico_calificaciones.php
//
// TEMPORAL. Sirve para averiguar por qué no aparece el popup de calificación.
// Abrilo en el navegador con la sesión iniciada como el usuario que PUBLICÓ
// el trabajo (no como el técnico). Cuando ya ande, borrá este archivo.

session_start();
require __DIR__ . '/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: login.php');
	exit;
}

function h($s) { return htmlspecialchars((string) $s); }

$uid = (int) $_SESSION['usuario_id'];

// 1) Fecha según PHP y según MySQL (la que usa el popup es la de MySQL)
$fecha_php = date('Y-m-d H:i:s');
$mysql     = $conexion->query('SELECT CURDATE() AS hoy, NOW() AS ahora, @@time_zone AS tz')->fetch_assoc();

// 2) ¿Existe la columna calificacion.id_trabajo? (la agrega la migración)
$tiene_columna = false;
try {
	$tiene_columna = $conexion->query("SHOW COLUMNS FROM calificacion LIKE 'id_trabajo'")->num_rows > 0;
} catch (mysqli_sql_exception $e) {
	$tiene_columna = false;
}

// 3) Trabajos publicados por el usuario logueado
$calificado_sql = $tiene_columna
	? '(SELECT COUNT(*) FROM calificacion ca WHERE ca.id_trabajo = tr.id_trabajo)'
	: '0';

$stmt = $conexion->prepare(
	"SELECT tr.id_trabajo, tr.titulo, tr.estado, tr.fecha_trabajo,
	        (tr.fecha_trabajo IS NOT NULL AND tr.fecha_trabajo < CURDATE()) AS ya_paso,
	        ut.nombre AS tecnico_aceptado,
	        $calificado_sql AS calificado
	 FROM trabajo tr
	 LEFT JOIN trabajo_cliente tc ON tc.id_trabajo = tr.id_trabajo
	 LEFT JOIN cliente c          ON c.id_cliente = tc.id_cliente
	 LEFT JOIN trabajo_empleador te ON te.id_trabajo = tr.id_trabajo
	 LEFT JOIN empleador emp        ON emp.id_empleador = te.id_empleador
	 LEFT JOIN postulacion p ON p.id_trabajo = tr.id_trabajo AND p.estado = 'aceptada'
	 LEFT JOIN tecnico t     ON t.id_tecnico = p.id_tecnico
	 LEFT JOIN usuario ut    ON ut.id_usuario = t.id_usuario
	 WHERE c.id_usuario = ? OR emp.id_usuario = ?
	 ORDER BY tr.id_trabajo DESC"
);
$stmt->bind_param('ii', $uid, $uid);
$stmt->execute();
$trabajos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function veredicto($t, $tiene_columna, $hoy) {
	if (!$tiene_columna)                    return ['mal', 'Falta correr migracion_calificaciones.sql (la tabla calificacion no tiene la columna id_trabajo).'];
	if ($t['estado'] !== 'en_progreso') {
		if ($t['estado'] === 'cerrado' && (int) $t['calificado'] > 0) return ['ok', 'Ya fue calificado (el trabajo quedó cerrado).'];
		return ['mal', "El trabajo está en estado \"{$t['estado']}\". Tiene que estar \"en_progreso\", que se pone al ACEPTAR a un técnico desde el feed."];
	}
	if (empty($t['tecnico_aceptado']))      return ['mal', 'No hay ninguna postulación aceptada en este trabajo.'];
	if (empty($t['fecha_trabajo']))         return ['mal', 'El trabajo no tiene fecha_trabajo cargada.'];
	if (!(int) $t['ya_paso'])               return ['mal', "La fecha del trabajo ({$t['fecha_trabajo']}) todavía no pasó según MySQL (hoy para MySQL es $hoy). Tiene que ser anterior a hoy."];
	if ((int) $t['calificado'] > 0)         return ['ok', 'Ya fue calificado.'];
	return ['ok', 'TODO OK: el popup debería aparecer en el feed.'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico de calificaciones</title>
<style>
	body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 0 16px; color: #081226; }
	h1 { font-size: 1.4rem; }
	.caja { border: 1px solid #d7dce6; border-radius: 8px; padding: 12px 16px; margin: 14px 0; background: #f8f9fc; }
	.mal  { color: #b91c1c; font-weight: bold; }
	.ok   { color: #15803d; font-weight: bold; }
	table { border-collapse: collapse; width: 100%; margin-top: 10px; }
	th, td { border: 1px solid #d7dce6; padding: 8px; text-align: left; font-size: 0.9rem; vertical-align: top; }
	th { background: #eef1f7; }
	small { color: #6b7280; }
</style>
</head>
<body>

<h1>Diagnóstico de calificaciones</h1>
<p><small>Página temporal: borrala cuando ya funcione. <a href="feed.php">Volver al feed</a></small></p>

<div class="caja">
	<p><b>Usuario logueado:</b> id <?php echo $uid; ?> (<?php echo h($_SESSION['usuario_nombre'] ?? ''); ?>, tipo: <?php echo h($_SESSION['usuario_tipo'] ?? '?'); ?>)</p>
	<p><b>Fecha según PHP:</b> <?php echo h($fecha_php); ?></p>
	<p><b>Fecha según MySQL:</b> <?php echo h($mysql['ahora']); ?> (hoy = <?php echo h($mysql['hoy']); ?>, zona horaria: <?php echo h($mysql['tz']); ?>)
		<br><small>El popup usa la fecha de MySQL. Si acá sigue mostrando el día real y no el que pusiste en la compu, MySQL no está tomando el cambio de fecha.</small></p>
	<p><b>Migración (columna calificacion.id_trabajo):</b>
		<?php if ($tiene_columna): ?><span class="ok">Aplicada</span><?php else: ?><span class="mal">NO aplicada. Corré migracion_calificaciones.sql</span><?php endif; ?></p>
</div>

<h2>Trabajos publicados por este usuario</h2>

<?php if (empty($trabajos)): ?>
<p class="mal">Este usuario no publicó ningún trabajo. ¿Iniciaste sesión con el técnico? Tenés que entrar con la cuenta (cliente o empresa) que publicó el trabajo.</p>
<?php else: ?>
<table>
	<tr><th>#</th><th>Título</th><th>Estado</th><th>Fecha del trabajo</th><th>Técnico aceptado</th><th>¿Va a aparecer?</th></tr>
	<?php foreach ($trabajos as $t): ?>
	<?php [$clase, $texto] = veredicto($t, $tiene_columna, $mysql['hoy']); ?>
	<tr>
		<td><?php echo (int) $t['id_trabajo']; ?></td>
		<td><?php echo h($t['titulo']); ?></td>
		<td><?php echo h($t['estado']); ?></td>
		<td><?php echo h($t['fecha_trabajo'] ?: '—'); ?></td>
		<td><?php echo h($t['tecnico_aceptado'] ?: '—'); ?></td>
		<td class="<?php echo $clase; ?>"><?php echo h($texto); ?></td>
	</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>

</body>
</html>
