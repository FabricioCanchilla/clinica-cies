# Clínica CIES

Sistema de gestión administrativa para la Clínica CIES (La Paz, Bolivia).
Proyecto de Programación 3, UNIFRANZ.

**Stack:** Laravel 13 · PHP 8.4 · MySQL 8.4 · todo corre en Docker.

## Requisitos

Solo dos cosas. **No hace falta instalar PHP, Composer, XAMPP ni MySQL.**

- Docker Desktop (abierto y corriendo)
- Git

## Levantar el sistema

```bash
git clone https://github.com/FabricioCanchilla/clinica-cies.git
cd clinica-cies
docker compose up --build
```

La primera vez tarda unos minutos: descarga las imágenes, instala las dependencias,
crea las 47 tablas y carga los datos de prueba. Cuando aparezca `Listo. Abre http://localhost:8000`,
abre ese enlace en el navegador.

Para dejarlo corriendo en segundo plano: `docker compose up -d --build`
y ver el avance con `docker compose logs -f app`.

| Servicio    | Dirección                                  |
|-------------|--------------------------------------------|
| Sistema     | http://localhost:8000                      |
| phpMyAdmin  | http://localhost:8081 (root / root123)     |
| MySQL       | 127.0.0.1:3307 (root / root123)            |

## Comandos útiles

```bash
docker compose up -d          # encender
docker compose down           # apagar (conserva los datos)
docker compose down -v        # apagar y BORRAR la base de datos y las dependencias
docker compose logs -f app    # ver logs de la aplicación
docker exec -it clinica_cies_app php artisan <comando>   # artisan dentro del contenedor
docker exec -it clinica_cies_db mysql -uroot -p clinica_cies -e "SELECT * FROM USUARIO;"
```

## Base de datos

- No usamos migraciones. La estructura está en `database/sql/01_estructura.sql`
  y MySQL la ejecuta sola la primera vez que se crea el volumen.
- Los datos de prueba (roles, empleados, usuarios) se cargan solos si la BD está vacía.
- Si cambias el SQL, recrea la BD (borra los datos): `docker compose down -v` y luego `docker compose up --build`.
- `01_estructura.sql` debe estar guardado en **UTF-8**.

## Usuarios de prueba

| Usuario    | Contraseña    | Rol           |
|------------|---------------|---------------|
| admin      | admin123      | Administrador |
| recepcion  | recepcion123  | Recepcionista |
| medico     | medico123     | Médico        |
| enfermeria | enfermeria123 | Enfermería    |
| caja       | caja123       | Caja          |
| paciente   | paciente123   | Paciente      |

Cada rol entra a su propio panel. Si abre el de otro rol, devuelve 403.
Las cuentas que crea el administrador piden cambiar la contraseña en el primer ingreso.

## Módulo de usuarios (rama `feature/usuarios`)

Entrando como `admin` → **Usuarios** y **Auditoría**:

- Listado con búsqueda, crear usuario para personal, editar usuario y cambiar rol.
- Bloquear y desbloquear cuentas (un admin no puede modificar su propia cuenta).
- Bloqueo automático tras 3 intentos fallidos.
- Cambio de contraseña obligatorio en el primer ingreso.
- Auditoría de todas las acciones, con filtros por texto, criticidad y fechas.

Pendiente: restablecer contraseña desde el admin, inmutabilidad de `AUDITORIA`
(usuario de BD sin UPDATE/DELETE) y módulo de empleados.

## Flujo de trabajo en equipo

- `main` es la rama estable. **No se trabaja directo en `main`.**
- Cada tarea va en su propia rama:

```bash
git checkout main
git pull origin main
git checkout -b feature/nombre-de-la-tarea
```

- Commits pequeños y claros, y se sube la rama:

```bash
git add .
git commit -m "Describe lo que hiciste"
git push -u origin feature/nombre-de-la-tarea
```

- En GitHub se abre un **Pull Request** hacia `main` y otro compañero lo revisa antes de unirlo.
- Antes de empezar a trabajar: `git pull origin main`.
- **Nunca** subir el archivo `.env`.

## Problemas comunes

| Problema | Solución |
|----------|----------|
| `Cannot connect to the Docker daemon` | Docker Desktop está apagado; ábrelo y espera a que inicie |
| Puerto 8000, 8081 o 3307 ocupado | Apaga lo que lo usa, o cambia el puerto izquierdo en `docker-compose.yml` |
| `container name ... already in use` | Hay contenedores viejos: `docker compose down` y vuelve a levantar |
| `Check constraint ... violated` | El SQL se guardó mal (debe ser UTF-8): corrige y haz `docker compose down -v` |
| La app no conecta a la BD | Mira `docker compose logs mysql`; espera a que MySQL termine de iniciar |
| Tenías la BD de antes y falta la columna `CAMCON_USR` | `docker compose down -v` y levanta de nuevo |
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
<<<<<<< HEAD
prueba 2 mdfk
=======
prueba 2 mdfk

ALAN ARANDA
>>>>>>> feature/panel-medico
 
ROSY EM YA ISE LA PRUEBA XD 