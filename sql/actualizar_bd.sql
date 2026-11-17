-- =========================================================
-- actualizar_bd.sql
--
-- Para una base de datos que YA TENÉS creada con el Schema.sql viejo.
-- Se corre UNA vez en phpMyAdmin: elegí tu base en la lista de la
-- izquierda, andá a la pestaña "SQL", pegá todo esto y dale
-- "Continuar". Se puede volver a correr sin problema.
-- (Usa "IF NOT EXISTS", que entiende MariaDB — el que trae XAMPP.)
--
-- Qué arregla:
--   1) Suspender cuentas en el panel admin: usuario.estado_cuenta
--      solo admitía 'activa' y 'eliminada'.
--   2) Columnas y tablas que el código ya usa y el Schema viejo no
--      tenía: chat "visto", CV del técnico, notificaciones con
--      demora, soporte, planes de pago y verificación de cuentas.
-- =========================================================

-- 1) Estado "suspendida" ---------------------------------------------
ALTER TABLE usuario
    MODIFY estado_cuenta ENUM('activa','suspendida','eliminada') NOT NULL DEFAULT 'activa';

-- Los "Suspender" que se hicieron antes de este arreglo quedaron
-- guardados como texto vacío: se pasan a 'suspendida'.
UPDATE usuario SET estado_cuenta = 'suspendida' WHERE estado_cuenta = '';

-- 2) Columnas que faltaban -------------------------------------------
ALTER TABLE tecnico       ADD COLUMN IF NOT EXISTS cv_archivo    VARCHAR(255) NULL;
ALTER TABLE mensaje       ADD COLUMN IF NOT EXISTS leido         TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE notificacion  ADD COLUMN IF NOT EXISTS visible_desde DATETIME NULL;

-- 3) Tablas que faltaban ---------------------------------------------
CREATE TABLE IF NOT EXISTS mensaje_soporte (
    id_mensaje_soporte  INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario          INT NOT NULL,
    tipo                ENUM('contacto','reporte') NOT NULL,
    asunto              VARCHAR(255) NOT NULL,
    mensaje             TEXT NOT NULL,
    fecha               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suscripcion (
    id_suscripcion     INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT NOT NULL,
    plan               VARCHAR(50) NOT NULL,
    estado             ENUM('pendiente','activa','vencida','cancelada') NOT NULL DEFAULT 'pendiente',
    monto              DECIMAL(10,2) NOT NULL DEFAULT 0,
    fecha_inicio       DATETIME NULL,
    fecha_vencimiento  DATETIME NULL,
    referencia_pago    VARCHAR(100) NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    INDEX idx_suscripcion_usuario (id_usuario, estado, fecha_vencimiento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS verificacion_cuenta (
    id_usuario        INT PRIMARY KEY,
    estado            ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    fecha_solicitud   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion  DATETIME NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4) Panel admin: configuración y notificaciones ----------------------
-- Momento hasta el cual cada admin ya vio las notificaciones (la
-- campanita cuenta las que son más nuevas que esta fecha).
ALTER TABLE admin ADD COLUMN IF NOT EXISTS notif_visto_hasta DATETIME NULL;

-- Ajustes del panel (clave/valor): qué avisos se muestran, rango por
-- defecto de Reportes, etc.
CREATE TABLE IF NOT EXISTS admin_config (
    clave  VARCHAR(60)  NOT NULL PRIMARY KEY,
    valor  VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- 5) Calificaciones: qué trabajo se calificó --------------------------
-- Después de que pasa el día del trabajo, el cliente/empresa califica al
-- técnico desde el popup del inicio. Cada trabajo se califica una sola vez
-- (el UNIQUE lo asegura). Las calificaciones viejas quedan con id_trabajo NULL.
ALTER TABLE calificacion ADD COLUMN IF NOT EXISTS id_trabajo INT NULL AFTER id_tecnico;
CREATE UNIQUE INDEX IF NOT EXISTS uq_calificacion_trabajo ON calificacion (id_trabajo);

-- 6) Panel admin: notificaciones borradas ------------------------------
-- Los avisos del admin se arman desde las tablas de la plataforma, así que
-- "borrar" uno guarda su clave acá para no volver a mostrarlo. (El panel
-- también crea esta tabla solo la primera vez; correrlo acá es opcional.)
CREATE TABLE IF NOT EXISTS admin_notif_borrada (
    id_admin  INT NOT NULL,
    clave     VARCHAR(80) NOT NULL,
    fecha     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_admin, clave)
) ENGINE=InnoDB;
