<?php
// procesar_contactar_soporte.php
//
// Guarda el mensaje de contacto en "mensaje_soporte" (tipo = 'contacto').

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

$asunto  = trim($_POST['asunto'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if ($asunto === '' || $mensaje === '') {
	header('Location: ../contactar_soporte.php');
	exit;
}

$id_usuario = (int) $_SESSION['usuario_id'];

// Con plan activo, el mensaje queda marcado para atenderlo primero.
if (tiene_plan_activo($conexion, $id_usuario)) {
	$asunto = '[PRIORITARIO] ' . $asunto;
}

$stmt = $conexion->prepare(
	"INSERT INTO mensaje_soporte (id_usuario, tipo, asunto, mensaje)
	 VALUES (?, 'contacto', ?, ?)"
);
$stmt->bind_param('iss', $id_usuario, $asunto, $mensaje);
$stmt->execute();
$stmt->close();

header('Location: ../contactar_soporte.php?enviado=1');
exit;