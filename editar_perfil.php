<?php
// editar_perfil.php
//
// Pantalla "Editar perfil" (se entra desde Ajustes > Cuenta).
// Se precarga con los datos reales que hay en la base.
//
//   - Técnico: estilo Instagram. Puede cambiar su foto de perfil
//     (subir una nueva o quitarla), su nombre y su descripción.
//   - Cliente / empresa: solo el nombre (como antes).
//
// La foto se guarda en uploads/perfiles/ y la ruta queda en
// usuario.foto_perfil; la descripción va en tecnico.descripcion.

session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$es_empresa  = $tipo_cuenta === 'empresa';

// Trae nombre y foto reales del usuario logueado
$stmt = $conexion->prepare('SELECT nombre, foto_perfil FROM usuario WHERE id_usuario = ?');
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$datos_usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$nombre_actual = $datos_usuario['nombre'] ?? $_SESSION['usuario_nombre'];
$foto_actual   = $datos_usuario['foto_perfil'] ?? '';

// ¿Es técnico? (solo ellos tienen perfil público con foto y descripción)
$stmt = $conexion->prepare('SELECT descripcion FROM tecnico WHERE id_usuario = ?');
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$fila_tecnico = $stmt->get_result()->fetch_assoc();
$stmt->close();

$es_tecnico         = $fila_tecnico !== null;
$descripcion_actual = $fila_tecnico['descripcion'] ?? '';
$foto_url           = $foto_actual ?: 'img/logo.png';
$max_descripcion    = 300;

// Si es empresa, trae también el nombre de la empresa
$nombre_empresa_actual = '';
if ($es_empresa) {
	$stmt = $conexion->prepare('SELECT empresa FROM empleador WHERE id_usuario = ?');
	$stmt->bind_param('i', $_SESSION['usuario_id']);
	$stmt->execute();
	$datos_empresa = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$nombre_empresa_actual = $datos_empresa['empresa'] ?? '';
}

$errores = [
	'1'       => 'Completá tu nombre.',
	'nombre'  => 'Completá tu nombre.',
	'formato' => 'La foto tiene que ser JPG, PNG o WEBP.',
	'grande'  => 'La foto pesa demasiado (máximo 5 MB).',
	'foto'    => 'No pudimos guardar la foto. Probá de nuevo.',
];
$error_msg = $errores[$_GET['error'] ?? ''] ?? null;

$page_title = "Editar perfil";
$extra_css  = ['css/reg.css', 'css/editar_perfil.css'];
include 'includes/header.php';
?>

<main>
	<section class="reg-page">
		<div class="reg-card ep-card">
			<div class="reg-card-topbar"></div>

			<div class="reg-card-body">
				<a href="ajustes.php#cuenta" class="reg-back">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					Volver
				</a>

				<h1>Editar perfil</h1>
				<p class="reg-subtitle">
					<?php echo $es_tecnico ? 'Así te ven los clientes y las empresas' : 'Actualizá tus datos personales'; ?>
				</p>

				<?php if ($error_msg): ?>
				<p class="reg-aviso reg-aviso-error" role="alert"><?php echo htmlspecialchars($error_msg); ?></p>
				<?php endif; ?>

				<form class="reg-form" action="procesar/procesar_editar_perfil.php" method="POST"<?php echo $es_tecnico ? ' enctype="multipart/form-data" id="ep-form"' : ''; ?>>

					<?php if ($es_tecnico): ?>
					<!-- ================= FOTO DE PERFIL ================= -->
					<div class="ep-foto">
						<label class="ep-avatar-anillo" for="ep-foto-input" title="Cambiar foto de perfil">
							<span class="ep-avatar">
								<img id="ep-avatar-img" src="<?php echo htmlspecialchars($foto_url); ?>" alt="Tu foto de perfil" data-original="<?php echo htmlspecialchars($foto_url); ?>">
							</span>
							<span class="ep-avatar-camara" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8 H7.5 L9 5.5 H15 L16.5 8 H20 V19 H4 Z" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5"/></svg>
							</span>
						</label>

						<div class="ep-foto-acciones">
							<p class="ep-foto-nombre">@<?php echo htmlspecialchars($nombre_actual); ?></p>
							<label for="ep-foto-input" class="ep-link">Cambiar foto de perfil</label>
							<button type="button" class="ep-link ep-link--quitar" id="ep-quitar"<?php echo $foto_actual ? '' : ' hidden'; ?>>Quitar foto</button>
						</div>

						<input type="file" name="foto_perfil" id="ep-foto-input" accept="image/jpeg,image/png,image/webp" hidden>
						<input type="hidden" name="quitar_foto" id="ep-quitar-campo" value="0">
					</div>

					<p class="ep-foto-error" id="ep-foto-error" role="alert" hidden></p>
					<?php endif; ?>

					<?php if ($es_empresa): ?>
					<div class="reg-field">
						<label for="nombre_empresa">Nombre de empresa</label>
						<input type="text" id="nombre_empresa" name="nombre_empresa" value="<?php echo htmlspecialchars($nombre_empresa_actual); ?>" required>
					</div>
					<?php else: ?>
					<div class="reg-field">
						<label for="nombre">Nombre</label>
						<input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($nombre_actual); ?>" required>
					</div>
					<?php endif; ?>

					<?php if ($es_tecnico): ?>
					<div class="reg-field ep-descripcion">
						<label for="descripcion">Descripción</label>
						<textarea id="descripcion" name="descripcion" rows="4" maxlength="<?php echo $max_descripcion; ?>" placeholder="Contá quién sos, qué hacés y en qué te especializás..."><?php echo htmlspecialchars($descripcion_actual); ?></textarea>
						<span class="ep-contador" id="ep-contador"><?php echo mb_strlen($descripcion_actual); ?> / <?php echo $max_descripcion; ?></span>
					</div>
					<?php endif; ?>

					<div class="reg-submit-wrap">
						<button type="submit" class="reg-submit">Guardar cambios</button>
					</div>

				</form>
			</div>
		</div>
	</section>
</main>

<?php if ($es_tecnico): ?>
<script>
(function () {
	var input    = document.getElementById('ep-foto-input');
	var img      = document.getElementById('ep-avatar-img');
	var quitar   = document.getElementById('ep-quitar');
	var campoQ   = document.getElementById('ep-quitar-campo');
	var errorBox = document.getElementById('ep-foto-error');
	var texto    = document.getElementById('descripcion');
	var contador = document.getElementById('ep-contador');
	var LADO     = 600;                 // la foto se recorta cuadrada y se achica a 600 px
	var MAX_MB   = 5;
	var TIPOS    = ['image/jpeg', 'image/png', 'image/webp'];
	var porDefecto = 'img/logo.png';

	function avisar(msg) {
		errorBox.textContent = msg || '';
		errorBox.hidden = !msg;
	}

	// Contador de la descripción
	function contar() {
		var n = texto.value.length;
		contador.textContent = n + ' / ' + texto.maxLength;
		contador.classList.toggle('ep-contador--limite', n >= texto.maxLength - 30);
	}
	texto.addEventListener('input', contar);
	contar();

	// Recorta al centro en un cuadrado y achica: el avatar queda parejo y liviano,
	// y no choca con el límite de subida del servidor (2 MB por defecto).
	function prepararFoto(archivo) {
		return new Promise(function (resolver) {
			var url = URL.createObjectURL(archivo);
			var tmp = new Image();
			tmp.onload = function () {
				var lado = Math.min(tmp.naturalWidth, tmp.naturalHeight);
				var salida = Math.min(lado, LADO);
				var lienzo = document.createElement('canvas');
				lienzo.width = lienzo.height = salida;
				lienzo.getContext('2d').drawImage(
					tmp,
					(tmp.naturalWidth - lado) / 2, (tmp.naturalHeight - lado) / 2, lado, lado,
					0, 0, salida, salida
				);
				URL.revokeObjectURL(url);
				lienzo.toBlob(function (blob) {
					resolver(blob ? new File([blob], 'perfil.jpg', { type: 'image/jpeg' }) : archivo);
				}, 'image/jpeg', 0.9);
			};
			tmp.onerror = function () { URL.revokeObjectURL(url); resolver(archivo); };
			tmp.src = url;
		});
	}

	input.addEventListener('change', function () {
		var archivo = input.files[0];
		if (!archivo) { return; }
		avisar('');

		if (TIPOS.indexOf(archivo.type) === -1) {
			input.value = '';
			avisar('La foto tiene que ser JPG, PNG o WEBP.');
			return;
		}
		if (archivo.size > MAX_MB * 1024 * 1024 * 4) {      // límite holgado: igual se achica
			input.value = '';
			avisar('La foto es demasiado pesada. Elegí otra.');
			return;
		}

		prepararFoto(archivo).then(function (listo) {
			var dt = new DataTransfer();
			dt.items.add(listo);
			input.files = dt.files;
			img.src = URL.createObjectURL(listo);
			campoQ.value = '0';
			quitar.hidden = false;
		});
	});

	quitar.addEventListener('click', function () {
		input.value = '';
		campoQ.value = '1';
		img.src = porDefecto;
		quitar.hidden = true;
		avisar('');
	});
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
