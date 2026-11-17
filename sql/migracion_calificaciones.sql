-- migracion_calificaciones.sql
--
-- Se corre UNA vez sobre tu base ya creada (elegí la base en phpMyAdmin
-- y pegalo en la pestaña SQL). Se puede volver a correr sin problema.
-- (Usa "IF NOT EXISTS", que entiende MariaDB, el que trae XAMPP; es lo mismo
-- que ya está incluido en la sección 5 de actualizar_bd.sql.)
--
-- Qué hace:
--   Agrega a "calificacion" la columna id_trabajo, para saber qué trabajo
--   se calificó. El índice UNIQUE hace que cada trabajo se pueda calificar
--   una sola vez. Las calificaciones que ya existieran quedan con id_trabajo
--   en NULL (no se pierden y siguen contando en el promedio del perfil).

ALTER TABLE calificacion ADD COLUMN IF NOT EXISTS id_trabajo INT NULL AFTER id_tecnico;
CREATE UNIQUE INDEX IF NOT EXISTS uq_calificacion_trabajo ON calificacion (id_trabajo);
