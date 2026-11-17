<?php
// logout_admin.php
session_start();
unset($_SESSION['admin_id'], $_SESSION['admin_usuario']);
session_write_close();

header('Location: login_admin.php');
exit;
