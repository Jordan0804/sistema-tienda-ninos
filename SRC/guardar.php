<?php
// Incluimos la conexión que ya probamos
$serverName = "host.docker.internal"; 
$database = "SistemaFacturacion";
$uid = "usuario_tienda"; 
$pwd = "Tienda123*"; 

try {
    $conn = new PDO("sqlsrv:server=$serverName;Database=$database;Encrypt=true;TrustServerCertificate=true", $uid, $pwd);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificamos que los datos lleguen por POST
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $nombre = $_POST['nombre'];
        $precio = $_POST['precio'];
        $estado = $_POST['estado'];
        $stock = $_POST['stock'];
        $descripcion = $_POST['descripcion'];

        // Preparamos la consulta SQL
        $sql = "INSERT INTO Productos (Nombre, Precio, Estado, Stock, Descripcion) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nombre, $precio, $estado, $stock, $descripcion]);

        echo "<h1>✅ ¡Producto guardado con éxito!</h1>";
        echo "<a href='index.php'>Volver al formulario</a>";
    }
} catch (PDOException $e) {
    echo "<h1>❌ Error al guardar:</h1> " . $e->getMessage();
}
?>