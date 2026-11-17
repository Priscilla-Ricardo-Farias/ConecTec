<?php
// procesar_restablecer_contrasena.php
//
// Recibe el token (oculto en el formulario) y la contraseña nueva
// escrita dos veces desde restablecer_contrasena.php. Si coinciden,
// actualiza la contraseña y manda a la persona a loguearse de nuevo.
//
// TODO cuando conectes la base de datos:
//   1. Buscar el token, comprobar que no haya expirado.
//   2. Actualizar la clave del usuario correspondiente con
//      password_hash($clave_nueva, PASSWORD_DEFAULT).
//   3. Borrar el token para que no se pueda volver a usar.

$token                 = $_POST['token'] ?? '';
$clave_nueva           = $_POST['clave_nueva'] ?? '';
$clave_nueva_confirmar = $_POST['clave_nueva_confirmar'] ?? '';

if ($token === '') {
	header('Location: ../recuperar_contrasena.php?error=token_invalido');
	exit;
}

if ($clave_nueva === '' || strlen($clave_nueva) < 6) {
	header('Location: ../restablecer_contrasena.php?token=' . urlencode($token) . '&error=1');
	exit;
}

if ($clave_nueva !== $clave_nueva_confirmar) {
	header('Location: ../restablecer_contrasena.php?token=' . urlencode($token) . '&error=1');
	exit;
}

/* =========================================================
   ACÁ VA LA ACTUALIZACIÓN REAL, por ejemplo:

   $clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);
   $stmt = $conexion->prepare('UPDATE usuarios SET clave_hash = ? WHERE token_recuperacion = ? AND token_expira > NOW()');
   $stmt->execute([$clave_hash, $token]);
   ========================================================= */

header('Location: ../login.php?clave_actualizada=1');
exit;