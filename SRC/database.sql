-- ============================================================
-- database.sql — Esquema completo del Sistema de Facturación
-- Tienda de Ropa Niños | SQL Server
-- ============================================================
-- INSTRUCCIONES:
--   Opción A (instalación nueva):    Ejecutar todo este script.
--   Opción B (base de datos existente): Ver sección "MIGRACIÓN"
--                                    más abajo y ejecutar solo esa parte.
-- ============================================================

-- ── 1. BASE DE DATOS ─────────────────────────────────────────
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'SistemaFacturacion')
    CREATE DATABASE SistemaFacturacion;
GO
USE SistemaFacturacion;
GO

-- ── 2. TABLA: Productos ──────────────────────────────────────
--    Catálogo de ropa con costos, precios de venta y stock.
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Productos')
BEGIN
    CREATE TABLE Productos (
        Id            INT            PRIMARY KEY IDENTITY(1,1),
        Nombre        VARCHAR(100)   NOT NULL,
        Categoria     VARCHAR(50)    NOT NULL DEFAULT 'General',
        Talla         VARCHAR(20)    NULL,
        Color         VARCHAR(30)    NULL,
        Estado        VARCHAR(20)    NOT NULL
                          CONSTRAINT CHK_Prod_Estado CHECK (Estado IN ('Nuevo','Seminuevo')),
        Precio_Costo  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
        Precio_Venta  DECIMAL(10,2)  NOT NULL,
        Stock         INT            NOT NULL DEFAULT 0
                          CONSTRAINT CHK_Prod_Stock CHECK (Stock >= 0),
        Descripcion   NVARCHAR(500)  NULL,
        FechaRegistro DATETIME       NOT NULL DEFAULT GETDATE()
    );
    PRINT 'Tabla Productos creada.';
END
GO

-- ══ MIGRACIÓN ════════════════════════════════════════════════
-- Si ya tienes la tabla Productos con el esquema ANTERIOR
-- (columna Precio en lugar de Precio_Costo / Precio_Venta),
-- descomenta y ejecuta SOLO estas líneas:
--
-- ALTER TABLE Productos ADD Categoria    VARCHAR(50)   NOT NULL DEFAULT 'General';
-- ALTER TABLE Productos ADD Talla        VARCHAR(20)   NULL;
-- ALTER TABLE Productos ADD Color        VARCHAR(30)   NULL;
-- ALTER TABLE Productos ADD Precio_Costo DECIMAL(10,2) NOT NULL DEFAULT 0.00;
-- ALTER TABLE Productos ADD Precio_Venta DECIMAL(10,2) NOT NULL DEFAULT 0.00;
-- -- Copia el precio viejo al nuevo campo:
-- UPDATE Productos SET Precio_Venta = Precio WHERE Precio_Venta = 0;
-- -- (Opcional) Elimina la columna antigua si ya no la necesitas:
-- -- ALTER TABLE Productos DROP COLUMN Precio;
-- ═════════════════════════════════════════════════════════════

-- ── 3. TABLA: Facturas ───────────────────────────────────────
--    Encabezado de cada venta (una fila por transacción).
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Facturas')
BEGIN
    CREATE TABLE Facturas (
        Id               INT            PRIMARY KEY IDENTITY(1,1),
        NumeroFactura    VARCHAR(25)    NOT NULL,              -- Ej: FAC-20260320-0001
        FechaVenta       DATETIME       NOT NULL DEFAULT GETDATE(),
        Subtotal         DECIMAL(10,2)  NOT NULL,
        ITBIS            DECIMAL(10,2)  NOT NULL,              -- 18% calculado
        Total            DECIMAL(10,2)  NOT NULL,
        MetodoPago       VARCHAR(20)    NOT NULL DEFAULT 'Efectivo',  -- Efectivo | Tarjeta | Transferencia
        EfectivoRecibido DECIMAL(10,2)  NULL,                  -- Solo aplica si MetodoPago = Efectivo
        Cambio           DECIMAL(10,2)  NULL,
        FechaRegistro    DATETIME       NOT NULL DEFAULT GETDATE()
    );
    PRINT 'Tabla Facturas creada.';
END
GO

-- ── 4. TABLA: Detalle_Facturas ───────────────────────────────
--    Líneas de cada factura (un registro por producto vendido).
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Detalle_Facturas')
BEGIN
    CREATE TABLE Detalle_Facturas (
        Id             INT            PRIMARY KEY IDENTITY(1,1),
        FacturaId      INT            NOT NULL,
        ProductoId     INT            NOT NULL,
        NombreProducto VARCHAR(100)   NOT NULL,   -- Snapshot del nombre al momento de la venta
        Cantidad       INT            NOT NULL     CONSTRAINT CHK_Det_Cant CHECK (Cantidad > 0),
        PrecioUnitario DECIMAL(10,2)  NOT NULL,
        Subtotal       DECIMAL(10,2)  NOT NULL,
        -- Si se borra una Factura, se borran sus detalles automáticamente
        CONSTRAINT FK_Detalle_Factura  FOREIGN KEY (FacturaId)  REFERENCES Facturas(Id) ON DELETE CASCADE,
        -- No se puede borrar un Producto que tenga ventas registradas
        CONSTRAINT FK_Detalle_Producto FOREIGN KEY (ProductoId) REFERENCES Productos(Id)
    );
    PRINT 'Tabla Detalle_Facturas creada.';
END
GO

-- ── 5. ÍNDICES de rendimiento ─────────────────────────────────
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_FechaVenta' AND object_id = OBJECT_ID('Facturas'))
    CREATE INDEX IX_Facturas_FechaVenta  ON Facturas        (FechaVenta DESC);
GO
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Detalle_FacturaId'  AND object_id = OBJECT_ID('Detalle_Facturas'))
    CREATE INDEX IX_Detalle_FacturaId    ON Detalle_Facturas (FacturaId);
GO
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Productos_Categoria' AND object_id = OBJECT_ID('Productos'))
    CREATE INDEX IX_Productos_Categoria  ON Productos        (Categoria);
GO

PRINT '=== Esquema completado exitosamente ===';
GO
