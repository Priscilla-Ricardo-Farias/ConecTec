<?php
// procesar_pago.php
//
// Recibe el formulario de pagar.php y deja activa la suscripción.
//
// >>> HOY: con PAGOS_MODO_PRUEBA = true (ver includes/funciones_suscripcion.php)
//     el pago se aprueba solo, sin cobrar. Sirve para probar todo el
//     circuito, pero NO es un cobro real.
//
// >>> PARA COBRAR DE VERDAD (ej: Mercado Pago Checkout Pro):
//     1) Poné PAGOS_MODO_PRUEBA en false.
//     2) En el bloque marcado más abajo: insertá la fila con
//        estado = 'pendiente', creá la preferencia de pago en la
//        pasarela y redirigí al usuario a su página de pago.
//     3) Creá un webhook que, al confirmarse el pago, pase esa fila a
//        estado = 'activa' con fecha_inicio / fecha_vencimiento y
//        guarde el id de la operación en referencia_pago.
//     Nunca actives un plan por lo que diga el navegador del usuario:
//     solo por la confirmación que llega de la pasarela.

session_start();

if (!isset($_SESSION['usuario_id'])) {
	header('Location: ../login.php');
	exit;
}

require __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';

$id_usuario  = (int) $_SESSION['usuario_id'];
$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$clave_plan  = clave_plan_para_tipo($tipo_cuenta);

// Solo se puede comprar el plan de la propia cuenta.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['plan'] ?? '') !== $clave_plan) {
	header('Location: ../verificar_cuenta.php');
	exit;
}

$token_ok = isset($_SESSION['csrf_pago'], $_POST['token'])
	&& hash_equals($_SESSION['csrf_pago'], $_POST['token']);
unset($_SESSION['csrf_pago']);

if (!$token_ok) {
	header('Location: ../pagar.php?plan=' . urlencode($clave_plan) . '&error=token_invalido');
	exit;
}

$plan = planes_conectec()[$clave_plan];

if (!PAGOS_MODO_PRUEBA) {
	// ---- AQUÍ va la integración con la pasarela de pago (ver arriba). ----
	header('Location: ../pagar.php?plan=' . urlencode($clave_plan) . '&error=pasarela_no_configurada');
	exit;
}

// Si ya tiene el plan activo, los días nuevos se suman a los que le quedan.
$activa = suscripcion_activa($conexion, $id_usuario);
$inicio = $activa
	? $activa['fecha_vencimiento']
	: $conexion->query('SELECT NOW() AS ahora')->fetch_assoc()['ahora'];

$monto      = (float) $plan['precio'];
$dias       = SUSCRIPCION_DIAS;
$referencia = 'PRUEBA-' . strtoupper(bin2hex(random_bytes(4)));

$stmt = $conexion->prepare(
	"INSERT INTO suscripcion (id_usuario, plan, estado, monto, fecha_inicio, fecha_vencimiento, referencia_pago)
	 VALUES (?, ?, 'activa', ?, ?, DATE_ADD(?, INTERVAL ? DAY), ?)"
);
$stmt->bind_param('isdssis', $id_usuario, $clave_plan, $monto, $inicio, $inicio, $dias, $referencia);
$stmt->execute();
$stmt->close();

// Cliente y técnico: el pago abre la solicitud de verificación, que el
// admin aprueba o rechaza (la insignia sale recién con su aprobación).
// Si ya existe una solicitud (aprobada, rechazada o pendiente), se deja
// como está: renovar no obliga a verificarse de nuevo.
// La empresa no la necesita: su verificación es la del registro (CUIT).
if ($tipo_cuenta !== 'empresa') {
	$stmt = $conexion->prepare('INSERT IGNORE INTO verificacion_cuenta (id_usuario) VALUES (?)');
	$stmt->bind_param('i', $id_usuario);
	$stmt->execute();
	$stmt->close();
}

header('Location: ../verificar_cuenta.php?pagado=1');
exit;