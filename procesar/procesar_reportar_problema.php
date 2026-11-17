<?php
// procesar_reportar_problema.php
//
// Guarda el reporte en "mensaje_soporte" (tipo = 'reporte'). El
// "asunto" se arma con el tipo de problema elegido y, si la cargó,
// la referencia de dónde pasó.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '../');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';

$tipo_problema = trim($_POST['tipo_problema'] ?? '');
$donde         = trim($_POST['donde'] ?? '');
$descripcion   = trim($_POST['descripcion'] ?? '');

if ($tipo_problema === '' || $descripcion === '') {
	header('Location: ../reportar_problema.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];
$asunto     = $tipo_problema;

// Con plan activo, el reporte queda marcado para atenderlo primero.
if (tiene_plan_activo($conexion, $id_usuario)) {
	$asunto = '[PRIORITARIO] ' . $asunto;
}
$mensaje    = ($donde !== '' ? "Dónde: {$donde}\n\n" : '') . $descripcion;

$stmt = $conexion->prepare(
	"INSERT INTO mensaje_soporte (id_usuario, tipo, asunto, mensaje)
	 VALUES (?, 'reporte', ?, ?)"
);
$stmt->bind_param('iss', $id_usuario, $asunto, $mensaje);
$stmt->execute();
$stmt->close();

header('Location: ../reportar_problema.php?enviado=1');
exit;