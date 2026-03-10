<?php
$serverName = "host.docker.internal"; 
$database = "SistemaFacturacion";
$uid = "usuario_tienda"; 
$pwd = "Tienda123*"; 

try {
    // Agregamos TrustServerCertificate=1 y Encrypt=no para evitar el error de SSL
    $conn = new PDO("sqlsrv:server=$serverName;Database=$database;Encrypt=true;TrustServerCertificate=true", $uid, $pwd);
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>¡ÉXITO TOTAL! el sistema ya está conectado a la base de datos.</h1>";
} catch (PDOException $e) {
    echo "<h1>Error de conexión:</h1> " . $e->getMessage();
}
?>