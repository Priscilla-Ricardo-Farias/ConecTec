<?php
$raiz = $raiz ?? '';   // prefijo hacia la raíz del proyecto ('' en la raíz, '../' en admin/)
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<script>

	(function () {
		if (localStorage.getItem('ct_tema') === 'oscuro') {
			document.documentElement.setAttribute('data-theme', 'oscuro');
		}
	})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? $page_title . ' | CT' : 'CT'; ?></title>

<link rel="icon" href="<?php echo $raiz; ?>img/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $raiz; ?>css/nobese.css?v=<?php echo file_exists(__DIR__ . '/../css/nobese.css') ? filemtime(__DIR__ . '/../css/nobese.css') : time(); ?>">
<?php if (!empty($extra_css)): foreach ($extra_css as $hoja_extra): ?>
<link rel="stylesheet" href="<?php echo $raiz . htmlspecialchars($hoja_extra); ?>?v=<?php echo file_exists(__DIR__ . '/../' . $hoja_extra) ? filemtime(__DIR__ . '/../' . $hoja_extra) : time(); ?>">
<?php endforeach; endif; ?>
</head>
<body>

<header class="site-header">
	<div class="header-inner">
		<a href="<?php echo $raiz; ?>nosotros.php" class="logo-link">
			<img src="<?php echo $raiz; ?>img/logo.png" alt="CT" class="logo-img">
		</a>

		<nav class="main-nav" id="menu-principal">
			<ul>
				<li><a href="<?php echo $raiz; ?>nosotros.php">Nosotros</a></li>
				<li><a href="<?php echo $raiz; ?>servicios.php">Servicios</a></li>
				<li><a href="<?php echo $raiz; ?>beneficios.php">Beneficios</a></li>
				<?php if (!isset($_SESSION['usuario_nombre'])): ?>
				<li class="nav-item-movil"><a href="<?php echo $raiz; ?>registro.php">Registrate</a></li>
				<?php endif; ?>
			</ul>
		</nav>

		<?php if (isset($_SESSION['usuario_nombre'])): ?>
		<a href="<?php echo $raiz; ?>perfil.php" class="header-perfil-btn" aria-label="Mi perfil">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21 C4 16.5 7.5 13.5 12 13.5 C16.5 13.5 20 16.5 20 21" stroke-linecap="round"/></svg>
		</a>
		<?php else: ?>
		<div class="header-auth">
			<a href="<?php echo $raiz; ?>login.php" class="btn-login">Iniciar sesión</a>
			<a href="<?php echo $raiz; ?>registro.php" class="btn-registrate">Registrate</a>
		</div>
		<?php endif; ?>

		<button class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-principal">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<script>
(function () {
	var boton = document.querySelector('.site-header .nav-toggle');
	var menu  = document.getElementById('menu-principal');
	if (!boton || !menu) { return; }
	function cerrar() { menu.classList.remove('abierto'); boton.setAttribute('aria-expanded', 'false'); }
	boton.addEventListener('click', function (e) {
		e.stopPropagation();
		var abierto = menu.classList.toggle('abierto');
		boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
	});
	document.addEventListener('click', function (e) {
		if (!menu.contains(e.target)) { cerrar(); }
	});
	window.addEventListener('resize', function () { if (window.innerWidth > 768) { cerrar(); } });
})();
</script>
