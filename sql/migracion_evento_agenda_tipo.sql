-- migracion_evento_agenda_tipo.sql
--
-- Corré esto UNA sola vez sobre tu base "conectec" ya existente, para
-- no tener que borrar y recrear todo desde Schema.sql.
--
-- Qué hace:
--   1) Agrega la columna "tipo" a evento_agenda (trabajo / reunion),
--      todos los eventos existentes (si tuvieras alguno) quedan como
--      'trabajo' por defecto.
--   2) Hace que hora_inicio pueda quedar vacía, porque los trabajos
--      de cliente solo tienen fecha (no una hora puntual), a
--      diferencia de las reuniones de empresa.

USE conectec;

ALTER TABLE evento_agenda
    ADD COLUMN tipo ENUM('trabajo','reunion') NOT NULL DEFAULT 'trabajo' AFTER id_trabajo;

ALTER TABLE evento_agenda
    MODIFY hora_inicio TIME NULL;
