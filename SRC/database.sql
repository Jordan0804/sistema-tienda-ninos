CREATE DATABASE SistemaFacturacion;
GO
USE SistemaFacturacion;
GO
CREATE TABLE Productos (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Nombre VARCHAR(100) NOT NULL,
    Precio DECIMAL(10,2) NOT NULL,
    Estado VARCHAR(20) NOT NULL,
    Stock INT DEFAULT 0,
    Descripcion TEXT,
    FechaRegistro DATETIME DEFAULT GETDATE()
);