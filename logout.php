<?php
// logout.php
//
// Destruye la sesión (borra $_SESSION['usuario_nombre'] y todo lo
// demás que se haya guardado ahí) y manda a la persona a la página
// pública de inicio.

session_start();
session_unset();
session_destroy();

header('Location: nosotros.php');
exit;