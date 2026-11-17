<?php
// procesar_admin_certificado.php
//
// Recibe la decisión del administrador sobre un certificado pendiente
// (admin/certificados.php): aprobar, o rechazar con un motivo.
//
// Seguridad: solo con sesión de administrador (nunca de técnico), solo por POST y
// con el token CSRF del panel. Un técnico no tiene forma de llegar a esta decisión.

session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login_admin.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_admin.php';
require_once __DIR__ . '/../includes/funciones_certificados.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: certificados.php');
	exit;
}

if (!admin_csrf_valido()) {
	header('Location: certificados.php?resultado=csrf');
	exit;
}

$id_certificado = (int) ($_POST['id_certificado'] ?? 0);
$accion         = $_POST['accion'] ?? '';
$motivo         = (string) ($_POST['motivo'] ?? '');

$resultado = certificado_resolver($conexion, $id_certificado, $accion, $motivo, (int) $_SESSION['admin_id']);

// Después de decidir se vuelve a la lista de pendientes; si faltó el motivo, a la misma pestaña.
header('Location: certificados.php?resultado=' . urlencode($resultado['codigo']));
exit;
