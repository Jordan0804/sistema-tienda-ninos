-- ============================================================
-- setup_ventas.sql
-- Script de instalación / migración segura
-- Sistema de Facturación — Tienda Niños
--
-- INSTRUCCIONES:
--   1. Abrir SQL Server Management Studio (SSMS)
--   2. Conectarse al servidor
--   3. Abrir este archivo (File > Open > File)
--   4. Presionar F5 o el botón "Execute"
--
-- Este script es IDEMPOTENTE: puedes ejecutarlo múltiples
-- veces sin que duplique tablas ni columnas.
-- ============================================================

USE SistemaFacturacion;
GO

PRINT '=== Iniciando setup_ventas.sql ===';
GO

-- ────────────────────────────────────────────────────────────
-- PASO 1: Verificar que la base de datos existe
-- ────────────────────────────────────────────────────────────
IF DB_ID('SistemaFacturacion') IS NULL
BEGIN
    PRINT '[ERROR] La base de datos SistemaFacturacion no existe.';
    PRINT 'Crea la base de datos primero y vuelve a ejecutar este script.';
    -- Detiene la ejecución
    THROW 50001, 'Base de datos SistemaFacturacion no encontrada.', 1;
END
GO

-- ────────────────────────────────────────────────────────────
-- PASO 2: Tabla Productos
--   Si ya existe con el esquema antiguo (columna Precio),
--   se agregan las columnas nuevas sin tocar los datos.
--   Si no existe, se crea completa.
-- ────────────────────────────────────────────────────────────

-- 2a. Crear la tabla si no existe
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Productos')
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
        Precio_Venta  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
        Stock         INT            NOT NULL DEFAULT 0
                          CONSTRAINT CHK_Prod_Stock CHECK (Stock >= 0),
        Descripcion   NVARCHAR(500)  NULL,
        FechaRegistro DATETIME       NOT NULL DEFAULT GETDATE()
    );
    PRINT '[OK] Tabla Productos creada.';
END
ELSE
BEGIN
    PRINT '[INFO] Tabla Productos ya existe. Verificando columnas...';
END
GO

-- 2b. Agregar columna Categoria si no existe
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE  Name = 'Categoria' AND Object_ID = OBJECT_ID('Productos')
)
BEGIN
    ALTER TABLE Productos ADD Categoria VARCHAR(50) NOT NULL DEFAULT 'General';
    PRINT '[OK] Columna Categoria agregada.';
END
ELSE PRINT '[SKIP] Columna Categoria ya existe.';
GO

-- 2c. Agregar columna Talla si no existe
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE  Name = 'Talla' AND Object_ID = OBJECT_ID('Productos')
)
BEGIN
    ALTER TABLE Productos ADD Talla VARCHAR(20) NULL;
    PRINT '[OK] Columna Talla agregada.';
END
ELSE PRINT '[SKIP] Columna Talla ya existe.';
GO

-- 2d. Agregar columna Color si no existe
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE  Name = 'Color' AND Object_ID = OBJECT_ID('Productos')
)
BEGIN
    ALTER TABLE Productos ADD Color VARCHAR(30) NULL;
    PRINT '[OK] Columna Color agregada.';
END
ELSE PRINT '[SKIP] Columna Color ya existe.';
GO

-- 2e. Agregar columna Precio_Costo si no existe
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE  Name = 'Precio_Costo' AND Object_ID = OBJECT_ID('Productos')
)
BEGIN
    ALTER TABLE Productos ADD Precio_Costo DECIMAL(10,2) NOT NULL DEFAULT 0.00;
    PRINT '[OK] Columna Precio_Costo agregada.';
END
ELSE PRINT '[SKIP] Columna Precio_Costo ya existe.';
GO

-- 2f. Agregar columna Precio_Venta si no existe
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE  Name = 'Precio_Venta' AND Object_ID = OBJECT_ID('Productos')
)
BEGIN
    ALTER TABLE Productos ADD Precio_Venta DECIMAL(10,2) NOT NULL DEFAULT 0.00;
    PRINT '[OK] Columna Precio_Venta agregada.';

    -- Migrar el valor de la columna Precio antigua → Precio_Venta
    -- (solo si existe la columna Precio con datos)
    IF EXISTS (
        SELECT 1 FROM sys.columns
        WHERE  Name = 'Precio' AND Object_ID = OBJECT_ID('Productos')
    )
    BEGIN
        UPDATE Productos
        SET    Precio_Venta = Precio
        WHERE  Precio_Venta = 0 AND Precio > 0;
        PRINT '[OK] Valores migrados de Precio → Precio_Venta.';
    END
END
ELSE PRINT '[SKIP] Columna Precio_Venta ya existe.';
GO

-- ────────────────────────────────────────────────────────────
-- PASO 3: Tabla Facturas
--   Encabezado de cada venta (una fila por transacción).
-- ────────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Facturas')
BEGIN
    CREATE TABLE Facturas (
        Id               INT            PRIMARY KEY IDENTITY(1,1),
        -- Número legible, ej: FAC-20260320-0001 (generado por PHP tras el INSERT)
        NumeroFactura    VARCHAR(25)    NOT NULL,
        FechaVenta       DATETIME       NOT NULL DEFAULT GETDATE(),
        Subtotal         DECIMAL(10,2)  NOT NULL,
        ITBIS            DECIMAL(10,2)  NOT NULL,   -- 18% sobre el Subtotal
        Total            DECIMAL(10,2)  NOT NULL,   -- Subtotal + ITBIS
        MetodoPago       VARCHAR(20)    NOT NULL DEFAULT 'Efectivo',
                                                    -- Efectivo | Tarjeta | Transferencia
        EfectivoRecibido DECIMAL(10,2)  NULL,       -- Solo si MetodoPago = 'Efectivo'
        Cambio           DECIMAL(10,2)  NULL,       -- EfectivoRecibido - Total
        FechaRegistro    DATETIME       NOT NULL DEFAULT GETDATE()
    );
    PRINT '[OK] Tabla Facturas creada.';
END
ELSE
    PRINT '[SKIP] Tabla Facturas ya existe.';
GO

-- ────────────────────────────────────────────────────────────
-- PASO 4: Tabla Detalle_Facturas
--   Un registro por cada producto dentro de una factura.
-- ────────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Detalle_Facturas')
BEGIN
    CREATE TABLE Detalle_Facturas (
        Id              INT            PRIMARY KEY IDENTITY(1,1),
        FacturaId       INT            NOT NULL,
        ProductoId      INT            NOT NULL,
        -- Guardamos el nombre tal como era al momento de la venta
        -- (si editas el nombre del producto, el historial no cambia)
        NombreProducto  VARCHAR(100)   NOT NULL,
        Cantidad        INT            NOT NULL
                            CONSTRAINT CHK_Det_Cant CHECK (Cantidad > 0),
        PrecioUnitario  DECIMAL(10,2)  NOT NULL,
        Subtotal        DECIMAL(10,2)  NOT NULL,   -- Cantidad * PrecioUnitario

        -- FK → Facturas: si borras la factura, se borran sus líneas
        CONSTRAINT FK_Det_Factura  FOREIGN KEY (FacturaId)
            REFERENCES Facturas(Id) ON DELETE CASCADE,

        -- FK → Productos: NO puede borrarse un producto con ventas
        CONSTRAINT FK_Det_Producto FOREIGN KEY (ProductoId)
            REFERENCES Productos(Id)
    );
    PRINT '[OK] Tabla Detalle_Facturas creada.';
END
ELSE
    PRINT '[SKIP] Tabla Detalle_Facturas ya existe.';
GO

-- ────────────────────────────────────────────────────────────
-- PASO 5: Índices de rendimiento (evitan tablas completas)
-- ────────────────────────────────────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE  name = 'IX_Facturas_FechaVenta'
      AND  object_id = OBJECT_ID('Facturas')
)
BEGIN
    CREATE INDEX IX_Facturas_FechaVenta ON Facturas (FechaVenta DESC);
    PRINT '[OK] Índice IX_Facturas_FechaVenta creado.';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE  name = 'IX_Detalle_FacturaId'
      AND  object_id = OBJECT_ID('Detalle_Facturas')
)
BEGIN
    CREATE INDEX IX_Detalle_FacturaId ON Detalle_Facturas (FacturaId);
    PRINT '[OK] Índice IX_Detalle_FacturaId creado.';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE  name = 'IX_Productos_Categoria'
      AND  object_id = OBJECT_ID('Productos')
)
BEGIN
    CREATE INDEX IX_Productos_Categoria ON Productos (Categoria);
    PRINT '[OK] Índice IX_Productos_Categoria creado.';
END
GO

-- ────────────────────────────────────────────────────────────
-- PASO 6: Verificación final — listar las tablas creadas
-- ────────────────────────────────────────────────────────────
SELECT
    t.name        AS Tabla,
    p.rows        AS Filas,
    CAST(ROUND(SUM(a.total_pages) * 8 / 1024.0, 2) AS VARCHAR) + ' MB' AS TamañoAprox
FROM       sys.tables             t
INNER JOIN sys.indexes            i  ON t.object_id  = i.object_id
INNER JOIN sys.partitions         p  ON i.object_id  = p.object_id AND i.index_id = p.index_id
INNER JOIN sys.allocation_units   a  ON p.partition_id = a.container_id
WHERE  t.name IN ('Productos', 'Facturas', 'Detalle_Facturas', 'Usuarios')
  AND  i.index_id <= 1
GROUP BY t.name, p.rows
ORDER BY t.name;

GO
-- ────────────────────────────────────────────────────────────
-- PASO 7: Columna Aplica_ITBIS en Productos
--   Permite indicar si el producto lleva ITBIS (18%) o no.
--   Por defecto 1 = sí aplica.
-- ────────────────────────────────────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE  Name = 'Aplica_ITBIS' AND Object_ID = OBJECT_ID('Productos')
)
BEGIN
    ALTER TABLE Productos ADD Aplica_ITBIS BIT NOT NULL DEFAULT 1;
    PRINT '[OK] Columna Aplica_ITBIS agregada a Productos.';
END
ELSE
    PRINT '[SKIP] Columna Aplica_ITBIS ya existe.';
GO

-- ────────────────────────────────────────────────────────────
-- PASO 8: Tabla Usuarios
--   Almacena los operadores del sistema con sus roles.
--   Roles disponibles: Admin | Cajero | Inventario
-- ────────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Usuarios')
BEGIN
    CREATE TABLE Usuarios (
        Id            INT          PRIMARY KEY IDENTITY(1,1),
        Nombre        VARCHAR(100) NOT NULL,
        Usuario       VARCHAR(50)  NOT NULL,           -- Nombre de login (único)
        Contrasena    VARCHAR(255) NOT NULL,            -- Hash bcrypt (password_hash PHP)
        Rol           VARCHAR(20)  NOT NULL DEFAULT 'Cajero',
                                   CONSTRAINT CHK_Rol CHECK (Rol IN ('Admin','Cajero','Inventario')),
        Activo        BIT          NOT NULL DEFAULT 1,  -- 0 = cuenta desactivada
        FechaCreacion DATETIME     NOT NULL DEFAULT GETDATE(),
        CreadoPor     INT          NULL REFERENCES Usuarios(Id),
        CONSTRAINT UQ_Usuario UNIQUE (Usuario)
    );
    PRINT '[OK] Tabla Usuarios creada.';
END
ELSE
    PRINT '[SKIP] Tabla Usuarios ya existe.';
GO

PRINT '=== setup_ventas.sql completado exitosamente ===';
GO
