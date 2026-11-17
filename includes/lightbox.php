<?php
// includes/lightbox.php
//
// Permite agrandar las fotos de los trabajos: al tocar la imagen se abre
// a pantalla completa sobre un fondo oscuro. Se cierra con la X, con un
// clic afuera de la foto o con la tecla Esc.
//
// Se incluye al final de feed.php y calendario.php. Las imágenes que se
// pueden agrandar son las de ".post-card-image" y
// ".calendario-proximo-imagen".
?>
<style>
.post-card-image img,
.calendario-proximo-imagen img {
	cursor: zoom-in;
}

.ct-lightbox {
	position: fixed;
	inset: 0;
	z-index: 1000;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 24px;
	background: rgba(8, 18, 38, 0.88);
}

.ct-lightbox[hidden] {
	display: none;
}

.ct-lightbox img {
	max-width: min(1100px, 100%);
	max-height: 100%;
	object-fit: contain;
	border-radius: 10px;
	box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
	cursor: default;
}

.ct-lightbox-cerrar {
	position: absolute;
	top: 16px;
	right: 20px;
	width: 42px;
	height: 42px;
	border: none;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.15);
	color: #fff;
	font-size: 1.6rem;
	line-height: 1;
	cursor: pointer;
}

.ct-lightbox-cerrar:hover {
	background: rgba(255, 255, 255, 0.28);
}
</style>

<div class="ct-lightbox" id="ct-lightbox" role="dialog" aria-modal="true" aria-label="Imagen ampliada" hidden>
	<button type="button" class="ct-lightbox-cerrar" aria-label="Cerrar imagen">&times;</button>
	<img src="" alt="">
</div>

<script>
(function () {
	var caja   = document.getElementById('ct-lightbox');
	var imagen = caja.querySelector('img');
	var cierre = caja.querySelector('.ct-lightbox-cerrar');

	function abrir(origen) {
		imagen.src = origen.currentSrc || origen.src;
		imagen.alt = origen.alt || '';
		caja.hidden = false;
		document.body.style.overflow = 'hidden';
		cierre.focus();
	}

	function cerrar() {
		caja.hidden = true;
		imagen.removeAttribute('src');
		document.body.style.overflow = '';
	}

	document.addEventListener('click', function (e) {
		var img = e.target.closest('.post-card-image img, .calendario-proximo-imagen img');
		if (img) { abrir(img); }
	});

	caja.addEventListener('click', function (e) {
		if (e.target !== imagen) { cerrar(); }
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !caja.hidden) { cerrar(); }
	});
})();
</script>
