<?php
// includes/funciones_publicaciones.php
//
// Reglas para que alguien edite o elimine SUS publicaciones de trabajo:
//
//   - tiene que ser el dueño (el cliente o la empresa que la publicó), y
//   - la publicación todavía no se aceptó como trabajo: sigue "abierta" y
//     ningún postulante fue aceptado. Cuando se acepta a un técnico pasa a
//     ser un trabajo en curso y ya no se puede tocar.

/**
 * Devuelve la publicación (con sus datos) si $id_usuario es el dueño y se puede
 * editar/eliminar; si no, null. Una sola función decide esto para el feed, la
 * pantalla de edición y los dos procesadores, así no se pueden desfasar.
 *
 * Campos: id_trabajo, titulo, descripcion, caracteristicas, categoria, ubicacion,
 *         fecha_trabajo, archivo, es_empresa (bool).
 */
function publicacion_propia_editable($conexion, $id_trabajo, $id_usuario) {
	$id_trabajo = (int) $id_trabajo;
	$id_usuario = (int) $id_usuario;

	if ($id_trabajo <= 0 || $id_usuario <= 0) {
		return null;
	}

	$stmt = $conexion->prepare(
		"SELECT t.id_trabajo, t.titulo, t.descripcion, t.caracteristicas, t.categoria, t.ubicacion,
		        t.fecha_trabajo, t.archivo, (tem.id_empleador IS NOT NULL) AS es_empresa
		 FROM trabajo t
		 LEFT JOIN trabajo_cliente tc    ON tc.id_trabajo = t.id_trabajo
		 LEFT JOIN cliente c             ON c.id_cliente = tc.id_cliente
		 LEFT JOIN trabajo_empleador tem ON tem.id_trabajo = t.id_trabajo
		 LEFT JOIN empleador e           ON e.id_empleador = tem.id_empleador
		 WHERE t.id_trabajo = ?
		   AND (c.id_usuario = ? OR e.id_usuario = ?)
		   AND t.estado = 'abierto'
		   AND NOT EXISTS (SELECT 1 FROM postulacion p WHERE p.id_trabajo = t.id_trabajo AND p.estado = 'aceptada')"
	);
	$stmt->bind_param('iii', $id_trabajo, $id_usuario, $id_usuario);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$fila) {
		return null;
	}

	$fila['es_empresa'] = (bool) $fila['es_empresa'];
	return $fila;
}
