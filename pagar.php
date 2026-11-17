<?php
// pagar.php
//
// Resumen del pedido antes de pagar. Solo deja comprar el plan que le
// corresponde al tipo de cuenta de quien está logueado. El formulario
// va a procesar/procesar_pago.php.
//
// No se piden ni guardan datos de tarjeta acá: con una pasarela real
// (ej: Mercado Pago) el pago se hace en la página de la pasarela.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/includes/funciones_suscripcion.php';

$id_usuario  = (int) $_SESSION['usuario_id'];
$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$clave_plan  = clave_plan_para_tipo($tipo_cuenta);

// Una empresa sin habilitar ve el header sin accesos (solo puede pagar o salir).
$nav_bloqueada = empresa_bloqueada($conexion);

if (($_GET['plan'] ?? '') !== $clave_plan) {
	header('Location: verificar_cuenta.php');
	exit;
}

$plan   = planes_conectec()[$clave_plan];
$activa = suscripcion_activa($conexion, $id_usuario);

// Token para que el pago solo se pueda confirmar desde esta pantalla.
$_SESSION['csrf_pago'] = bin2hex(random_bytes(16));

$errores = [
	'pasarela_no_configurada' => 'El cobro online todavía no está habilitado. Probá de nuevo más tarde.',
	'token_invalido'          => 'La sesión del pago venció. Volvé a intentarlo.',
];

$page_title = 'Confirmar pago';
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css', 'css/verificar.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">

			<?php $volver_a = 'verificar_cuenta.php'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo">Confirmar pago</h1>

			<?php if (isset($_GET['error'], $errores[$_GET['error']])): ?>
			<p class="reg-aviso reg-aviso-error"><?php echo htmlspecialchars($errores[$_GET['error']]); ?></p>
			<?php endif; ?>

			<?php if (PAGOS_MODO_PRUEBA): ?>
			<p class="plan-aviso-prueba">Modo de prueba: no se cobra nada, el plan se activa al confirmar.</p>
			<?php endif; ?>

			<div class="ayuda-card plan-card">

				<div class="plan-resumen-fila">
					<span><?php echo htmlspecialchars($plan['nombre']); ?> · <?php echo SUSCRIPCION_DIAS; ?> días</span>
					<strong><?php echo formatear_precio($plan['precio']); ?></strong>
				</div>

				<?php if ($activa): ?>
				<p class="plan-nota">
					Ya tenés el plan activo hasta el <?php echo (new DateTime($activa['fecha_vencimiento']))->format('d/m/Y'); ?>.
					Los <?php echo SUSCRIPCION_DIAS; ?> días nuevos se suman a partir de esa fecha.
				</p>
				<?php endif; ?>

				<form action="procesar/procesar_pago.php" method="POST" class="plan-acciones">
					<input type="hidden" name="plan" value="<?php echo htmlspecialchars($clave_plan); ?>">
					<input type="hidden" name="token" value="<?php echo $_SESSION['csrf_pago']; ?>">
					<button type="submit" class="ajustes-btn ajustes-btn--primario">
						Pagar <?php echo formatear_precio($plan['precio']); ?>
					</button>
					<a href="verificar_cuenta.php" class="ajustes-btn">Cancelar</a>
				</form>

			</div>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>