<?php
/**
 * db.php — Conexión reutilizable a SQL Server.
 * Expone $conn (PDO) o lanza PDOException si falla.
 */
$serverName = "host.docker.internal";
$database   = "SistemaFacturacion";
$uid        = "usuario_tienda";
$pwd        = "Tienda123*";

$conn = new PDO(
    "sqlsrv:server=$serverName;Database=$database;Encrypt=true;TrustServerCertificate=true",
    $uid,
    $pwd
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>