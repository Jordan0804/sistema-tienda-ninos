<?php
/** logout.php — Cierra la sesión y redirige al login. */
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: login.php');
exit;
?>
