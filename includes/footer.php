<?php
$raiz = $raiz ?? '';   // prefijo hacia la raíz del proyecto ('' en la raíz, '../' en admin/)
// includes/footer.php
// Footer general del sitio. Se incluye con: include 'includes/footer.php';

// El footer visual solo se muestra en las páginas públicas de entrada y en
// login / registro. En el resto solo se cierra el HTML.
$ct_pagina = basename($_SERVER['PHP_SELF']);
$ct_con_footer = in_array($ct_pagina, [
	'nosotros.php', 'servicios.php', 'beneficios.php',
	'login.php', 'login_admin.php',
	'registro.php', 'registro_usuario.php', 'registro_empresa.php', 'registro_tecnico.php',
], true);

// TEMPORAL: el candado de acceso al login admin (esquina inferior izquierda
// del footer) solo se muestra en estas tres páginas. Para sacarlo: borrar
// $ct_con_candado y el <a class="footer-admin-lock"> de más abajo.
$ct_con_candado = in_array($ct_pagina, ['nosotros.php', 'servicios.php', 'beneficios.php'], true);
?>
<?php if ($ct_con_footer): ?>
<footer class="site-footer">
	<div class="footer-inner">
		<div class="footer-brand">
			<img src="<?php echo $raiz; ?>img/logo.png" alt="CT" class="footer-logo">
			<p>El punto de encuentro entre profesionales y oportunidades.</p>
		</div>

		<div class="footer-col">
			<h4>Contacto</h4>
			<ul>
				<li>info@ct.com</li>
				<li>+54 9 223 000-0000</li>
				<li>Mar del Plata, Argentina</li>
			</ul>
		</div>
	</div>

	<div class="footer-bottom">
		<?php if ($ct_con_candado): ?>
		<a href="<?php echo $raiz; ?>admin/login_admin.php" class="footer-admin-lock" aria-label="Acceso administrador">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
		</a>
		<?php endif; ?>
		<p>&copy; <?php echo date('Y'); ?> GT. Todos los derechos reservados.</p>
	</div>
</footer>
<?php endif; ?>

</body>
</html>
