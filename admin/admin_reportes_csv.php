<?php
// admin_reportes_csv.php
//
// Descarga el reporte del período elegido en admin_reportes.php como CSV.
// Separador ";" y BOM UTF-8: es lo que Excel en español (Argentina)
// abre bien, con tildes y ñ, con doble clic.

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

$nombres_plan = [];
foreach (planes_conectec() as $clave => $plan) {
	$nombres_plan[$clave] = $plan['nombre'];
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_conectec_' . date('Y-m-d') . '.csv"');

echo "\xEF\xBB\xBF";
$salida = fopen('php://output', 'w');

$fila = function (array $celdas) use ($salida) {
	fputcsv($salida, array_map('admin_csv_seguro', $celdas), ';', '"', '');
};

$fila(['Reporte ConecTec', $rangos[$dias]]);
$fila(['Generado', date('d/m/Y H:i')]);
$fila([]);

$fila(['Indicador', 'Valor']);
$fila(['Usuarios nuevos', $r['usuarios_nuevos']]);
$fila(['Cuentas activas (total)', $r['usuarios_activos']]);
$fila(['Clientes activos', $r['clientes']]);
$fila(['Técnicos activos', $r['tecnicos']]);
$fila(['Empresas activas', $r['empresas']]);
$fila(['Publicaciones', $r['trabajos_nuevos']]);
$fila(['Publicaciones abiertas', $r['trabajos_estado']['abierto']]);
$fila(['Publicaciones en progreso', $r['trabajos_estado']['en_progreso']]);
$fila(['Publicaciones cerradas', $r['trabajos_estado']['cerrado']]);
$fila(['Publicaciones de clientes', $r['trabajos_origen']['cliente']]);
$fila(['Publicaciones de empresas', $r['trabajos_origen']['empresa']]);
$fila(['Postulaciones', $r['postulaciones']]);
$fila(['Postulaciones aceptadas', $r['postulaciones_estado']['aceptada']]);
$fila(['Postulaciones rechazadas', $r['postulaciones_estado']['rechazada']]);
$fila(['Postulaciones pendientes', $r['postulaciones_estado']['pendiente']]);
$fila(['Tasa de aceptación (%)', $r['tasa_aceptacion'] === null ? '' : number_format($r['tasa_aceptacion'], 1, ',', '')]);
$fila(['Calificación promedio', $r['calificacion_prom'] === null ? '' : number_format($r['calificacion_prom'], 1, ',', '')]);
$fila(['Cantidad de calificaciones', $r['calificaciones']]);
$fila(['Suscripciones activas ahora', $r['suscripciones_activas']]);
$fila(['Ingresos por planes (ARS)' . (PAGOS_MODO_PRUEBA ? ' - MODO DE PRUEBA' : ''), number_format($r['ingresos'], 2, ',', '')]);
$fila(['Mensajes de soporte', $r['soporte']]);
$fila(['Mensajes de soporte prioritarios', $r['soporte_prioritarios']]);
$fila(['Verificaciones pendientes (ahora)', $r['pendientes']['total']]);
$fila([]);

$fila(['Publicaciones por categoría', 'Cantidad']);
foreach ($r['categorias'] as $c) {
	$fila([$c['categoria'], $c['c']]);
}
$fila([]);

$fila(['Ingresos por plan', 'Pagos', 'Total (ARS)']);
foreach ($r['ingresos_plan'] as $p) {
	$fila([$nombres_plan[$p['plan']] ?? $p['plan'], $p['c'], number_format((float) $p['total'], 2, ',', '')]);
}
$fila([]);

$fila(['Últimos 6 meses', 'Registros', 'Publicaciones']);
foreach ($r['meses'] as $m) {
	$fila([$m['etiqueta'], $m['usuarios'], $m['trabajos']]);
}

fclose($salida);
exit;
