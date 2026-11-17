<?php
// verificar_cuenta.php
//
// Pantalla del botón "Verificar cuenta" de Ajustes. Muestra el plan
// que le corresponde al tipo de cuenta (Cliente Plus, Técnico Plus o
// abono de empresa), sus beneficios, si está activo y hasta cuándo, y
// lleva a pagar.php para comprarlo o renovarlo.
//
// Las empresas llegan acá con ?requerido=1 cuando intentan usar la
// plataforma sin abono activo (ver exigir_abono_empresa()).

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

require __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';

$id_usuario  = (int) $_SESSION['usuario_id'];
$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$es_empresa  = $tipo_cuenta === 'empresa';
$es_tecnico  = $tipo_cuenta === 'tecnico';

$clave_plan = clave_plan_para_tipo($tipo_cuenta);
$plan       = planes_conectec()[$clave_plan];
$activa     = suscripcion_activa($conexion, $id_usuario);

$estado_verif = $es_empresa ? null : estado_verificacion_cuenta($conexion, $id_usuario);

$fecha_vence = $activa ? (new DateTime($activa['fecha_vencimiento']))->format('d/m/Y') : null;

// Empresas: para usar la plataforma hacen falta el abono Y la aprobación
// del admin. Acá se les muestra cuál de las dos cosas falta.
$hab           = $es_empresa ? estado_habilitacion_empresa($conexion, $id_usuario) : null;
$nav_bloqueada = $es_empresa && !$hab['habilitada'];   // header sin accesos mientras esté bloqueada
$requerido     = isset($_GET['requerido']) && $nav_bloqueada;

$page_title = $es_empresa ? 'Abono de empresa' : 'Verificar cuenta';
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css', 'css/verificar.css'];
include 'includes/header_hogar.php';
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner">

			<?php $volver_a = 'ajustes.php#cuenta'; include 'includes/boton_volver.php'; ?>

			<h1 class="panel-titulo"><?php echo $es_empresa ? 'Abono de empresa' : 'Verificar cuenta'; ?></h1>

			<?php if (isset($_GET['pagado'])): ?>
			<p class="reg-aviso reg-aviso-ok">
				¡Pago recibido! Tu plan ya está activo.
				<?php if (!$es_empresa): ?>
				Tu solicitud de verificación quedó en revisión: cuando el equipo de Conectec la apruebe, aparece la insignia junto a tu nombre.
				<a href="feed.php">Ir al inicio</a>
				<?php elseif ($hab['habilitada']): ?>
				<a href="feed.php">Ir al inicio</a>
				<?php else: ?>
				Ahora falta que el equipo de Conectec apruebe tu cuenta: apenas lo haga vas a poder usar la plataforma.
				<?php endif; ?>
			</p>
			<?php elseif ($requerido): ?>
			<p class="reg-aviso reg-aviso-error">
				<?php if (!$hab['abono'] && !$hab['aprobada']): ?>
				Para usar Conectec tu empresa tiene que abonar y ser aprobada por el equipo de Conectec.
				<?php elseif (!$hab['abono']): ?>
				Tu cuenta ya fue aprobada, pero para usar Conectec tu empresa necesita un abono activo.
				<?php else: ?>
				Tu abono está activo, pero todavía falta que el equipo de Conectec apruebe tu cuenta.
				<?php endif; ?>
			</p>
			<?php endif; ?>

			<?php if ($es_empresa): ?>
			<div class="ayuda-card plan-card">
				<h2 class="plan-nombre">Para usar Conectec necesitás dos cosas</h2>

				<p class="plan-verificacion plan-verificacion--<?php echo $hab['abono'] ? 'aprobada' : 'pendiente'; ?>">
					1. Abono de empresa:
					<?php echo $hab['abono'] ? 'activo hasta el ' . $fecha_vence . '.' : 'todavía no está pagado.'; ?>
				</p>

				<p class="plan-verificacion plan-verificacion--<?php echo $hab['aprobada'] ? 'aprobada' : 'pendiente'; ?>">
					2. Aprobación del equipo de Conectec:
					<?php echo $hab['aprobada'] ? 'tu cuenta fue aprobada.' : 'en revisión (verificamos los datos y el CUIT de tu empresa).'; ?>
				</p>

				<?php if (!$hab['habilitada']): ?>
				<p class="plan-nota">Hasta que se cumplan las dos, no podés usar el resto de la plataforma.</p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="ayuda-card plan-card">

				<div class="plan-encabezado">
					<div>
						<h2 class="plan-nombre">
							<?php echo htmlspecialchars($plan['nombre']); ?>
							<?php if ($estado_verif === 'aprobada' && $activa) { echo insignia_verificada(); } ?>
						</h2>
						<?php if ($activa): ?>
						<p class="plan-estado plan-estado--activo">Activo hasta el <?php echo $fecha_vence; ?></p>
						<?php else: ?>
						<p class="plan-estado">Todavía no está activo</p>
						<?php endif; ?>

						<?php if ($estado_verif === 'pendiente'): ?>
						<p class="plan-verificacion plan-verificacion--pendiente">Verificación en revisión: el equipo de Conectec la está evaluando.</p>
						<?php elseif ($estado_verif === 'aprobada'): ?>
						<p class="plan-verificacion plan-verificacion--aprobada">
							Cuenta verificada.
							<?php if (!$activa): ?>La insignia vuelve a mostrarse cuando renueves tu plan.<?php endif; ?>
						</p>
						<?php elseif ($estado_verif === 'rechazada'): ?>
						<p class="plan-verificacion plan-verificacion--rechazada">
							No pudimos verificar tu cuenta. Si tenés dudas, escribinos desde <a href="contactar_soporte.php">Contactar soporte</a>.
						</p>
						<?php endif; ?>
					</div>

					<p class="plan-precio">
						<?php echo formatear_precio($plan['precio']); ?>
						<span>/ <?php echo SUSCRIPCION_DIAS; ?> días</span>
					</p>
				</div>

				<ul class="plan-beneficios">
					<?php foreach ($plan['beneficios'] as $beneficio): ?>
					<li><?php echo htmlspecialchars($beneficio); ?></li>
					<?php endforeach; ?>
				</ul>

				<?php if ($es_tecnico && $activa): ?>
				<p class="plan-nota">
					Para ver primero los trabajos cercanos, cargá tu zona en
					<a href="ajustes.php#preferencias">Ajustes → Preferencias de búsqueda</a>.
				</p>
				<?php endif; ?>

				<div class="plan-acciones">
					<a href="pagar.php?plan=<?php echo urlencode($clave_plan); ?>" class="ajustes-btn ajustes-btn--primario">
						<?php
						if ($activa)       { echo 'Renovar'; }
						elseif ($es_empresa) { echo 'Activar abono'; }
						else               { echo 'Verificar mi cuenta'; }
						?>
					</a>
				</div>

				<p class="plan-nota">
					Es un pago por <?php echo SUSCRIPCION_DIAS; ?> días, sin renovación automática.
					<?php if ($activa): ?>Si renovás antes de que venza, los días se suman a los que ya tenés.<?php endif; ?>
				</p>

			</div>

		</div>
	</section>
</main>

<?php include 'includes/footer.php'; ?>