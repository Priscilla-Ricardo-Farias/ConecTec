<?php
// includes/boton_volver.php
//
// Botón "Volver" de las pantallas internas (mensajes, chat, calendario,
// notificaciones, ajustes, perfil, ayuda). Se usa dentro del contenedor
// de la página: primero se define $volver_a y después se hace
// include 'includes/boton_volver.php';
//
// (Ojo: en un comentario de una línea no se puede escribir la etiqueta de
// cierre de PHP, porque corta el modo PHP y el resto se imprime en pantalla.)
//
//   $volver_a      -> pantalla "padre": adonde va el botón si no se sabe
//                     de dónde venía la persona (entró directo, recargó
//                     la página, etc.). Sin JavaScript, el botón siempre
//                     va acá.
//   $volver_texto  -> (opcional) texto del botón. Por defecto "Volver".
//
// Con JavaScript, el botón vuelve a la pantalla desde la que se llegó
// (con su mismo estado: el mes del calendario, la conversación, etc.), en
// vez de mandar siempre al mismo lugar. Eso se recuerda por pantalla en
// sessionStorage, y se ignoran las recargas y los redirects de los
// formularios (guardar cambios, mandar un mensaje) para que "Volver" no
// termine devolviendo a la pantalla de la que se acaba de guardar.

$volver_a     = $volver_a ?? 'feed.php';
$volver_texto = $volver_texto ?? 'Volver';
?>
<a href="<?php echo htmlspecialchars($volver_a); ?>" class="btn-volver">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
	<?php echo htmlspecialchars($volver_texto); ?>
</a>
<?php if (!defined('CT_VOLVER_JS')): define('CT_VOLVER_JS', true); ?>
<script>
(function () {
	var boton = document.querySelector('.btn-volver');
	if (!boton) { return; }

	var clave  = 'ct_volver:' + location.pathname;
	var actual = location.pathname + location.search;

	// 1) Recordar de qué pantalla se llegó a esta
	try {
		var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};

		// Si se llegó tocando "Volver", no se anota nada (evita que dos
		// pantallas queden mandándose la una a la otra).
		var marca = sessionStorage.getItem('ct_volviendo');
		sessionStorage.removeItem('ct_volviendo');
		var llegoConVolver = (marca === actual);

		if ((nav.type || 'navigate') === 'navigate' && !llegoConVolver) {
			var previa = document.referrer ? new URL(document.referrer) : null;
			var sirve  = previa
				&& previa.origin === location.origin
				&& previa.pathname !== location.pathname        // otra pantalla, no la misma
				&& (nav.redirectCount || 0) === 0               // no viene de guardar un formulario
				&& previa.pathname.indexOf('/procesar/') === -1;

			if (sirve) {
				sessionStorage.setItem(clave, previa.pathname + previa.search);
			} else if (!document.referrer) {
				sessionStorage.removeItem(clave);               // entró directo: no se sabe de dónde venía
			}
		}
	} catch (e) { /* sin sessionStorage: queda el destino fijo */ }

	// 2) Al tocar "Volver", ir a la pantalla recordada
	boton.addEventListener('click', function (ev) {
		try {
			var destino = sessionStorage.getItem(clave);
			if (destino && destino !== actual) {
				ev.preventDefault();
				sessionStorage.setItem('ct_volviendo', destino);
				location.href = destino;
			}
		} catch (e) { /* si falla, sigue el link normal */ }
	});
})();
</script>
<?php endif; ?>
