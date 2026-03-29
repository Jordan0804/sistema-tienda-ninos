USE SistemaFacturacion;
GO

-- ── 1. Columnas nuevas en Productos (se omiten si ya existen) ──────
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Categoria'    AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Categoria    VARCHAR(50)   NOT NULL DEFAULT 'General';

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Talla'        AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Talla        VARCHAR(20)   NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Color'        AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Color        VARCHAR(30)   NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Precio_Costo' AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Precio_Costo DECIMAL(10,2) NOT NULL DEFAULT 0.00;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Precio_Venta' AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Precio_Venta DECIMAL(10,2) NOT NULL DEFAULT 0.00;
GO

-- ── 2. Copiar precio anterior → Precio_Venta (solo si existía Precio) ──
IF EXISTS (SELECT 1 FROM sys.columns WHERE Name='Precio' AND Object_ID=OBJECT_ID('Productos'))
    EXEC('UPDATE Productos SET Precio_Venta = Precio WHERE Precio_Venta = 0');
GO

-- ── 3. Tabla Facturas ──────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name='Facturas')
BEGIN
    CREATE TABLE Facturas (
        Id               INT            PRIMARY KEY IDENTITY(1,1),
        NumeroFactura    VARCHAR(25)    NOT NULL,
        FechaVenta       DATETIME       NOT NULL DEFAULT GETDATE(),
        Subtotal         DECIMAL(10,2)  NOT NULL,
        ITBIS            DECIMAL(10,2)  NOT NULL,
        Total            DECIMAL(10,2)  NOT NULL,
        MetodoPago       VARCHAR(20)    NOT NULL DEFAULT 'Efectivo',
        EfectivoRecibido DECIMAL(10,2)  NULL,
        Cambio           DECIMAL(10,2)  NULL,
        FechaRegistro    DATETIME       NOT NULL DEFAULT GETDATE()
    );
END
GO

-- ── 4. Tabla Detalle_Facturas ──────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name='Detalle_Facturas')
BEGIN
    CREATE TABLE Detalle_Facturas (
        Id             INT            PRIMARY KEY IDENTITY(1,1),
        FacturaId      INT            NOT NULL,
        ProductoId     INT            NOT NULL,
        NombreProducto VARCHAR(100)   NOT NULL,
        Cantidad       INT            NOT NULL,
        PrecioUnitario DECIMAL(10,2)  NOT NULL,
        Subtotal       DECIMAL(10,2)  NOT NULL,
        CONSTRAINT FK_Det_Factura  FOREIGN KEY (FacturaId)
            REFERENCES Facturas(Id) ON DELETE CASCADE,
        CONSTRAINT FK_Det_Producto FOREIGN KEY (ProductoId)
            REFERENCES Productos(Id)
    );
END
GO

PRINT 'Listo. Recarga el sistema.';
GO