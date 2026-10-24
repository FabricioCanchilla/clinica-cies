# Clínica CIES

Sistema de gestión para la Clínica CIES (La Paz, Bolivia).
Proyecto de Programación 3, UNIFRANZ.

**Stack:** Laravel 13 · PHP 8.5 · MySQL 8.4 (Docker)

## Requisitos

- PHP 8.5 y Composer
- Docker Desktop (tiene que estar abierto)
- Git

## Cómo levantar el proyecto

```bash
git clone https://github.com/FabricioCanchilla/clinica-cies.git
cd clinica-cies

composer install
copy .env.example .env
php artisan key:generate

docker compose up -d
```

Espera a que MySQL termine de iniciar (la primera vez crea las 47 tablas y tarda un poco). Para verlo:

```bash
docker compose logs -f mysql
```

Cuando aparezca `ready for connections` por segunda vez, sal con `Ctrl + C` y sigue:

```bash
php artisan db:seed
php artisan serve
```

Abre http://127.0.0.1:8000

## Base de datos

- No usamos migraciones. La estructura está en `database/sql/01_estructura.sql` y Docker la ejecuta sola la primera vez.
- Los datos de prueba se cargan con `php artisan db:seed` (roles, empleados, usuarios).
- MySQL queda en `127.0.0.1:3307` (usuario `root`, contraseña `root123`).
- phpMyAdmin (opcional): http://localhost:8081 (revisa el puerto en `docker-compose.yml`).

### Si cambias el script SQL

Docker solo ejecuta el script con el volumen vacío. Para recrear la BD (esto **borra los datos**):

```bash
docker compose down -v
docker compose up -d
php artisan db:seed
```

### Importante

`01_estructura.sql` debe estar guardado en **UTF-8**. Si no, las tildes del script (`Mañana`, `Miércoles`...) se corrompen y fallan los CHECK.

## Usuarios de prueba

La contraseña es el usuario + `123`.

| Usuario    | Contraseña    | Rol           |
|------------|---------------|---------------|
| admin      | admin123      | Administrador |
| recepcion  | recepcion123  | Recepcionista |
| medico     | medico123     | Médico        |
| enfermeria | enfermeria123 | Enfermería    |
| caja       | caja123       | Caja          |
| paciente   | paciente123   | Paciente      |

Cada rol entra a su propio panel. Si intentas abrir el panel de otro rol, devuelve 403.

## Flujo de trabajo en equipo

- `main` es la rama estable. **No se trabaja directo en `main`.**
- Cada tarea va en su propia rama:

```bash
git checkout main
git pull origin main
git checkout -b feature/nombre-de-la-tarea
```

- Se hacen commits pequeños y claros, y se sube la rama:

```bash
git add .
git commit -m "Describe lo que hiciste"
git push -u origin feature/nombre-de-la-tarea
```

- En GitHub se abre un **Pull Request** hacia `main`, y otro compañero lo revisa antes de unirlo.
- Antes de empezar a trabajar siempre: `git pull origin main`.
- **Nunca** subir el archivo `.env`.

## Problemas comunes

| Problema | Solución |
|----------|----------|
| `No such container` | Docker Desktop está apagado, o falta `docker compose up -d` |
| Puerto 3307 ocupado | Cambia `3307:3306` en `docker-compose.yml` y `DB_PORT` en `.env` |
| `Check constraint ... violated` al sembrar | El SQL se guardó mal; revisa que esté en UTF-8 y recrea la BD con `down -v` |
| Error de conexión al sembrar | Revisa que `.env` tenga `DB_PORT=3307` y `DB_PASSWORD=root123`, y corre `php artisan config:clear` |

```text
 /\_/\
( o.o )  ~~~  VAMOS KRAJO
 > ^ <
```
```text
░░░░░░░░░░░░░░░░░░░░░░░░░░░░░
░░░░█▀▀▀░█▀▀▀░░█▀▀░▀▀█░░█░░░░
░░░░█░▀█░█░▀█░░█▀▀░▄▀░░░▀░░░░
░░░░▀▀▀▀░▀▀▀▀░░▀▀▀░▀▀▀░░▀░░░░
░░░░░░░░░░░░░░░░░░░░░░░░░░░░░
```
prueba 1