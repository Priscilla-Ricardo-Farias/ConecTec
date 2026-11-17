-- =====================================================================
-- ConecTec - migración de unión
-- Se puede correr en la base de CUALQUIERA de los dos y todas las veces
-- que haga falta: no borra ni pisa datos (solo crea lo que falta).
-- Requiere MariaDB/MySQL de XAMPP (usa IF NOT EXISTS).
-- =====================================================================

-- 1) Carrusel: varias fotos/videos por publicación
CREATE TABLE IF NOT EXISTS `trabajo_archivo` (
  `id_archivo` int(11) NOT NULL AUTO_INCREMENT,
  `id_trabajo` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_archivo`),
  KEY `idx_trabajo_orden` (`id_trabajo`, `orden`),
  CONSTRAINT `trabajo_archivo_ibfk_1` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajo` (`id_trabajo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- pasa a la tabla nueva las publicaciones que tienen 1 sola foto (sin duplicar)
INSERT INTO `trabajo_archivo` (`id_trabajo`, `archivo`, `orden`)
SELECT t.`id_trabajo`, t.`archivo`, 0
FROM `trabajo` t
WHERE t.`archivo` IS NOT NULL AND t.`archivo` <> ''
  AND NOT EXISTS (SELECT 1 FROM `trabajo_archivo` ta WHERE ta.`id_trabajo` = t.`id_trabajo`);

-- 2) Ajustes: columnas de notificaciones
ALTER TABLE `preferencia_usuario`
  ADD COLUMN IF NOT EXISTS `noti_cambios_postulaciones` TINYINT(1) NOT NULL DEFAULT 1 AFTER `noti_postulaciones`,
  ADD COLUMN IF NOT EXISTS `noti_mensajes`              TINYINT(1) NOT NULL DEFAULT 1 AFTER `noti_cambios_postulaciones`,
  ADD COLUMN IF NOT EXISTS `noti_alertas_preferencias`  TINYINT(1) NOT NULL DEFAULT 1 AFTER `noti_mensajes`;

-- 3) Panel de admin: notificaciones borradas
CREATE TABLE IF NOT EXISTS `admin_notif_borrada` (
  `id_admin` int(11) NOT NULL,
  `clave`    varchar(80) NOT NULL,
  `fecha`    datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_admin`, `clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
