# 🛒 Sistema Tienda Niños

Sistema de gestión de inventario y facturación para una tienda infantil, desarrollado como proyecto de práctica profesional.
Este proyecto utiliza un entorno **containerizado** para facilitar su despliegue en cualquier sistema.

---

## 🚀 Tecnologías usadas

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=flat&logo=docker&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)

---

## 📋 Funcionalidades principales

- **Gestión de Inventario:** Registro, edición y control de stock de productos infantiles.
- **Módulo de Ventas:** Interfaz dinámica para realizar facturación básica (`vender.php`).
- **Seguridad:** Sistema de autenticación de usuarios (`auth.php`).
- **Arquitectura Limpia:** Separación de lógica en la carpeta `SRC`.
- **Despliegue Rápido:** Configuración lista para Docker.

---

## ⚙️ Instalación y despliegue

### Prerrequisitos
- Tener instalado [Docker Desktop](https://www.docker.com/) y Docker Compose.

### Pasos para correr el proyecto

```bash
# 1. Clonar el repositorio
git clone https://github.com/Jordan0804/sistema-tienda-ninos.git

# 2. Entrar a la carpeta del proyecto
cd sistema-tienda-ninos

# 3. Levantar los servicios (PHP + MySQL)
docker-compose up -d

# 4. Acceder al sistema
# Abre tu navegador en: http://localhost:8080
```

---

## 📁 Estructura del proyecto

```text
sistema-tienda-ninos/
├── SRC/                # Lógica del negocio, vistas y scripts SQL
├── Dockerfile          # Configuración del contenedor Apache + PHP
├── docker-compose.yml  # Orquestación de servicios y base de datos
└── README.md           # Documentación del proyecto
```

---

## 🤝 Contribuciones

¡Las contribuciones son bienvenidas! Si encuentras un error o quieres añadir una funcionalidad:

1. Haz un **Fork** del proyecto.
2. Crea una rama para tu mejora: `git checkout -b feature/MejoraIncreible`.
3. Haz tus cambios y un **Commit**: `git commit -m 'Add: Nueva funcionalidad'`.
4. Sube tus cambios: `git push origin feature/MejoraIncreible`.
5. Abre un **Pull Request**.

---

## 👤 Autor

**Jordan Viola** *Estudiante de Desarrollo de Software - ITLA*

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0A66C2?style=flat&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/jordan-viola-de-los-santos)
[![Email](https://img.shields.io/badge/Email-EA4335?style=flat&logo=gmail&logoColor=white)](mailto:ing.viola08@gmail.com)

---

> Desarrollado por Jordan Viola en Santo Domingo, República Dominicana.
