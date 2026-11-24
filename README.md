# ConecTec

Plataforma web que conecta **técnicos**, **clientes** y **empresas**. Los clientes publican problemas técnicos, las empresas publican ofertas laborales, y los técnicos se postulan, muestran sus proyectos, reciben calificaciones y organizan su agenda. Un administrador verifica a los técnicos y modera las publicaciones.

Proyecto de **Prácticas Profesionalizantes** · Informática Personal y Profesional · 7°12 · Ciclo lectivo 2026.

**Integrantes:** Meregaglia Celeste Itati · Ricardo Farias Priscilla B. · Casquero Kamila M.

Versión de prueba en línea: `[COMPLETAR: conectec.great-site.net]` (hosting gratuito, solo para pruebas).

## Qué hace cada tipo de cuenta

| Cuenta | Qué puede hacer |
|---|---|
| Cliente | Publicar problemas, ver y aceptar postulaciones, chatear con técnicos y calificar el trabajo |
| Técnico | Armar su perfil con portfolio, CV y certificados; postularse; usar la agenda; recibir calificaciones |
| Empresa | Publicar ofertas, revisar perfiles, agendar reuniones (requiere abono activo y aprobación del administrador) |
| Administrador | Verificar técnicos y empresas, revisar certificados, moderar trabajos, ver reportes con exportación a CSV |

Un técnico recién registrado usa la plataforma como cliente hasta que el administrador lo verifica.

## Tecnologías

- PHP (sin framework) con `mysqli`
- MySQL / MariaDB (InnoDB, `utf8mb4`), 30 tablas
- HTML, CSS (11 hojas de estilo) y algo de JavaScript

## Requisitos

- PHP 8.0 o superior `[COMPLETAR: versión con la que se probó]`
- MySQL o MariaDB
- XAMPP, o cualquier servidor con Apache, PHP y MySQL
- Permiso de escritura en la carpeta `uploads/` (si no, no se pueden subir fotos ni certificados)

## Instalación en local

1. Copiar la carpeta del proyecto en `htdocs/` (XAMPP), por ejemplo `htdocs/proyecto_conectec/`.
2. Abrir phpMyAdmin y crear una base llamada `conectec` con cotejamiento `utf8mb4_general_ci`.
3. Importar `sql/Schema.sql`. Después, importar los scripts de migración de `sql/` `[COMPLETAR: orden exacto]`.
4. Copiar `conexion.example.php` como `conexion.php` y completar los datos de tu base:

   ```php
   $host  = 'localhost';
   $bd    = 'conectec';
   $user  = 'TU_USUARIO';
   $clave = 'TU_CLAVE';
   ```

   `conexion.php` está en `.gitignore`: **no se sube al repositorio**.
5. Abrir `http://localhost/proyecto_conectec/`.

## Cuentas de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Cliente | `[COMPLETAR]` | `[COMPLETAR]` |
| Técnico | `[COMPLETAR]` | `[COMPLETAR]` |
| Empresa | `[COMPLETAR]` | `[COMPLETAR]` |
| Administrador (`/login_admin.php`) | `[COMPLETAR]` | `[COMPLETAR]` |

Son cuentas de ejemplo para probar en local. Cambiar la clave del administrador antes de publicar.

## Estructura del proyecto

```
proyecto_conectec/
├── *.php            Pantallas (una por archivo): feed, perfil, chat, calendario, pagar…
├── procesar/        Scripts que reciben un formulario, validan, escriben en la base y redirigen
├── admin/           Panel de administración
├── includes/        Funciones compartidas, encabezados y pie de página
├── css/             Hojas de estilo
├── img/             Imágenes del sitio
├── uploads/         Archivos subidos por los usuarios (perfiles, certificados, portfolio, CV, publicaciones)
├── sql/             Schema.sql y migraciones
├── docs/            Documentación (ver abajo)
└── conexion.example.php   Conexión de ejemplo
```

Flujo: navegador → vistas `.php` → `procesar/*.php` → MySQL.

## Seguridad aplicada

- Contraseñas con `password_hash` / `password_verify`
- Consultas preparadas contra inyección SQL
- Salida escapada con `htmlspecialchars` contra XSS
- Validación del tipo real de los archivos subidos y nombres únicos
- Acceso por sesión y por rol
- Token CSRF en el pago y en las acciones del panel de administración
- Permisos por dueño: solo quien publicó puede editar o eliminar, y solo el autor elimina un mensaje para todos

## Pagos en modo prueba

El cobro de los planes funciona en **modo prueba**: `PAGOS_MODO_PRUEBA = true` en `includes/funciones_suscripcion.php`. No se cobra dinero real. Para cobrar hay que pasarlo a `false` y conectar una pasarela (por ejemplo, Mercado Pago); con la pasarela sin conectar, el pago se rechaza.

## Limitaciones conocidas

- **Recuperar contraseña:** la pantalla existe, pero todavía falta la parte real (buscar al usuario, generar el token, enviar el correo con el enlace y actualizar la clave). Los campos `token_recuperacion` y `token_expira` ya están en la tabla `usuario`.
- **Pagos:** solo en modo prueba, como se explica arriba.
- **Datos de prueba:** limpiar los usuarios, las empresas y las notificaciones de ejemplo antes de usar el sistema en serio.

## Documentación

| Documento | Dónde está |
|---|---|
| Manual de usuario | `docs/manual-de-usuario.md` (y PDF) |
| Documentación técnica | `docs/documentacion-tecnica.md` |
| Plan de pruebas | `docs/plan-de-pruebas.md` |
| Prueba de usabilidad | `docs/prueba-usabilidad.md` |
| Hallazgos de la prueba | `docs/hallazgos.md` |
| Informe del proyecto | `docs/informe-del-proyecto.pdf` |
| Historial de cambios | `CHANGELOG.md` |

## Cómo contribuir

- Los problemas se reportan como **issues** del repositorio, con pasos para reproducirlos.
- Cada cambio se anota en `CHANGELOG.md`.
- No subir nunca `conexion.php`, claves ni archivos de `uploads/`.

## Licencia y créditos

Proyecto escolar de la EEST N°3. `[COMPLETAR: licencia, si la hay]`
