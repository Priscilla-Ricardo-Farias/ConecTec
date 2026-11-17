		</main>
	</div>

</div>

<script>
// Campanita de notificaciones: abre/cierra el panel, se cierra al hacer
// clic afuera o con Escape.
(function () {
	var boton = document.getElementById('admin-bell-btn');
	var panel = document.getElementById('admin-notif-panel');
	if (!boton || !panel) return;

	function abrir(abierto) {
		panel.hidden = !abierto;
		boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
	}

	boton.addEventListener('click', function (e) {
		e.stopPropagation();
		abrir(panel.hidden);
	});
	panel.addEventListener('click', function (e) { e.stopPropagation(); });
	document.addEventListener('click', function () { abrir(false); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { abrir(false); boton.focus(); }
	});
})();
</script>

</body>
</html>
