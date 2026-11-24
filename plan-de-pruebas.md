# Plan de pruebas · ConecTec

- **Fecha del plan:** 24/11/2026
- **Alcance:** accesos y guardias por rol, validación en PHP (campo vacío, formato equivocado, valor fuera de rango), reglas `NOT NULL` / `UNIQUE` / `CHECK` de la base, avisos en castellano, y los flujos principales: registro, publicación, postulación, aceptación, calificación, certificados, mensajes, calendario y planes.
- **Cómo se prueba:** abrir el sistema en XAMPP (`http://localhost/proyecto_conectec/`), importar `sql/Schema.sql` (más las migraciones de `sql/`) y entrar con las cuentas de prueba:

| rol | email | contraseña |
|---|---|---|
| cliente | `[COMPLETAR]` | `[COMPLETAR]` |
| técnico verificado | `[COMPLETAR]` | `[COMPLETAR]` |
| técnico pendiente | `[COMPLETAR]` | `[COMPLETAR]` |
| empresa con abono y aprobada | `[COMPLETAR]` | `[COMPLETAR]` |
| empresa sin abono | `[COMPLETAR]` | `[COMPLETAR]` |
| administrador (`/login_admin.php`) | `[COMPLETAR]` | `[COMPLETAR]` |

> Las cuentas y claves reales **no se escriben acá si el repositorio es público**: se dejan en el archivo de datos de prueba del equipo.

- **Capa:** **NAV** = lo detecta el navegador antes de enviar (atributos `required`, `type="email"`, `minlength`) · **PHP** = lo rechaza el servidor (se prueba mandando el formulario a mano o quitando el atributo desde el inspector) · **BD** = lo rechaza MySQL (se prueba en phpMyAdmin, pestaña SQL) · **GUARDIA** = lo frena el control de sesión/rol al principio del archivo.
- **Resultado:** `Pasó` / `Falló` + fecha de ejecución + quién probó. Todo figura **Pendiente** hasta ejecutarlo; no se completa nada que no se haya corrido. Si falla, se anota el número del issue.
- **Regla de aviso:** todo rechazo muestra un aviso en castellano. Donde el texto está entre «», es el que muestra el sistema hoy; donde dice *(texto a confirmar)* hay que copiar el literal al ejecutar el caso.

## 1. Guardias (casos negativos)

| N° | Qué se hace | Esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| G1 | Sin iniciar sesión, abrir `feed.php` y `perfil.php` | Redirige al login | GUARDIA | Pendiente |
| G2 | Sin iniciar sesión, abrir cada `procesar/*.php` por la barra de direcciones (GET) | Redirige al login o al inicio; no cambia nada en la base | GUARDIA | Pendiente |
| G3 | Como cliente, abrir `admin/panel_admin.php` | Bloqueado; pide login de administrador | GUARDIA | Pendiente |
| G4 | Como técnico, abrir `publicar.php` e intentar publicar un problema | Bloqueado: el técnico no publica trabajos de cliente | GUARDIA | Pendiente |
| G5 | Como empresa sin abono, abrir `calendario.php` y `publicar.php` | Bloqueada hasta pagar el plan y que el admin la apruebe | GUARDIA | Pendiente |
| G6 | Como técnico pendiente de verificación, entrar a la agenda y postularse | Se comporta como cliente hasta que el admin lo verifica | GUARDIA | Pendiente |
| G7 | Como cliente A, abrir `editar_publicacion.php?id=` de un trabajo del cliente B | «Esa publicación no se puede editar ni eliminar: no es tuya o ya se aceptó como trabajo.» | GUARDIA | Pendiente |
| G8 | Enviar a mano `procesar_eliminar_publicacion.php` de un trabajo ajeno | No se elimina | GUARDIA | Pendiente |
| G9 | Abrir `diagnostico_calificaciones.php` | **Debe no existir** (se retira antes de publicar) | GUARDIA | Pendiente |
| G10 | Abrir `procesar/procesar_pago.php` sin token CSRF | «La sesión del pago venció. Volvé a intentarlo.» | GUARDIA | Pendiente |

## 2. Login y registro

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| L1 | Login con correo y contraseña vacíos | El navegador marca los campos obligatorios; por PHP vuelve a `login.php` con aviso | NAV / PHP | Pendiente |
| L2 | Login con correo correcto y clave equivocada | «Nombre, correo o contraseña incorrectos.» | PHP | Pendiente |
| L3 | Login con una cuenta suspendida | «Tu cuenta está suspendida. Contactá a soporte si creés que es un error.» | PHP | Pendiente |
| L4 | Login con una cuenta eliminada | «Esta cuenta fue eliminada.» | PHP | Pendiente |
| L5 | Login correcto | Entra a `feed.php` | — (positivo) | Pendiente |
| R1 | Registro de cliente con todo vacío | El navegador exige los campos; por PHP, aviso de campos incompletos *(texto a confirmar)* | NAV / PHP | Pendiente |
| R2 | Registro con correo `juan.com` | Aviso de correo inválido *(texto a confirmar)* | NAV / PHP | Pendiente |
| R3 | Registro con un correo que ya existe | Aviso de correo ya registrado *(texto a confirmar)* | PHP + BD (`UNIQUE email`) | Pendiente |
| R4 | Registro de empresa con CUIT `123` | «El CUIT tiene que tener el formato 20-12345678-9.» | PHP | Pendiente |
| R5 | Registro de empresa con un CUIT ya registrado | «Ya existe una empresa registrada con ese CUIT.» | PHP | Pendiente |
| R6 | Registro de empresa con un correo ya registrado | «Ya existe una cuenta con ese correo.» | PHP | Pendiente |
| R7 | Registro de técnico con certificado de tipo no permitido (por ejemplo `.exe`) | Rechaza el archivo | PHP | Pendiente |
| R8 | Registro de técnico correcto con certificado | Cuenta creada en estado «pendiente» | — (positivo) | Pendiente |
| R9 | Registro de cliente correcto | Entra logueado a `feed.php` | — (positivo) | Pendiente |

## 3. Contraseña

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| C1 | Cambiar contraseña con la nueva de 5 caracteres | El navegador la frena (`minlength="6"`); por PHP vuelve con error | NAV / PHP | Pendiente |
| C2 | Cambiar contraseña con la confirmación distinta | Vuelve a `cambiar_contrasena.php` con error | PHP | Pendiente |
| C3 | Cambiar contraseña con la clave actual equivocada | Error de clave incorrecta | PHP | Pendiente |
| C4 | Cambio correcto | «Tu contraseña se actualizó. Iniciá sesión con la nueva.» y la clave nueva funciona | — (positivo) | Pendiente |
| C5 | Recuperar contraseña con un correo existente | **Conocido: la recuperación real todavía no está implementada** (ver `README.md`). Anotar qué pasa hoy | PHP | Pendiente |

## 4. Publicaciones (cliente y empresa)

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| P1 | Publicar con el título vacío | Vuelve a `publicar.php` con aviso de campos incompletos | NAV / PHP | Pendiente |
| P2 | Publicar con la categoría manipulada (valor que no existe) | Rechaza | PHP | Pendiente |
| P3 | Publicar con una foto de tipo no permitido | Aviso de archivo inválido | PHP | Pendiente |
| P4 | Publicar con una foto muy pesada | Rechaza o recorta según el límite del servidor; anotar el resultado | PHP | Pendiente |
| P5 | Publicar un problema como cliente con foto | Aparece en el inicio de los técnicos | — (positivo) | Pendiente |
| P6 | Publicar una oferta como empresa habilitada | Aparece en el inicio | — (positivo) | Pendiente |
| P7 | Editar una publicación propia sin técnico aceptado | Se guarda y se ve el cambio | — (positivo) | Pendiente |
| P8 | Editar o eliminar una publicación que ya tiene técnico aceptado | «Esa publicación no se puede editar ni eliminar: no es tuya o ya se aceptó como trabajo.» | PHP | Pendiente |

## 5. Postulaciones y aceptación

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| A1 | Técnico se postula a un trabajo de su especialidad | Se registra; aviso de éxito; el cliente recibe notificación | — (positivo) | Pendiente |
| A2 | Técnico se postula a un trabajo de otra especialidad | «Solo podés postularte a trabajos de tus especialidades.» | PHP | Pendiente |
| A3 | Técnico se postula dos veces al mismo trabajo | No se duplica la postulación | PHP + BD (`UNIQUE id_tecnico, id_trabajo`) | Pendiente |
| A4 | Postularse a un trabajo que ya se cerró o tiene técnico | «Ese trabajo ya no está disponible.» | PHP | Pendiente |
| A5 | Cliente acepta una postulación sin elegir fecha | «Para aceptar al técnico, elegí la fecha acordada para el trabajo.» | PHP | Pendiente |
| A6 | Empresa acepta una postulación sin elegir fecha de reunión | Aviso de falta de fecha de reunión *(texto a confirmar)* | PHP | Pendiente |
| A7 | Cliente acepta una postulación con fecha | Cambia el estado, el técnico recibe aviso y el evento aparece en su agenda | — (positivo) | Pendiente |
| A8 | Un cliente intenta aceptar una postulación de un trabajo ajeno (enviado a mano) | Rechazado; no cambia el estado | PHP | Pendiente |

## 6. Calificaciones

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| K1 | Calificar con puntaje `0` | Rechaza: el puntaje va de 1 a 5 | PHP + BD (`CHECK puntaje BETWEEN 1 AND 5`) | Pendiente |
| K2 | Calificar con puntaje `6` (enviado a mano) | Rechaza | PHP + BD | Pendiente |
| K3 | Calificar dos veces el mismo trabajo | No se duplica | PHP + BD (`UNIQUE id_trabajo`) | Pendiente |
| K4 | Calificar un trabajo que no es del usuario | Rechaza; no se crea la calificación | PHP | Pendiente |
| K5 | Calificar al finalizar el trabajo | Se actualiza el promedio del técnico | — (positivo) | Pendiente |

## 7. Certificados y verificación (técnico y administrador)

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| V1 | Técnico sube un certificado con formato no permitido | Rechaza el archivo | PHP | Pendiente |
| V2 | Técnico sube un certificado válido | Queda «pendiente» de revisión | — (positivo) | Pendiente |
| V3 | Admin aprueba el certificado | Queda «aprobado» y se muestra como verificado | — (positivo) | Pendiente |
| V4 | Admin rechaza un certificado **sin** escribir el motivo | No se rechaza; vuelve a la misma pestaña pidiendo el motivo | PHP | Pendiente |
| V5 | Admin rechaza un certificado con motivo | Llega la notificación con el motivo al técnico | — (positivo) | Pendiente |
| V6 | Técnico intenta cambiar el estado de su certificado enviando `estado=aprobado` a mano | Se ignora: solo el administrador cambia el estado | PHP | Pendiente |
| V7 | Admin aprueba la verificación de un técnico | El perfil queda habilitado como técnico | — (positivo) | Pendiente |
| V8 | Admin rechaza la verificación de una empresa | La empresa sigue bloqueada | — (positivo) | Pendiente |
| V9 | Acción del panel (aprobar, rechazar, borrar notificación) enviada sin token CSRF | «Rechazada»: no ejecuta la acción | GUARDIA | Pendiente |

## 8. Mensajes y notificaciones

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| M1 | Enviar un mensaje vacío | No se envía | NAV / PHP | Pendiente |
| M2 | Escribir a alguien con quien la regla no permite chatear (por ejemplo, cliente a cliente) | Bloqueado | PHP | Pendiente |
| M3 | Eliminar un mensaje «para mí» | Deja de verse solo para quien lo borra | — (positivo) | Pendiente |
| M4 | Eliminar un mensaje «para todos» siendo el autor | Queda «Se eliminó este mensaje» | — (positivo) | Pendiente |
| M5 | Eliminar «para todos» un mensaje ajeno (enviado a mano) | Rechazado: solo el autor puede | PHP | Pendiente |
| M6 | Marcar una notificación como favorita y borrarla | Se refleja en la lista | — (positivo) | Pendiente |
| M7 | Cuenta sin plan: llega un trabajo nuevo | El aviso llega con 30 minutos de demora | — (positivo) | Pendiente |
| M8 | Cuenta con plan activo: llega un trabajo nuevo | El aviso llega al instante | — (positivo) | Pendiente |

## 9. Calendario por tipo de cuenta

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| D1 | Aceptar un técnico en un trabajo de cliente y entrar al calendario como técnico | El técnico ve el trabajo en la fecha acordada | — (positivo) | Pendiente |
| D2 | Mismo caso, entrar como cliente | El cliente ve solo el día y el nombre del técnico; no ve reuniones ni eventos personales | — (positivo) | Pendiente |
| D3 | Aceptar un técnico en una oferta de empresa y entrar como empresa habilitada | La empresa ve la reunión | — (positivo) | Pendiente |
| D4 | Agregar un mensaje del chat al calendario («Agregar al calendario») | Aparece como evento personal de quien lo agregó | — (positivo) | Pendiente |
| D5 | Navegar al mes anterior y siguiente | El mes se arma bien, incluso al cambiar de año | — (positivo) | Pendiente |

## 10. Planes y pagos (modo prueba)

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| Pg1 | Pagar un plan con el pago de prueba activo (`PAGOS_MODO_PRUEBA = true`) | La suscripción queda activa 30 días | — (positivo) | Pendiente |
| Pg2 | Ver la insignia de verificado con plan activo **y** cuenta aprobada | La insignia aparece | — (positivo) | Pendiente |
| Pg3 | Ver la insignia con plan activo pero cuenta sin aprobar | No aparece | — (positivo) | Pendiente |
| Pg4 | Vencer el plan (cambiar la fecha de vencimiento en la base) | La insignia desaparece y vuelven las demoras | — (positivo) | Pendiente |
| Pg5 | Pagar con `PAGOS_MODO_PRUEBA = false` y sin pasarela conectada | «El cobro online todavía no está habilitado. Probá de nuevo más tarde.» | PHP | Pendiente |
| Pg6 | Pagar con un plan inventado (`plan=oro`) | Rechaza | PHP | Pendiente |

## 11. Panel de administración

| N° | Entrada | Aviso esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| Ad1 | Login de administrador con clave equivocada | Aviso de credenciales incorrectas | PHP | Pendiente |
| Ad2 | Suspender una cuenta de usuario | Esa persona ya no puede iniciar sesión (ver L3) | — (positivo) | Pendiente |
| Ad3 | Eliminar un trabajo inadecuado desde moderación | Desaparece del inicio | — (positivo) | Pendiente |
| Ad4 | Exportar reportes a CSV | Se descarga un archivo con datos y tildes bien | — (positivo) | Pendiente |
| Ad5 | Cerrar la sesión de administrador y volver atrás con el navegador | No se puede ver el panel | GUARDIA | Pendiente |

## 12. Reglas de la base (probar en phpMyAdmin, pestaña SQL)

| N° | Sentencia | Error esperado de MySQL | Regla | Resultado / fecha |
|---|---|---|---|---|
| B1 | `INSERT` en `usuario` con un `email` que ya existe | 1062 Duplicate entry | `UNIQUE email` | Pendiente |
| B2 | `INSERT` en `usuario` con `nombre` en `NULL` | 1048 Column cannot be null | `NOT NULL nombre` | Pendiente |
| B3 | `INSERT` en `usuario` con `estado_cuenta='suspendida'` | **Verificar**: el `Schema.sql` del repo solo define `activa` y `eliminada`, y el informe dice que también existe `suspendida` | `ENUM estado_cuenta` | Pendiente |
| B4 | Dos `INSERT` en `postulacion` con el mismo técnico y trabajo | 1062 Duplicate entry en el segundo | `UNIQUE uq_postulacion` | Pendiente |
| B5 | `INSERT` en `calificacion` con `puntaje = 9` | Violación de `CHECK` (según versión de MySQL/MariaDB) | `CHECK puntaje BETWEEN 1 AND 5` | Pendiente |
| B6 | Dos `INSERT` en `calificacion` con el mismo `id_trabajo` | 1062 Duplicate entry en el segundo | `UNIQUE id_trabajo` | Pendiente |
| B7 | `INSERT` en `empleador` sin `cuit` | 1364 / 1048 según el modo SQL | `NOT NULL cuit` | Pendiente |
| B8 | `INSERT` en `admin` con un `usuario` repetido | 1062 Duplicate entry | `UNIQUE usuario` | Pendiente |
| B9 | `INSERT` en `notificacion` con un `tipo` de los que usa el informe (`certificado`, `alerta`…) | **Verificar**: el `Schema.sql` solo define `trabajo` y `mensaje`; confirmar que la base final está al día con las migraciones | `ENUM tipo` | Pendiente |
| B10 | Borrar un usuario con trabajos, postulaciones y mensajes | Se borran en cascada los datos que cuelgan de él | `FOREIGN KEY ... ON DELETE CASCADE` | Pendiente |

## 13. Vista en celular

| N° | Qué se hace | Esperado | Capa | Resultado / fecha |
|---|---|---|---|---|
| X1 | Abrir inicio, feed y perfil con el ancho de un celular (herramientas del navegador, 375 px) | Sin scroll horizontal; el menú pasa a la barra inferior | NAV | Pendiente |
| X2 | Completar el registro desde el celular | Los campos y botones se pueden usar sin agrandar | NAV | Pendiente |

## 14. Cómo registrar los resultados

1. Ejecutar el caso y completar la última columna con `Pasó` o `Falló`, la fecha y las iniciales de quien probó.
2. Si falla: abrir un issue en el repositorio con título `[Falla] N° del caso – resumen`, pasos, resultado esperado y obtenido, y captura. Anotar el número del issue en la misma fila.
3. Cuando se corrige, volver a correr el caso y actualizar la fila. Anotar el cambio en `CHANGELOG.md`.
4. Antes de entregar: no deben quedar casos «Pendiente» sin una razón escrita.
