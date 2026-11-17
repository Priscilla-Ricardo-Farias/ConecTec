<?php
// ajustes.php
//
// Pantalla de configuración de la cuenta. Se arma en 6 secciones que
// se pueden recorrer con el menú de la izquierda (son anclas #, no
// hay JS de por medio, así que funciona sin problema aunque falle
// algo del script).
//
// La sección de preferencias cambia sola según el tipo de cuenta:
//   - Empresa            → "Preferencias de contratación"
//   - Técnico / usuario   → "Preferencias de búsqueda"
//
// TODO cuando conectes la base de datos: precargar los <input>/<select>
// de cada sección con los valores guardados del usuario logueado, y
// que los formularios (que ya apuntan a procesar_ajustes.php) graben
// los cambios de verdad.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones_suscripcion.php';
require_once __DIR__ . '/includes/funciones_especialidad.php';
require_once __DIR__ . '/includes/funciones_certificados.php';

$tipo_cuenta = $_SESSION['usuario_tipo'] ?? 'cliente';
$es_empresa  = $tipo_cuenta === 'empresa';
$es_tecnico  = $tipo_cuenta === 'tecnico';

$id_usuario_propio = (int) $_SESSION['usuario_id'];

/* =========================================================
   PREFERENCIAS GUARDADAS (privacidad, apariencia)
   ========================================================= */
$stmt = $conexion->prepare(
	'SELECT privacidad_perfil, privacidad_cv_visible, modo_oscuro, pref_ubicacion, pref_puesto,
	        noti_ofertas, noti_postulaciones, noti_cambios_postulaciones, noti_mensajes, noti_alertas_preferencias
	 FROM preferencia_usuario WHERE id_usuario = ?'
);
$stmt->bind_param('i', $id_usuario_propio);
$stmt->execute();
$prefs = $stmt->get_result()->fetch_assoc();
$stmt->close();

$prefs = $prefs ?: [
	'privacidad_perfil'      => 'publico',
	'privacidad_cv_visible'  => 1,
	'modo_oscuro'            => 0,
	'pref_ubicacion'         => '',
	'pref_puesto'            => '',
	// sin fila guardada: todos los avisos activados
	'noti_ofertas'               => 1,
	'noti_postulaciones'         => 1,
	'noti_cambios_postulaciones' => 1,
	'noti_mensajes'              => 1,
	'noti_alertas_preferencias'  => 1,
];

/* =========================================================
   CV DEL TÉCNICO (si la cuenta es de técnico)
   ========================================================= */
$tecnico_actual = null;

// Especialidades y certificados adicionales del técnico (sección "Especialidades y certificados")
$especialidades_tecnico = [];
$claves_tecnico         = [];
$otras_especialidades   = [];
$certificados_extra     = [];
$max_certificados_extra = CERT_MAX_ACTIVOS;   // pendientes + aprobados
$certificados_activos    = 0;
$estados_certificado     = certificado_estados();

if ($es_tecnico) {
	$stmt = $conexion->prepare('SELECT id_tecnico, cv_archivo, certificado_archivo, estado_verificacion FROM tecnico WHERE id_usuario = ?');
	$stmt->bind_param('i', $id_usuario_propio);
	$stmt->execute();
	$tecnico_actual = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($tecnico_actual) {
		$especialidades_tecnico = especialidades_de_tecnico($conexion, $tecnico_actual['id_tecnico']);

		foreach ($especialidades_tecnico as $esp_t) {
			$claves_tecnico[] = $esp_t['clave'];
			if (!es_especialidad_de_lista($esp_t['clave'])) {
				$otras_especialidades[] = $esp_t['especialidad'];
			}
		}

		// Certificados adicionales con su estado de revisión (pendiente / aprobado / rechazado)
		try {
			$certificados_extra = certificados_del_tecnico($conexion, (int) $tecnico_actual['id_tecnico']);
		} catch (Throwable $e) {
			$certificados_extra = []; // todavía no se corrió sql/migracion_certificados_revision.sql
		}
		foreach ($certificados_extra as $c_extra) {
			if (in_array($c_extra['estado'], ['pendiente', 'aprobado'], true)) {
				$certificados_activos++;
			}
		}
	}
}

$avisos_cert = [
	'esp_ok'        => ['ok',    'Guardamos tus especialidades.'],
	'esp_vacia'     => ['error', 'Elegí al menos una especialidad.'],
	'enviado'       => ['ok',    'Enviamos tu certificado a revisión. Quedó pendiente de aprobación: te avisamos por notificación cuando un administrador lo revise.'],
	'cancelado'     => ['ok',    'Cancelaste el envío del certificado.'],
	'no_cancelable' => ['error', 'Solo se pueden cancelar los certificados que siguen pendientes.'],
	'nombre'        => ['error', 'Escribí el nombre del certificado (entre 3 y 150 caracteres).'],
	'especialidad'  => ['error', 'Elegí la especialidad del certificado: tiene que ser una de las tuyas. Si no está, agregala arriba en "Mis especialidades".'],
	'archivo'       => ['error', 'Adjuntá el archivo que acredite el certificado.'],
	'formato'       => ['error', 'El certificado tiene que ser JPG, PNG, WEBP o PDF.'],
	'grande'        => ['error', 'El archivo pesa demasiado (máximo 5 MB).'],
	'limite'        => ['error', 'Llegaste al máximo de certificados pendientes o aprobados. Esperá a que revisen alguno o cancelá un envío pendiente.'],
	'reintento'     => ['error', 'Ese certificado no se puede volver a presentar (solo los rechazados que todavía no reenviaste).'],
	'error'         => ['error', 'No pudimos guardar los cambios. Probá de nuevo.'],
];
$aviso_cert = $es_tecnico ? ($avisos_cert[$_GET['cert'] ?? ''] ?? null) : null;

$page_title = "Ajustes";
$extra_css  = ['css/feed.css', 'css/panel.css', 'css/ajustes.css'];
include 'includes/header_hogar.php';

$suscripcion_actual = suscripcion_activa($conexion, $id_usuario_propio);
$cuenta_verificada   = $suscripcion_actual !== null;
$estado_verif        = $es_empresa ? null : estado_verificacion_cuenta($conexion, $id_usuario_propio);
?>

<main>
	<section class="panel-page">
		<div class="panel-page-inner ajustes-layout">

			<div class="ajustes-volver"><?php $volver_a = 'feed.php'; include 'includes/boton_volver.php'; ?></div>

			<nav class="ajustes-nav" aria-label="Secciones de ajustes">
				<a href="#cuenta">Cuenta</a>
				<a href="#notificaciones">Notificaciones</a>
				<a href="#preferencias"><?php echo $es_empresa ? 'Preferencias de contratación' : 'Preferencias de búsqueda'; ?></a>
				<?php if ($es_tecnico): ?><a href="#certificados">Especialidades y certificados</a><?php endif; ?>
				<a href="#privacidad">Privacidad y seguridad</a>
				<a href="#apariencia">Apariencia</a>
				<a href="#ayuda">Ayuda</a>
			</nav>

			<div class="ajustes-contenido">

				<?php if (isset($_GET['guardado'])): ?>
				<p class="reg-aviso reg-aviso-ok">Los cambios se guardaron correctamente.</p>
				<?php endif; ?>

				<!-- ================= CUENTA ================= -->
				<section id="cuenta" class="ajustes-seccion">
					<h2>Cuenta</h2>

					<div class="ajustes-item">
						<div class="ajustes-item-texto">
							<h3>Editar perfil</h3>
							<p><?php echo $es_tecnico ? 'Cambiá tu foto de perfil, tu nombre y tu descripción.' : 'Actualizá tu nombre y demás datos de tu perfil.'; ?></p>
						</div>
						<a href="editar_perfil.php" class="ajustes-btn">Editar</a>
					</div>

					<form class="ajustes-item" action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="correo">
						<div class="ajustes-item-texto">
							<h3>Cambiar correo electrónico</h3>
							<p>Correo actual: <?php echo htmlspecialchars($_SESSION['usuario_correo'] ?? ''); ?></p>
							<input type="email" name="correo_nuevo" placeholder="Nuevo correo electrónico" class="ajustes-input" required>
						</div>
						<button type="submit" class="ajustes-btn">Guardar</button>
					</form>

					<div class="ajustes-item">
						<div class="ajustes-item-texto">
							<h3>Cambiar contraseña</h3>
							<p>Te vamos a pedir tu contraseña actual antes de definir la nueva.</p>
						</div>
						<a href="cambiar_contrasena.php" class="ajustes-btn">Cambiar</a>
					</div>

					<?php if ($tipo_cuenta === 'cliente'): ?>
					<div class="ajustes-item">
						<div class="ajustes-item-texto">
							<h3>Cambiar tipo de cuenta a Técnico</h3>
							<p>Presentá tu certificado de técnico para empezar a ofrecer tus servicios en la plataforma.</p>
						</div>
						<a href="cambiar_a_tecnico.php" class="ajustes-btn">Convertirme en técnico</a>
					</div>
					<?php endif; ?>

					<form class="ajustes-item" action="logout.php" method="POST">
						<div class="ajustes-item-texto">
							<h3>Cerrar sesión</h3>
							<p>Vas a tener que volver a ingresar tu correo y contraseña la próxima vez.</p>
						</div>
						<button type="submit" class="ajustes-btn">Cerrar sesión</button>
					</form>

					<div class="ajustes-item ajustes-item--peligro">
						<div class="ajustes-item-texto">
							<h3>Eliminar cuenta</h3>
							<p>Se borran tu perfil, tus publicaciones y tu historial. Esta acción no se puede deshacer.</p>
						</div>
						<a href="eliminar_cuenta.php" class="ajustes-btn ajustes-btn--peligro">Eliminar cuenta</a>
					</div>
				</section>

				<!-- ================= NOTIFICACIONES ================= -->
				<section id="notificaciones" class="ajustes-seccion">
					<h2>Notificaciones</h2>
					<p class="ajustes-seccion-desc">Elegí qué avisos querés recibir.</p>

					<form action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="notificaciones">

						<div class="ajustes-toggle-fila">
							<span>Nuevas ofertas de trabajo</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="noti_ofertas"<?php echo !empty($prefs['noti_ofertas']) ? ' checked' : ''; ?>>
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>

						<div class="ajustes-toggle-fila">
							<span>Postulaciones recibidas/enviadas</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="noti_postulaciones"<?php echo !empty($prefs['noti_postulaciones']) ? ' checked' : ''; ?>>
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>

						<div class="ajustes-toggle-fila">
							<span>Cambios en postulaciones</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="noti_cambios_postulaciones"<?php echo !empty($prefs['noti_cambios_postulaciones']) ? ' checked' : ''; ?>>
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>

						<div class="ajustes-toggle-fila">
							<span>Mensajes</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="noti_mensajes"<?php echo !empty($prefs['noti_mensajes']) ? ' checked' : ''; ?>>
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>

						<div class="ajustes-toggle-fila">
							<span>Alertas de empleos según preferencias</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="noti_alertas_preferencias"<?php echo !empty($prefs['noti_alertas_preferencias']) ? ' checked' : ''; ?>>
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>

						<div class="ajustes-guardar-wrap">
							<button type="submit" class="ajustes-btn ajustes-btn--primario">Guardar cambios</button>
						</div>
					</form>
				</section>

				<!-- ================= PREFERENCIAS ================= -->
				<section id="preferencias" class="ajustes-seccion">

					<?php if ($es_empresa): ?>

					<h2>Preferencias de contratación</h2>
					<p class="ajustes-seccion-desc">Para que te lleguen los perfiles que más te sirven.</p>

					<form action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="preferencias_contratacion">

						<div class="ajustes-campo">
							<label for="pref_categorias">Categorías de trabajadores</label>
							<select id="pref_categorias" name="pref_categorias[]" class="ajustes-input" multiple size="6">
								<?php echo opciones_especialidades_html(); ?>
							</select>
						</div>

						<div class="ajustes-campo">
							<label for="pref_ubicacion">Ubicación</label>
							<input type="text" id="pref_ubicacion" name="pref_ubicacion" class="ajustes-input" placeholder="Ciudad o zona">
						</div>

						<div class="ajustes-campo">
							<span class="ajustes-campo-label">Modalidad de trabajo</span>
							<div class="ajustes-checkbox-grupo">
								<label><input type="checkbox" name="pref_modalidad[]" value="presencial"> Presencial</label>
								<label><input type="checkbox" name="pref_modalidad[]" value="hibrido"> Híbrido</label>
								<label><input type="checkbox" name="pref_modalidad[]" value="remoto"> Remoto</label>
							</div>
						</div>

						<div class="ajustes-campo">
							<label for="pref_tipo_contratacion">Tipo de contratación</label>
							<select id="pref_tipo_contratacion" name="pref_tipo_contratacion" class="ajustes-input">
								<option value="">Seleccionar</option>
								<option value="tiempo_completo">Tiempo completo</option>
								<option value="medio_tiempo">Medio tiempo</option>
								<option value="por_trabajo">Por trabajo / changa</option>
								<option value="temporal">Temporal</option>
							</select>
						</div>

						<div class="ajustes-guardar-wrap">
							<button type="submit" class="ajustes-btn ajustes-btn--primario">Guardar cambios</button>
						</div>
					</form>

					<?php else: ?>

					<h2>Preferencias de búsqueda</h2>
					<p class="ajustes-seccion-desc">Para que te lleguen las ofertas que más te sirven.</p>

					<form action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="preferencias_busqueda">

						<div class="ajustes-campo">
							<label for="pref_puesto">Puesto/categoría</label>
							<select id="pref_puesto" name="pref_puesto" class="ajustes-input">
								<option value="">Seleccionar</option>
								<?php foreach (especialidades_conectec() + ['otro' => 'Otro'] as $valor_puesto => $etiqueta_puesto): ?>
								<option value="<?php echo $valor_puesto; ?>"<?php echo ($prefs['pref_puesto'] ?? '') === $valor_puesto ? ' selected' : ''; ?>><?php echo $etiqueta_puesto; ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ajustes-campo">
							<label for="pref_ubicacion">Ubicación</label>
							<input type="text" id="pref_ubicacion" name="pref_ubicacion" class="ajustes-input" placeholder="Ciudad o zona" value="<?php echo htmlspecialchars($prefs['pref_ubicacion'] ?? ''); ?>">
						</div>

						<div class="ajustes-campo">
							<span class="ajustes-campo-label">Modalidad</span>
							<div class="ajustes-checkbox-grupo">
								<label><input type="checkbox" name="pref_modalidad[]" value="presencial"> Presencial</label>
								<label><input type="checkbox" name="pref_modalidad[]" value="hibrido"> Híbrido</label>
								<label><input type="checkbox" name="pref_modalidad[]" value="remoto"> Remoto</label>
							</div>
						</div>

						<div class="ajustes-campo">
							<label for="pref_tipo_contrato">Tipo de contrato</label>
							<select id="pref_tipo_contrato" name="pref_tipo_contrato" class="ajustes-input">
								<option value="">Seleccionar</option>
								<option value="tiempo_completo">Tiempo completo</option>
								<option value="medio_tiempo">Medio tiempo</option>
								<option value="por_trabajo">Por trabajo / changa</option>
								<option value="temporal">Temporal</option>
							</select>
						</div>

						<div class="ajustes-campo">
							<span class="ajustes-campo-label">Rango salarial</span>
							<div class="ajustes-rango-salarial">
								<input type="number" name="pref_salario_min" class="ajustes-input" placeholder="Mínimo" min="0">
								<span>—</span>
								<input type="number" name="pref_salario_max" class="ajustes-input" placeholder="Máximo" min="0">
							</div>
						</div>

						<div class="ajustes-guardar-wrap">
							<button type="submit" class="ajustes-btn ajustes-btn--primario">Guardar cambios</button>
						</div>
					</form>

					<?php endif; ?>
				</section>

				<?php if ($es_tecnico && !empty($tecnico_actual['id_tecnico'])): ?>
				<!-- ================= ESPECIALIDADES Y CERTIFICADOS (solo técnicos) ================= -->
				<section id="certificados" class="ajustes-seccion">
					<h2>Especialidades y certificados</h2>
					<p class="ajustes-seccion-desc">Marcá todas las especialidades que tenés: vas a ver los trabajos de cualquiera de ellas y vas a poder postularte. Sumá tus certificados técnicos para respaldarlas.</p>

					<?php if ($aviso_cert): ?>
					<p class="reg-aviso <?php echo $aviso_cert[0] === 'ok' ? 'reg-aviso-ok' : 'reg-aviso-error'; ?>" role="status"><?php echo htmlspecialchars($aviso_cert[1]); ?></p>
					<?php endif; ?>

					<form action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="especialidades">

						<div class="ajustes-campo">
							<span class="ajustes-campo-label">Mis especialidades</span>
							<div class="esp-chips">
								<?php foreach (especialidades_conectec() as $clave_esp => $etiqueta_esp): ?>
								<label class="esp-chip">
									<input type="checkbox" name="especialidades[]" value="<?php echo htmlspecialchars($clave_esp); ?>"<?php echo in_array($clave_esp, $claves_tecnico, true) ? ' checked' : ''; ?>>
									<span><?php echo htmlspecialchars($etiqueta_esp); ?></span>
								</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="ajustes-campo">
							<label for="esp_otra">¿Tenés otra que no está en la lista?</label>
							<input type="text" id="esp_otra" name="esp_otra" class="ajustes-input" maxlength="200" placeholder="Escribila acá (si son varias, separalas con coma)" value="<?php echo htmlspecialchars(implode(', ', $otras_especialidades)); ?>">
						</div>

						<div class="ajustes-guardar-wrap">
							<button type="submit" class="ajustes-btn ajustes-btn--primario">Guardar especialidades</button>
						</div>
					</form>

					<div class="ajustes-subseccion" id="cert-seccion">
						<h3>Mis certificados</h3>
						<p class="ajustes-seccion-desc">Cada certificado lo revisa un administrador. Mientras esté pendiente o rechazado no se muestra como verificado en tu perfil. Te avisamos por notificación cuando lo revisen.</p>

						<ul class="cert-lista">
							<?php if (!empty($tecnico_actual['certificado_archivo'])): ?>
							<?php $cert_principal_pdf = strtolower(pathinfo($tecnico_actual['certificado_archivo'], PATHINFO_EXTENSION)) === 'pdf'; ?>
							<li class="cert-item">
								<a class="cert-mini" href="uploads/certificados/<?php echo htmlspecialchars($tecnico_actual['certificado_archivo']); ?>" target="_blank" rel="noopener">
									<?php if ($cert_principal_pdf): ?><span>PDF</span><?php else: ?><img src="uploads/certificados/<?php echo htmlspecialchars($tecnico_actual['certificado_archivo']); ?>" alt="Certificado de registro"><?php endif; ?>
								</a>
								<div class="cert-info">
									<strong>Certificado con el que te registraste</strong>
									<small>
										<?php
										$estados_cert = ['verificado' => 'Verificado', 'pendiente' => 'Pendiente de revisión', 'rechazado' => 'Rechazado'];
										echo htmlspecialchars($estados_cert[$tecnico_actual['estado_verificacion']] ?? '');
										?>
									</small>
								</div>
							</li>
							<?php endif; ?>

							<?php foreach ($certificados_extra as $cert): ?>
							<?php
							$cert_es_pdf = strtolower(pathinfo($cert['archivo'], PATHINFO_EXTENSION)) === 'pdf';
							$cert_datos  = array_filter([
								!empty($cert['especialidad']) ? etiqueta_especialidad($cert['especialidad']) : '',
								!empty($cert['anio']) ? 'Año ' . (int) $cert['anio'] : '',
								!empty($cert['institucion']) ? $cert['institucion'] : '',
							]);
							?>
							<li class="cert-item cert-item--<?php echo htmlspecialchars($cert['estado']); ?>">
								<a class="cert-mini" href="uploads/certificados/<?php echo htmlspecialchars($cert['archivo']); ?>" target="_blank" rel="noopener">
									<?php if ($cert_es_pdf): ?><span>PDF</span><?php else: ?><img src="uploads/certificados/<?php echo htmlspecialchars($cert['archivo']); ?>" alt="Certificado"><?php endif; ?>
								</a>
								<div class="cert-info">
									<strong><?php echo htmlspecialchars($cert['nombre'] !== '' ? $cert['nombre'] : 'Certificado técnico'); ?></strong>
									<?php if (!empty($cert_datos)): ?>
									<small><?php echo htmlspecialchars(implode(' · ', $cert_datos)); ?></small>
									<?php endif; ?>
									<span class="cert-estado cert-estado--<?php echo htmlspecialchars($cert['estado']); ?>"><?php echo htmlspecialchars($estados_certificado[$cert['estado']] ?? $cert['estado']); ?></span>

									<?php if ($cert['estado'] === 'rechazado' && !empty($cert['motivo_rechazo'])): ?>
									<p class="cert-motivo"><strong>Motivo del rechazo:</strong> <?php echo htmlspecialchars($cert['motivo_rechazo']); ?></p>
									<?php endif; ?>

									<?php if (!empty($cert['nombre_anterior'])): ?>
									<small class="cert-historial">Reintento de «<?php echo htmlspecialchars($cert['nombre_anterior']); ?>», que fue rechazado.</small>
									<?php endif; ?>
									<?php if ($cert['estado'] === 'rechazado' && (int) $cert['reintentos'] > 0): ?>
									<small class="cert-historial">Ya lo volviste a presentar: mirá el nuevo envío.</small>
									<?php endif; ?>
								</div>

								<?php if ($cert['estado'] === 'rechazado' && (int) $cert['reintentos'] === 0): ?>
								<button type="button" class="ajustes-btn ajustes-btn--chico ajustes-btn--primario"
									data-reintento
									data-id="<?php echo (int) $cert['id_certificado']; ?>"
									data-nombre="<?php echo htmlspecialchars($cert['nombre']); ?>"
									data-clave="<?php echo htmlspecialchars((string) $cert['clave_especialidad']); ?>"
									data-anio="<?php echo (int) $cert['anio']; ?>"
									data-institucion="<?php echo htmlspecialchars((string) $cert['institucion']); ?>">Volver a presentar</button>
								<?php elseif ($cert['estado'] === 'pendiente'): ?>
								<form action="procesar/procesar_ajustes.php" method="POST" onsubmit="return confirm('¿Cancelar el envío de este certificado?');">
									<input type="hidden" name="seccion" value="certificado_eliminar">
									<input type="hidden" name="id_certificado" value="<?php echo (int) $cert['id_certificado']; ?>">
									<button type="submit" class="ajustes-btn ajustes-btn--chico ajustes-btn--peligro">Cancelar envío</button>
								</form>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>

							<?php if (empty($tecnico_actual['certificado_archivo']) && empty($certificados_extra)): ?>
							<li class="cert-vacio">Todavía no enviaste certificados.</li>
							<?php endif; ?>
						</ul>

						<h3>Enviar un certificado para revisión</h3>

						<form action="procesar/procesar_ajustes.php" method="POST" enctype="multipart/form-data" class="cert-subir" id="cert-form">
							<input type="hidden" name="seccion" value="certificado_subir">
							<input type="hidden" name="cert_reemplaza" id="cert-reemplaza" value="">

							<p class="cert-reintento-aviso" id="cert-reintento-aviso" hidden>
								Estás volviendo a presentar <strong id="cert-reintento-nombre"></strong>. Corregí lo que indicó el administrador y adjuntá el archivo nuevo.
								<button type="button" id="cert-reintento-cancelar">Cancelar</button>
							</p>

							<div class="ajustes-campo">
								<label for="cert_nombre">Nombre del certificado</label>
								<input type="text" id="cert_nombre" name="cert_nombre" class="ajustes-input" maxlength="150" minlength="3" required placeholder="Ej: Técnico en instalaciones eléctricas domiciliarias">
							</div>

							<div class="cert-subir-fila">
								<div class="ajustes-campo">
									<label for="cert_especialidad">Especialidad en la que te certificaste</label>
									<select id="cert_especialidad" name="cert_especialidad" class="ajustes-input" required>
										<option value="" disabled selected>Elegí una de tus especialidades</option>
										<?php foreach ($especialidades_tecnico as $esp_t): ?>
										<option value="<?php echo htmlspecialchars($esp_t['clave']); ?>"><?php echo htmlspecialchars(etiqueta_especialidad($esp_t['especialidad'])); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="ajustes-campo">
									<label for="cert_anio">Año <span style="font-weight:400;text-transform:none;">(opcional)</span></label>
									<select id="cert_anio" name="cert_anio" class="ajustes-input">
										<option value="">Sin especificar</option>
										<?php for ($anio_c = (int) date('Y'); $anio_c >= (int) date('Y') - 50; $anio_c--): ?>
										<option value="<?php echo $anio_c; ?>"><?php echo $anio_c; ?></option>
										<?php endfor; ?>
									</select>
								</div>
							</div>

							<div class="ajustes-campo">
								<label for="cert_institucion">Institución que lo emitió <span style="font-weight:400;text-transform:none;">(opcional)</span></label>
								<input type="text" id="cert_institucion" name="cert_institucion" class="ajustes-input" maxlength="150" placeholder="Ej: Escuela Técnica N.º 1, Cámara de Instaladores...">
							</div>

							<div class="ajustes-campo">
								<label for="cert_archivo">Archivo que lo acredite</label>
								<input type="file" id="cert_archivo" name="cert_archivo" accept="image/*,.pdf" required class="ajustes-input">
								<span class="ajustes-cv-ayuda">JPG, PNG, WEBP o PDF, hasta 5 MB. Podés tener hasta <?php echo (int) $max_certificados_extra; ?> certificados pendientes o aprobados; ahora tenés <?php echo (int) $certificados_activos; ?>.</span>
							</div>

							<div class="ajustes-guardar-wrap">
								<button type="submit" class="ajustes-btn ajustes-btn--primario">Enviar para revisión</button>
							</div>
						</form>

						<script>
							// "Volver a presentar": carga el formulario con los datos del certificado rechazado
							(function () {
								var form    = document.getElementById('cert-form');
								var aviso   = document.getElementById('cert-reintento-aviso');
								var campoId = document.getElementById('cert-reemplaza');

								function salirDelReintento() {
									campoId.value = '';
									aviso.hidden = true;
									form.reset();
								}

								document.querySelectorAll('[data-reintento]').forEach(function (boton) {
									boton.addEventListener('click', function () {
										campoId.value = boton.dataset.id;
										document.getElementById('cert-reintento-nombre').textContent = '«' + boton.dataset.nombre + '»';
										document.getElementById('cert_nombre').value = boton.dataset.nombre;
										document.getElementById('cert_institucion').value = boton.dataset.institucion;
										document.getElementById('cert_anio').value = boton.dataset.anio && boton.dataset.anio !== '0' ? boton.dataset.anio : '';

										var esp = document.getElementById('cert_especialidad');
										if (boton.dataset.clave) { esp.value = boton.dataset.clave; }

										aviso.hidden = false;
										form.scrollIntoView({ behavior: 'smooth', block: 'center' });
										document.getElementById('cert_archivo').focus({ preventScroll: true });
									});
								});

								document.getElementById('cert-reintento-cancelar').addEventListener('click', salirDelReintento);
							})();
						</script>
					</div>
				</section>
				<?php endif; ?>

				<!-- ================= PRIVACIDAD Y SEGURIDAD ================= -->
				<section id="privacidad" class="ajustes-seccion">
					<h2>Privacidad y seguridad</h2>

					<form action="procesar/procesar_ajustes.php" method="POST" enctype="multipart/form-data">
						<input type="hidden" name="seccion" value="privacidad">

						<div class="ajustes-campo">
							<label for="privacidad_perfil">Quién puede ver el perfil</label>
							<select id="privacidad_perfil" name="privacidad_perfil" class="ajustes-input">
								<option value="todos">Todos</option>
								<option value="registrados">Solo usuarios registrados</option>
								<option value="contactos">Solo mis contactos</option>
							</select>
						</div>

						<?php if ($es_tecnico): ?>
						<div class="ajustes-campo">
							<span class="ajustes-campo-label">Currículum (CV)</span>
							<?php if (!empty($tecnico_actual['cv_archivo'])): ?>
							<p class="ajustes-cv-actual">
								Ya tenés un CV cargado:
								<a href="uploads/cv/<?php echo htmlspecialchars($tecnico_actual['cv_archivo']); ?>" target="_blank" rel="noopener">ver archivo actual</a>
								<button type="submit" form="form-eliminar-cv" class="ajustes-btn ajustes-btn--chico ajustes-btn--peligro" style="margin-left:10px;" onclick="return confirm('¿Seguro que querés eliminar tu CV?');">Eliminar CV</button>
							</p>
							<?php else: ?>
							<p class="ajustes-cv-actual">Todavía no subiste tu CV.</p>
							<?php endif; ?>
							<input type="file" name="cv_archivo" accept=".pdf,.doc,.docx" class="ajustes-input">
							<span class="ajustes-cv-ayuda">PDF o Word (.pdf, .doc, .docx). Para actualizarlo, elegí un archivo nuevo y guardá los cambios: reemplaza al anterior.</span>
						</div>

						<div class="ajustes-toggle-fila">
							<span>Visibilidad del CV</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="privacidad_cv_visible" <?php echo $prefs['privacidad_cv_visible'] ? 'checked' : ''; ?>>
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>
						<?php endif; ?>

						<div class="ajustes-guardar-wrap">
							<button type="submit" class="ajustes-btn ajustes-btn--primario">Guardar cambios</button>
						</div>
					</form>

					<?php if ($es_tecnico && !empty($tecnico_actual['cv_archivo'])): ?>
					<!-- Formulario aparte para "Eliminar CV" (el botón está arriba y apunta acá con form="form-eliminar-cv") -->
					<form id="form-eliminar-cv" action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="eliminar_cv">
					</form>
					<?php endif; ?>

					<div class="ajustes-item">
						<div class="ajustes-item-texto">
							<h3><?php echo $es_empresa ? 'Abono de empresa' : 'Verificación de cuenta'; ?></h3>
							<p>
								<?php if ($cuenta_verificada): ?>
									<?php echo $es_empresa ? 'Tu abono está activo' : 'Tu plan Plus está activo'; ?> hasta el <?php echo (new DateTime($suscripcion_actual['fecha_vencimiento']))->format('d/m/Y'); ?>.
									<?php if ($estado_verif === 'aprobada'): ?>Tu cuenta está verificada.
									<?php elseif ($estado_verif === 'pendiente'): ?>Tu verificación está en revisión.
									<?php elseif ($estado_verif === 'rechazada'): ?>Tu verificación fue rechazada.<?php endif; ?>
								<?php elseif ($es_empresa): ?>
									Tu abono no está activo.
								<?php else: ?>
									Todavía no verificaste tu cuenta.
								<?php endif; ?>
							</p>
						</div>
						<a href="verificar_cuenta.php" class="ajustes-btn">
							<?php echo $cuenta_verificada ? 'Ver plan' : ($es_empresa ? 'Activar abono' : 'Verificar cuenta'); ?>
						</a>
					</div>
				</section>

				<!-- ================= APARIENCIA ================= -->
				<section id="apariencia" class="ajustes-seccion">
					<h2>Apariencia</h2>

					<form action="procesar/procesar_ajustes.php" method="POST">
						<input type="hidden" name="seccion" value="apariencia">

						<div class="ajustes-toggle-fila">
							<span>Modo oscuro</span>
							<label class="ajustes-switch">
								<input type="checkbox" name="modo_oscuro" id="modo_oscuro_toggle">
								<span class="ajustes-switch-slider"></span>
							</label>
						</div>

						<div class="ajustes-guardar-wrap">
							<button type="submit" class="ajustes-btn ajustes-btn--primario">Guardar cambios</button>
						</div>
					</form>
				</section>

				<!-- ================= AYUDA ================= -->
				<section id="ayuda" class="ajustes-seccion">
					<h2>Ayuda</h2>

					<a href="preguntas_frecuentes.php" class="ajustes-item ajustes-item--link">
						<span>Preguntas frecuentes</span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>

					<a href="centro_ayuda.php" class="ajustes-item ajustes-item--link">
						<span>Centro de ayuda</span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>

					<a href="contactar_soporte.php" class="ajustes-item ajustes-item--link">
						<span>Contactar soporte</span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>

					<a href="reportar_problema.php" class="ajustes-item ajustes-item--link">
						<span>Reportar un problema</span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18 L15 12 L9 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>
				</section>

			</div>

		</div>
	</section>
</main>

<script>
	const toggleModoOscuro = document.getElementById('modo_oscuro_toggle');

	toggleModoOscuro.checked = localStorage.getItem('ct_tema') === 'oscuro';

	toggleModoOscuro.addEventListener('change', function () {
		if (this.checked) {
			document.documentElement.setAttribute('data-theme', 'oscuro');
			localStorage.setItem('ct_tema', 'oscuro');
		} else {
			document.documentElement.removeAttribute('data-theme');
			localStorage.setItem('ct_tema', 'claro');
		}
	});
</script>

<?php include 'includes/footer.php'; ?>