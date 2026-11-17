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

		<nav class="main-nav">
			<ul>
				<li><a href="<?php echo $raiz; ?>nosotros.php">Nosotros</a></li>
				<li><a href="<?php echo $raiz; ?>servicios.php">Servicios</a></li>
				<li><a href="<?php echo $raiz; ?>beneficios.php">Beneficios</a></li>
			</ul>
		</nav>

	    
		</button>
	</div>
</header>