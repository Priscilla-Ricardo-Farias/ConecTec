<?php
// includes/indicadores_header.php
//
// Calcula los "puntitos" de la barra de arriba (header_hogar.php y
// header_interno.php):
//   $hay_notif_sin_leer    → hay notificaciones que todavía no se leyeron
//   $hay_mensajes_sin_leer → hay mensajes recibidos que todavía no se leyeron
//
// Si algo falla (falta una tabla, no hay sesión) simplemente no se
// muestra el puntito: nunca tiene que romper la pantalla.

$hay_notif_sin_leer    = false;
$hay_mensajes_sin_leer = false;

if (!empty($_SESSION['usuario_id'])) {
	try {
		if (!isset($conexion) || !($conexion instanceof mysqli)) {
			require_once __DIR__ . '/../conexion.php';
		}
		$ind_id = (int) $_SESSION['usuario_id'];

		$stmt = $conexion->prepare(
			'SELECT 1 FROM notificacion
			 WHERE id_usuario = ? AND leida = 0 AND (visible_desde IS NULL OR visible_desde <= NOW())
			 LIMIT 1'
		);
		$stmt->bind_param('i', $ind_id);
		$stmt->execute();
		$hay_notif_sin_leer = $stmt->get_result()->num_rows > 0;
		$stmt->close();

		// No cuentan los mensajes que el remitente eliminó para todos ni los que yo eliminé para mí.
		try {
			$stmt = $conexion->prepare(
				'SELECT 1 FROM mensaje m
				 JOIN usuario_conversacion uc ON uc.id_conversacion = m.id_conversacion AND uc.id_usuario = ?
				 WHERE m.id_usuario <> ? AND m.leido = 0 AND m.eliminado_para_todos = 0
				   AND NOT EXISTS (SELECT 1 FROM mensaje_oculto mo WHERE mo.id_mensaje = m.id_mensaje AND mo.id_usuario = ?)
				 LIMIT 1'
			);
			$stmt->bind_param('iii', $ind_id, $ind_id, $ind_id);
		} catch (Throwable $e_nuevo) {
			// todavía no se corrió sql/migracion_cambios_21oct.sql
			$stmt = $conexion->prepare(
				'SELECT 1 FROM mensaje m
				 JOIN usuario_conversacion uc ON uc.id_conversacion = m.id_conversacion AND uc.id_usuario = ?
				 WHERE m.id_usuario <> ? AND m.leido = 0
				 LIMIT 1'
			);
			$stmt->bind_param('ii', $ind_id, $ind_id);
		}
		$stmt->execute();
		$hay_mensajes_sin_leer = $stmt->get_result()->num_rows > 0;
		$stmt->close();
	} catch (Throwable $e) {
		// sin puntitos
	}
}
