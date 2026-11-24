# Changelog

Todos los cambios importantes de ConecTec se anotan en este archivo.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y las versiones siguen [Versionado Semántico](https://semver.org/lang/es/).

> Cada punto de «Sin publicar» se pasa a una versión nueva (con su fecha) cuando se hace el commit de la reentrega. Los puntos con `[ ]` todavía no están hechos: se tildan `[x]` al terminarlos y, si no se hacen, se borran. No dejar acá nada que no esté en el repositorio.

## [Sin publicar] · Reentrega tras la devolución del 24/11/2026

### Agregado
- [x] `plan-de-pruebas.md`: plan de pruebas con casos por rol, validaciones, reglas de la base y flujos principales (todos los casos figuran como «Pendiente» hasta ejecutarlos).
- [x] `README.md`: descripción, instalación en local, estructura, seguridad, pagos en modo prueba, limitaciones conocidas y tabla de documentación.
- [x] `CHANGELOG.md`: este archivo.
- [x] `docs/hallazgos.md`: dos hallazgos de la prueba de usabilidad, cada uno con su issue.
- [x] `docs/manual-de-usuario.md` (el PDF ya existe) y `docs/documentacion-tecnica.md`.

### Seguridad
- [x] Se retiran del repositorio las credenciales de la base de datos.

### Conocido y pendiente
- Pagos en modo prueba (`PAGOS_MODO_PRUEBA = true`): no se cobra dinero real hasta conectar una pasarela.

## [1.0.0] · 23/10/2026 · Entrega final

### Agregado
- Plataforma completa en PHP y MySQL, sin framework: 30 tablas, scripts de `procesar/`, funciones de `includes/` y 11 hojas de estilo.
- Registro e inicio de sesión para cliente, técnico y empresa; login propio del administrador.
- Publicación de problemas (cliente) y ofertas de empleo (empresa), con fotos adjuntas; edición y eliminación por su dueño.
- Postulaciones de técnicos, aceptación con fecha de trabajo o de reunión y cambio de estado.
- Perfil del técnico con portfolio, CV, certificados técnicos y calificaciones con promedio.
- Mensajería interna (ocultar mensaje, eliminar para todos, eliminar chat) y notificaciones (borrar y marcar como favoritas).
- Calendario según el tipo de cuenta, con eventos personales agregados desde el chat.
- Planes de 30 días (Cliente Plus, Técnico Plus y abono de empresa) con pago en modo prueba e insignia de verificado.
- Panel de administración: verificaciones, certificados (con motivo de rechazo), gestión de usuarios, técnicos y empresas, moderación de trabajos, reportes con exportación a CSV, soporte y configuración.
- Centro de ayuda, preguntas frecuentes, contacto con soporte y reporte de problemas.
- Ajustes de cuenta: idioma (español e inglés), modo oscuro, privacidad del perfil, preferencias de notificaciones, cambio de cliente a técnico y baja de la cuenta.
- Adaptación a celular, con barra de navegación inferior.
- Versión de prueba publicada en InfinityFree; el SQL se exportó desde phpMyAdmin el 27/10/2026.

### Seguridad
- Contraseñas con `password_hash` y `password_verify`.
- Consultas preparadas, salida escapada con `htmlspecialchars` y validación del tipo real de los archivos subidos.
- Token CSRF en el pago y en las acciones del panel de administración.
- Permisos por dueño y estados controlados (el estado de un certificado solo lo cambia el administrador).

## Etapa 1 · 18/05 al 23/06/2026 · Análisis y diseño

### Agregado
- Entrevista al cliente, relevamiento de necesidades y definición del alcance.
- Casos de uso, diagramas UML, diseño de interfaces, Gantt, PERT y diseño del DER (18 tablas).
- Entrega de la primera etapa el 23/06/2026.

[Sin publicar]: https://github.com/USUARIO/REPOSITORIO/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/USUARIO/REPOSITORIO/releases/tag/v1.0.0
