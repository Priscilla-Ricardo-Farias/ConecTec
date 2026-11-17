-- =========================================================
-- ConecTec - Esquema de base de datos
-- Basado en el diagrama UML provisto + ajustes detectados
-- al revisar los .php (ver notas -- AJUSTE en cada tabla).
-- Motor: MySQL / MariaDB (InnoDB, utf8mb4)
-- =========================================================

CREATE DATABASE IF NOT EXISTS conectec CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE conectec;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- ADMIN
-- =========================================================
CREATE TABLE admin (
    id_admin    INT AUTO_INCREMENT PRIMARY KEY,
    usuario     VARCHAR(100)  NOT NULL UNIQUE,
    contrasena  VARCHAR(255)  NOT NULL
) ENGINE=InnoDB;

-- =========================================================
-- USUARIO (tabla base: cliente, tecnico y empleador cuelgan de acá)
-- =========================================================
CREATE TABLE usuario (
    id_usuario          INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(100)  NOT NULL,
    apellido            VARCHAR(100)  NULL,               -- AJUSTE: ningún form lo pide todavía, se deja nullable
    email               VARCHAR(150)  NOT NULL UNIQUE,
    contrasena          VARCHAR(255)  NOT NULL,            -- guardar siempre con password_hash()
    fecha_nacimiento    DATE          NOT NULL,
    foto_perfil         VARCHAR(255)  NULL,
    fecha_registro      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado_cuenta       ENUM('activa','eliminada') NOT NULL DEFAULT 'activa',

    -- AJUSTE: necesarios para recuperar_contrasena.php / restablecer_contrasena.php
    token_recuperacion  VARCHAR(64)   NULL,
    token_expira        DATETIME      NULL,

    INDEX idx_usuario_email (email),
    INDEX idx_usuario_token (token_recuperacion)
) ENGINE=InnoDB;

-- =========================================================
-- CLIENTE (usuario común que busca técnicos)
-- =========================================================
CREATE TABLE cliente (
    id_cliente  INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT NOT NULL UNIQUE,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TECNICO
-- =========================================================
CREATE TABLE tecnico (
    id_tecnico            INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario            INT NOT NULL UNIQUE,
    especialidad          VARCHAR(100)  NOT NULL,   -- tipo_tecnico (o tipo_tecnico_otro)
    descripcion           TEXT          NULL,        -- bio del perfil
    certificado_archivo   VARCHAR(255)  NULL,        -- AJUSTE: antes "foto_titulo"; es el archivo subido en certificado_archivo
    fecha_graduacion      SMALLINT      NULL,        -- AJUSTE: faltaba por completo en el UML
    estado_verificacion   ENUM('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
    promedio_calificacion FLOAT         NOT NULL DEFAULT 0,

    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- EMPLEADOR (empresa)
-- =========================================================
CREATE TABLE empleador (
    id_empleador        INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario           INT NOT NULL UNIQUE,
    cuit                 VARCHAR(13) NOT NULL,    -- formato XX-XXXXXXXX-X, lo carga la empresa al registrarse y lo valida el admin antes de aprobar
    empresa              VARCHAR(150) NOT NULL,   -- nombre_empresa
    descripcion_empresa  TEXT NULL,
    verificada            TINYINT(1) NOT NULL DEFAULT 0,  -- AJUSTE: mencionada en procesar_registro_empresa.php, faltaba en UML

    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TRABAJO (publicación: la sube un cliente o un empleador)
-- =========================================================
CREATE TABLE trabajo (
    id_trabajo         INT AUTO_INCREMENT PRIMARY KEY,
    titulo             VARCHAR(150) NOT NULL,
    descripcion        TEXT NULL,                 -- solo cliente
    caracteristicas    TEXT NULL,                 -- AJUSTE: solo empleador, faltaba en UML
    categoria          VARCHAR(100) NOT NULL,     -- tipo_tecnico (o el "otro" especificado)
    ubicacion          VARCHAR(255) NOT NULL,     -- direccion
    archivo            VARCHAR(255) NULL,         -- AJUSTE: foto/video que sube el cliente, faltaba en UML
    fecha_trabajo       DATE NULL,                 -- fecha elegida en el form de cliente
    fecha_publicacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado             ENUM('abierto','en_progreso','cerrado') NOT NULL DEFAULT 'abierto'
) ENGINE=InnoDB;

-- Relación trabajo <-> cliente (1 a 1: cada trabajo lo publica un solo cliente)
CREATE TABLE trabajo_cliente (
    id_trabajo_cliente  INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente          INT NOT NULL,
    id_trabajo          INT NOT NULL UNIQUE,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente) ON DELETE CASCADE,
    FOREIGN KEY (id_trabajo) REFERENCES trabajo(id_trabajo) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Relación trabajo <-> empleador (1 a 1: cada trabajo lo publica un solo empleador)
CREATE TABLE trabajo_empleador (
    id_trabajo_empleador  INT AUTO_INCREMENT PRIMARY KEY,
    id_empleador          INT NOT NULL,
    id_trabajo            INT NOT NULL UNIQUE,
    FOREIGN KEY (id_empleador) REFERENCES empleador(id_empleador) ON DELETE CASCADE,
    FOREIGN KEY (id_trabajo) REFERENCES trabajo(id_trabajo) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- POSTULACION (un técnico se postula a un trabajo)
-- =========================================================
CREATE TABLE postulacion (
    id_postulacion     INT AUTO_INCREMENT PRIMARY KEY,
    id_tecnico         INT NOT NULL,
    id_trabajo         INT NOT NULL,
    fecha_postulacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado             ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',

    UNIQUE KEY uq_postulacion (id_tecnico, id_trabajo),  -- un técnico no se postula 2 veces al mismo trabajo
    FOREIGN KEY (id_tecnico) REFERENCES tecnico(id_tecnico) ON DELETE CASCADE,
    FOREIGN KEY (id_trabajo) REFERENCES trabajo(id_trabajo) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- PROYECTO (item del portfolio del técnico en perfil.php)
-- =========================================================
CREATE TABLE proyecto (
    id_proyecto        INT AUTO_INCREMENT PRIMARY KEY,
    id_tecnico         INT NOT NULL,
    titulo             VARCHAR(150) NULL,
    descripcion        TEXT NULL,
    fecha_realizacion  DATE NULL,
    FOREIGN KEY (id_tecnico) REFERENCES tecnico(id_tecnico) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fotos del proyecto (perfil.php puede mostrar varias por trabajo)
CREATE TABLE foto_proyecto (
    id_foto      INT AUTO_INCREMENT PRIMARY KEY,
    id_proyecto  INT NOT NULL,
    imagen       VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_proyecto) REFERENCES proyecto(id_proyecto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- CALIFICACION (estrellas del perfil de un técnico)
-- =========================================================
CREATE TABLE calificacion (
    id_calificacion  INT AUTO_INCREMENT PRIMARY KEY,
    id_tecnico       INT NOT NULL,
    id_trabajo       INT NULL UNIQUE,   -- AJUSTE: trabajo que se está calificando; el UNIQUE evita calificar 2 veces el mismo trabajo
    puntaje          TINYINT NOT NULL CHECK (puntaje BETWEEN 1 AND 5),
    comentario       TEXT NULL,
    fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tecnico) REFERENCES tecnico(id_tecnico) ON DELETE CASCADE,
    FOREIGN KEY (id_trabajo) REFERENCES trabajo(id_trabajo) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Quién calificó: un cliente...
CREATE TABLE calificacion_cliente (
    id_relacion      INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente       INT NOT NULL,
    id_calificacion  INT NOT NULL UNIQUE,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente) ON DELETE CASCADE,
    FOREIGN KEY (id_calificacion) REFERENCES calificacion(id_calificacion) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ...o un empleador
CREATE TABLE calificacion_empleador (
    id_relacion      INT AUTO_INCREMENT PRIMARY KEY,
    id_empleador     INT NOT NULL,
    id_calificacion  INT NOT NULL UNIQUE,
    FOREIGN KEY (id_empleador) REFERENCES empleador(id_empleador) ON DELETE CASCADE,
    FOREIGN KEY (id_calificacion) REFERENCES calificacion(id_calificacion) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- MENSAJERIA (mensajes.php)
-- =========================================================
CREATE TABLE conversacion (
    id_conversacion  INT AUTO_INCREMENT PRIMARY KEY,
    fecha_creacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuario_conversacion (
    id_relacion      INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT NOT NULL,
    id_conversacion  INT NOT NULL,
    UNIQUE KEY uq_usuario_conversacion (id_usuario, id_conversacion),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_conversacion) REFERENCES conversacion(id_conversacion) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE mensaje (
    id_mensaje       INT AUTO_INCREMENT PRIMARY KEY,
    id_conversacion  INT NOT NULL,
    id_usuario       INT NOT NULL,   -- quién lo envió
    contenido        TEXT NOT NULL,
    fecha_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_conversacion) REFERENCES conversacion(id_conversacion) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- AGENDA (calendario.php)
-- =========================================================
CREATE TABLE evento_agenda (
    id_evento    INT AUTO_INCREMENT PRIMARY KEY,
    id_tecnico   INT NOT NULL,
    id_trabajo   INT NULL,
    tipo         ENUM('trabajo','reunion') NOT NULL DEFAULT 'trabajo',  -- AJUSTE: distingue changuita (cliente) de reunión (empresa)
    fecha        DATE NOT NULL,
    hora_inicio  TIME NULL,   -- AJUSTE: los trabajos de cliente solo tienen fecha; las reuniones de empresa sí llevan hora
    hora_fin     TIME NULL,
    estado       VARCHAR(50) NOT NULL DEFAULT 'pendiente',
    FOREIGN KEY (id_tecnico) REFERENCES tecnico(id_tecnico) ON DELETE CASCADE,
    FOREIGN KEY (id_trabajo) REFERENCES trabajo(id_trabajo) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- AJUSTE: NOTIFICACION -- no estaba en el UML.
-- Cubre notificaciones.php (tipo trabajo/mensaje, leída, favorito)
-- =========================================================
CREATE TABLE notificacion (
    id_notificacion   INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario        INT NOT NULL,           -- destinatario
    id_usuario_origen INT NULL,               -- quién generó la notificación (puede ser NULL si es del sistema)
    tipo              ENUM('trabajo','mensaje') NOT NULL,
    texto             VARCHAR(255) NOT NULL,
    leida             TINYINT(1) NOT NULL DEFAULT 0,
    favorito          TINYINT(1) NOT NULL DEFAULT 0,
    fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_origen) REFERENCES usuario(id_usuario) ON DELETE SET NULL,
    INDEX idx_notificacion_usuario (id_usuario, leida)
) ENGINE=InnoDB;

-- =========================================================
-- AJUSTE: PREFERENCIA_USUARIO -- no estaba en el UML.
-- Cubre procesar_ajustes.php (notificaciones, búsqueda,
-- contratación, privacidad, apariencia). Una fila por usuario.
-- =========================================================
CREATE TABLE preferencia_usuario (
    id_usuario                INT PRIMARY KEY,

    -- sección "notificaciones"
    noti_ofertas               TINYINT(1) NOT NULL DEFAULT 1,
    noti_postulaciones         TINYINT(1) NOT NULL DEFAULT 1,

    -- sección "preferencias de búsqueda" (solo aplica a cliente/técnico buscando trabajo)
    pref_puesto                VARCHAR(150) NULL,
    pref_ubicacion             VARCHAR(150) NULL,
    pref_modalidad             VARCHAR(255) NULL,   -- CSV o JSON con las opciones tildadas
    pref_tipo_contrato         VARCHAR(100) NULL,
    pref_salario_min           DECIMAL(10,2) NULL,
    pref_salario_max           DECIMAL(10,2) NULL,

    -- sección "preferencias de contratación" (empleador)
    pref_categorias            VARCHAR(255) NULL,   -- CSV o JSON
    pref_tipo_contratacion     VARCHAR(100) NULL,

    -- sección "privacidad"
    privacidad_perfil          ENUM('publico','privado') NOT NULL DEFAULT 'publico',
    privacidad_cv_visible      TINYINT(1) NOT NULL DEFAULT 1,

    -- sección "apariencia"
    modo_oscuro                TINYINT(1) NOT NULL DEFAULT 0,
    idioma                     VARCHAR(10) NOT NULL DEFAULT 'es',

    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;