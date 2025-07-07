# Pixel Factory – Backend

Este repositorio contiene la API y la lógica de negocio en PHP para el proyecto **Pixel Factory**, una plataforma de e-commerce de componentes de PC.

---

## 📂 Estructura del proyecto

PixelFactory-Backend/
├─ private/ # Código no público y configuraciones
│ ├─ config/
│ │ ├─ .env.example # Ejemplo de variables de entorno
│ │ └─ .env # Tu configuración local (IGNORAR EN GIT)
│ ├─ scripts/ # Lógica de negocio (pago.php, seeders, etc.)
│ └─ sql/
│ └─ 1_pixelfactory.sql # Dump de la base de datos
│
├─ public_html/ # Punto de entrada web (servidor)
│ ├─ index.php # Front controller
│ └─ assets/ # CSS, JS, imágenes públicas
│
├─ src/ # Clases, controladores y helpers
│
├─ composer.json # Definición de dependencias
├─ composer.lock # Versionado de dependencias
└─ README.md # Documentación de este repositorio

---

## ⚙️ Requisitos previos

- **PHP** ≥ 8.0
- **Composer** (https://getcomposer.org)
- **MySQL** o **MariaDB**
- **XAMPP**, **MAMP** o cualquier servidor Apache/Nginx con PHP instalado

---

## 🚀 Instalación paso a paso

1. **Clonar el repositorio**

   ```bash
   git clone git@github.com:TuUsuario/PixelFactory-Backend.git
   cd PixelFactory-Backend

   ```

2. **Configurar variables de entorno**
   cp private/config/.env.example private/config/.env
   abre private/config/.env y completa:

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=pixelfactory
   DB_USER=root
   DB_PASS=

   MPAGO_API_CLIENT=TU_CLIENT_ID_SANDBOX
   MPAGO_API_SECRET=TU_SECRET_SANDBOX

3. **Instalar dependencias con Composer**
   Abri una terminal en la raiz del proyecto y ejecuta:
   composer install

4. **Crear la base de datos**
   -Arranca MySQL desde XAMPP/MAMP o tu servicio habitual.
   -En phpMyAdmin (o CLI) crea la base pixelFactory.

5. **Configurar el servidor web**
   -Si usas XAMPP, copia public_html/ a htdocs/pixelfactory-back/public_html.
   -Asegúrate de que Apache apunte a esa carpeta como DocumentRoot.
   -Reinicia Apache/MySQL desde el panel de control de XAMPP.
   -Recorda siempre ejecutar XAMPP como Administrador.
   -Accede a:
   http://localhost/pixelfactory-back/public_html/index.php

---

## 🔗 Endpoints principales

    | Método | Ruta                                  | Descripción                           |
    | :----- | :------------------------------------ | :------------------------------------ |
    | POST   | `/api/paypal/create-order`            | Crear una orden de pago en sandbox    |
    | GET    | `/api/paypal/capture-order/{orderId}` | Capturar una orden aprobada           |
    | GET    | `/api/paypal/cancel-order`            | Manejar la cancelación de un pago     |
    | GET    | `/api/status`                         | Endpoint de salud / prueba (opcional) |

---

## 🛡️ Buenas prácticas de seguridad

    -Nunca subas tu .env real ni la carpeta vendor/: están en .gitignore.
    -Valida siempre las respuestas de PayPal antes de marcar un pago como completado.
    -Usa HTTPS en producción para proteger credenciales y tokens.
    -Mantén Composer y PHP actualizados a la última versión estable.

---

## 📝 Licencia

    Este proyecto se publica bajo la licencia MIT.
    Consulta el archivo LICENSE para más detalles.

---

## 🤝 Contribuir

1. **Haz fork de este repositorio.**

2. **Crea una rama con tu feature o fix:**
   git checkout -b feature/nueva-funcionalidad

3. **Haz tus cambios y commit:**
   git commit -m "Añadir nueva funcionalidad X"
4. **Push y abre un Pull Request.**
