<?php
// procesar_eliminar_cuenta.php
//
// Recibe la confirmación de contraseña de eliminar_cuenta.php.
//
// TODO cuando conectes la base de datos: verificar la contraseña con
// password_verify() contra la del usuario logueado, y si es correcta,
// borrar (o marcar como inactivo) su fila y sus publicaciones antes
// de cerrar la sesión.

session_start();

if (!isset($_SESSION['usuario_nombre'])) {
	header('Location: ../login.php');
	exit;
}

// Una empresa no entra acá hasta pagar el abono y que el admin la apruebe.
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones_suscripcion.php';
exigir_empresa_habilitada($conexion, '../');

$clave_confirmar = $_POST['clave_confirmar'] ?? '';

if ($clave_confirmar === '') {
	header('Location: ../eliminar_cuenta.php?error=1');
	exit;
}

/* =========================================================
   ACÁ VA LA VALIDACIÓN Y EL BORRADO REAL, por ejemplo:

   if (!password_verify($clave_confirmar, $usuario['clave_hash'])) {
       header('Location: ../eliminar_cuenta.php?error=clave_incorrecta');
       exit;
   }
   $conexion->prepare('DELETE FROM usuarios WHERE correo = ?')->execute([$_SESSION['usuario_correo']]);
   ========================================================= */

session_unset();
session_destroy();

header('Location: ../nosotros.php?cuenta_eliminada=1');
exit;