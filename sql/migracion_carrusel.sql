-- =========================================================
-- migracion_carrusel.sql
-- Permite que una publicación tenga VARIAS fotos/videos (carrusel).
--
-- Cómo correrlo (XAMPP): phpMyAdmin -> base "conectec" -> pestaña SQL
-- -> pegar todo esto -> Continuar. Se puede correr más de una vez sin romper nada.
--
-- Qué hace:
--   1) Crea la tabla trabajo_archivo (una fila por foto/video).
--   2) Pasa las fotos que ya estaban cargadas en trabajo.archivo
--      para que sigan viéndose en el feed.
--   trabajo.archivo se mantiene como "portada" (la 1ra imagen), así
--   el calendario y todo lo anterior sigue funcionando igual.
-- =========================================================

CREATE TABLE IF NOT EXISTS `trabajo_archivo` (
  `id_archivo` int(11) NOT NULL AUTO_INCREMENT,
  `id_trabajo` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_archivo`),
  KEY `idx_trabajo_orden` (`id_trabajo`, `orden`),
  CONSTRAINT `trabajo_archivo_ibfk_1` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajo` (`id_trabajo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pasar las publicaciones existentes (1 foto) a la tabla nueva
INSERT INTO `trabajo_archivo` (`id_trabajo`, `archivo`, `orden`)
SELECT t.`id_trabajo`, t.`archivo`, 0
FROM `trabajo` t
WHERE t.`archivo` IS NOT NULL AND t.`archivo` <> ''
  AND NOT EXISTS (SELECT 1 FROM `trabajo_archivo` ta WHERE ta.`id_trabajo` = t.`id_trabajo`);
