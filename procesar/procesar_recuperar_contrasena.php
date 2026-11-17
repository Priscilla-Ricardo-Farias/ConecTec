<?php
// procesar_recuperar_contrasena.php
//
// Recibe el correo del formulario de recuperar_contrasena.php.
// Por ahora, si el correo tiene formato válido, redirige de nuevo a
// recuperar_contrasena.php mostrando el aviso de "revisá tu correo".
//
// TODO cuando conectes la base de datos y el envío de mails:
//   1. Buscar si ese correo existe en la tabla de usuarios.
//   2. Generar un token único (bin2hex(random_bytes(32))) y
//      guardarlo en la base junto con una fecha de expiración
//      (por ejemplo, 1 hora).
//   3. Enviar un mail con un link a:
//      restablecer_contrasena.php?token=EL_TOKEN_GENERADO
//   4. Nunca revelar si el correo existe o no en la respuesta (por
//      seguridad), por eso el mensaje de "revisá tu correo" se
//      muestra siempre, exista o no la cuenta.

$correo = trim($_POST['correo'] ?? '');

if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
	header('Location: ../recuperar_contrasena.php?error=correo_invalido');
	exit;
}

/* =========================================================
   ACÁ VA LA LÓGICA REAL: buscar el usuario, generar el token,
   guardarlo y enviar el mail con el link a restablecer_contrasena.php
   ========================================================= */

header('Location: ../recuperar_contrasena.php?enviado=1');
exit;