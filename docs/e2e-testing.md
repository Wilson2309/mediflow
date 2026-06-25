# Pruebas End-to-End (E2E) con Playwright en MediFlow

## Introducción

Este documento describe cómo ejecutar y mantener las pruebas E2E automatizadas para MediFlow utilizando Playwright. Las pruebas validan los flujos principales de negocio interactuando con la interfaz de usuario como lo haría un usuario real (recepcionista, cajero, médico, administrador).

## Prerequisitos

Antes de ejecutar las pruebas, asegúrate de que el entorno cumpla con lo siguiente:

1. **Dependencias NPM:** `npm install` debe haberse ejecutado.
2. **Navegadores de Playwright:** Instalados vía `npx playwright install`.
3. **Servidor Local Activo:** El servidor de desarrollo de Laravel (`php artisan serve`) debe estar corriendo en `http://127.0.0.1:8000`.
4. **Base de Datos y Seeders:** La base de datos debe estar configurada. Es estrictamente necesario ejecutar el seeder de demostración para pruebas E2E:
   
   ```bash
   php artisan db:seed --class=E2EDemoSeeder
   ```
   *(Nota: No ejecutamos este seeder por defecto en el `DatabaseSeeder` para no contaminar los entornos de desarrollo con usuarios E2E de prueba constantes).*

## Ejecución de Pruebas

Para correr las pruebas, abre una terminal, asegúrate de que tu servidor (y Vite si es necesario) esté activo, y ejecuta uno de los siguientes comandos:

* **Modo Headless (Por Defecto - Más Rápido)**
  Ejecuta las pruebas en segundo plano sin abrir una ventana visible.
  ```bash
  npm run test:e2e
  ```

* **Modo Headed (Con Navegador Visible)**
  Abre Chrome para que puedas ver lo que el test está haciendo paso a paso.
  ```bash
  npm run test:e2e:headed
  ```

* **Modo Interfaz de Usuario (UI Mode)**
  Abre la interfaz avanzada de Playwright que permite depurar paso a paso, ver el DOM, la consola, redes, etc. ¡Excelente para desarrollo de nuevos tests!
  ```bash
  npm run test:e2e:ui
  ```

* **Ver Reporte HTML**
  Después de correr las pruebas, puedes ver un reporte detallado con videos y capturas (si hubo fallos) ejecutando:
  ```bash
  npm run test:e2e:report
  ```

## Estructura de Directorios

- `tests/e2e/`: Directorio raíz de pruebas E2E.
- `tests/e2e/helpers/`: Funciones reutilizables.
  - `auth.js`: Métodos de inicio y cierre de sesión.
  - `data.js`: Generadores de datos aleatorios.
- `tests/e2e/*.spec.js`: Archivos de especificaciones que contienen las aserciones.
- `playwright.config.js`: Configuración global de entorno.

## Manejo de Errores Comunes

* **ERR_CONNECTION_REFUSED**: Significa que Playwright no pudo acceder a `http://127.0.0.1:8000`. Asegúrate de iniciar `php artisan serve`.
* **Login Fails (401/403)**: Puede ocurrir si olvidaste correr el `E2EDemoSeeder` o si la base de datos fue refrescada (`migrate:fresh`).
* **Timeouts**: Si una prueba tarda más de 30s en encontrar un elemento, fallará. Asegúrate de que las animaciones, modales o peticiones AJAX completen a tiempo.
