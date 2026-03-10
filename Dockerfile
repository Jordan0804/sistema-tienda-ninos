FROM php:8.3-apache

# Instalamos dependencias y configuramos el repositorio de Microsoft
RUN apt-get update && apt-get install -y unixodbc-dev gnupg2 curl \
    && curl -L https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/microsoft-ascii.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft-ascii.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 unixodbc-dev

# Instalamos drivers de SQL Server
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

RUN a2enmod rewrite
