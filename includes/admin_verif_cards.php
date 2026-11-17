<?php
$raiz = $raiz ?? '';   // prefijo hacia la raíz del proyecto ('' en la raíz, '../' en admin/)
// includes/admin_verif_cards.php
//
// Pinta la grilla de tarjetas de "Verificaciones pendientes": técnicos
// (tecnico.estado_verificacion = 'pendiente') y empresas
// (empleador.verificada = 0), mezclados y ordenados por fecha de
// registro. Lo usan panel_admin.php (tablero, acotado) y
// verificaciones.php (listado completo).
//
// Espera que quien lo incluye ya haya definido $items_verificacion,
// un array de filas con esta forma:
//   ['tipo' => 'tecnico', 'id' => ..., 'nombre' => ..., 'sub' => ...,
//    'certificado_archivo' => ... ]
//   ['tipo' => 'empresa', 'id' => ..., 'nombre' => ..., 'sub' => ...]

$icono_persona = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.3"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round"/></svg>';
$icono_empresa = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="7" height="10"/><rect x="13" y="4" width="7" height="16"/><path d="M7 14h1M7 17h1M16 8h1M16 11h1M16 14h1" stroke-linecap="round"/></svg>';
?>

<?php if (empty($items_verificacion)): ?>

<p class="admin-vacio">No hay verificaciones pendientes.</p>

<?php else: ?>

<div class="admin-verif-grid">
	<?php foreach ($items_verificacion as $item): ?>
	<div class="admin-verif-card">
		<span class="admin-verif-tipo<?php echo $item['tipo'] === 'empresa' ? ' admin-verif-tipo--empresa' : ''; ?>">
			<?php echo ['empresa' => 'EMPRESA', 'cuenta' => 'CUENTA PLUS'][$item['tipo']] ?? 'TÉCNICO'; ?>
		</span>

		<div class="admin-verif-cuerpo">
			<div class="admin-verif-avatar"><?php echo $item['tipo'] === 'empresa' ? $icono_empresa : $icono_persona; ?></div>
			<div>
				<p class="admin-verif-nombre"><?php echo htmlspecialchars($item['nombre']); ?></p>
				<p class="admin-verif-sub"><?php echo htmlspecialchars($item['sub']); ?></p>
			</div>
		</div>

		<?php if (in_array($item['tipo'], ['tecnico', 'cuenta'], true) && !empty($item['certificado_archivo'])): ?>
		<a href="<?php echo $raiz; ?>uploads/certificados/<?php echo htmlspecialchars($item['certificado_archivo']); ?>" target="_blank" rel="noopener" class="admin-cert-link admin-verif-cert">Ver certificado</a>
		<?php endif; ?>

		<div class="admin-verif-acciones">
			<?php if ($item['tipo'] === 'tecnico'): ?>
			<form action="procesar_admin_tecnico.php" method="POST">
				<input type="hidden" name="id_tecnico" value="<?php echo (int) $item['id']; ?>">
				<input type="hidden" name="accion" value="aprobar">
				<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($volver_a ?? 'verificaciones.php'); ?>">
				<button type="submit" class="admin-btn-aprobar">✓ Aprobar</button>
			</form>
			<form action="procesar_admin_tecnico.php" method="POST">
				<input type="hidden" name="id_tecnico" value="<?php echo (int) $item['id']; ?>">
				<input type="hidden" name="accion" value="rechazar">
				<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($volver_a ?? 'verificaciones.php'); ?>">
				<button type="submit" class="admin-btn-rechazar">✗ Rechazar</button>
			</form>
			<?php elseif ($item['tipo'] === 'cuenta'): ?>
			<form action="procesar_admin_cuenta.php" method="POST">
				<input type="hidden" name="id_usuario" value="<?php echo (int) $item['id']; ?>">
				<input type="hidden" name="accion" value="aprobar">
				<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($volver_a ?? 'verificaciones.php'); ?>">
				<button type="submit" class="admin-btn-aprobar">✓ Verificar</button>
			</form>
			<form action="procesar_admin_cuenta.php" method="POST">
				<input type="hidden" name="id_usuario" value="<?php echo (int) $item['id']; ?>">
				<input type="hidden" name="accion" value="rechazar">
				<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($volver_a ?? 'verificaciones.php'); ?>">
				<button type="submit" class="admin-btn-rechazar">✗ Rechazar</button>
			</form>
			<?php else: ?>
			<form action="procesar_admin_empresa.php" method="POST">
				<input type="hidden" name="id_empleador" value="<?php echo (int) $item['id']; ?>">
				<input type="hidden" name="accion" value="aprobar">
				<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($volver_a ?? 'verificaciones.php'); ?>">
				<button type="submit" class="admin-btn-aprobar">✓ Aprobar</button>
			</form>
			<form action="procesar_admin_empresa.php" method="POST">
				<input type="hidden" name="id_empleador" value="<?php echo (int) $item['id']; ?>">
				<input type="hidden" name="accion" value="rechazar">
				<input type="hidden" name="volver_a" value="<?php echo htmlspecialchars($volver_a ?? 'verificaciones.php'); ?>">
				<button type="submit" class="admin-btn-rechazar">✗ Rechazar</button>
			</form>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<?php endif; ?>