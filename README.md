# Clínica CIES

Sistema de gestión administrativa para la Clínica CIES (La Paz, Bolivia).
Proyecto de Programación 3, UNIFRANZ.

**Stack:** Laravel 13 · PHP 8.4 · MySQL 8.4 · todo corre en Docker.

## Integrantes del grupo

| Nombre completo | Rol | Usuario GitHub |
|-----------------|-----|----------------|
| Aranda Silva Alan Cristhofer | Líder del proyecto, desarrollo backend y vistas, panel del médico | [alanhack777](https://github.com/alanhack777) |
| Canchilla Mamani Isaac Fabricio | Docker y base de datos, módulos de usuarios, empleados y auditoría | [FabricioCanchilla](https://github.com/FabricioCanchilla) |
| Quispe Mamani Rosmery | Desarrollo backend y vistas, módulo de pacientes | [rosmeryqm2020-afk](https://github.com/rosmeryqm2020-afk) |
| Copa Pérez Neyda Milena | Documentación, pruebas y módulo de caja | [Milenacopa120-0409](https://github.com/Milenacopa120-0409) |

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
crea las 48 tablas y carga los datos de prueba. Cuando aparezca el mensaje `Listo`,
abre el sistema en el navegador.

Para dejarlo corriendo en segundo plano: `docker compose up -d --build`
y ver el avance con `docker compose logs -f app`.

| Servicio    | Dirección                                  |
|-------------|--------------------------------------------|
| Sistema     | http://localhost:8001                      |
| phpMyAdmin  | http://localhost:8081 (root / root123)     |
| MySQL       | 127.0.0.1:3309 (root / root123)            |

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
  (versión 3, **48 tablas**) y MySQL la ejecuta sola la primera vez que se crea el volumen.
- Los datos base (roles, empleados, usuarios) se cargan solos si la BD está vacía.
- Los datos clínicos de prueba (órdenes de examen, receta y lotes de medicamentos)
  se cargan con:

```bash
docker exec -it clinica_cies_app php artisan db:seed --class=DatosClinicosPruebaSeeder
```

  El seeder se puede ejecutar varias veces sin duplicar datos.
- Si cambias el SQL, recrea la BD (borra los datos): `docker compose down -v` y luego `docker compose up --build`.
- `01_estructura.sql` debe estar guardado en **UTF-8**.
- **Si ya tenías la BD de antes**, haz `docker compose down -v`: la versión 3 agrega la tabla
  `LOTE_MEDICAMENTO` y columnas nuevas (`CAMCON_USR`, `EST_RES`, `CRI_RES`, `CANENT_DRE`, entre otras).

## Usuarios de prueba

| Usuario     | Contraseña     | Rol           |
|-------------|----------------|---------------|
| admin       | admin123       | Administrador |
| recepcion   | recepcion123   | Recepcionista |
| medico      | medico123      | Médico        |
| enfermeria  | enfermeria123  | Enfermería    |
| caja        | caja123        | Caja          |
| paciente    | paciente123    | Paciente      |
| laboratorio | laboratorio123 | Laboratorio   |
| farmacia    | farmacia123    | Farmacia      |
| auditor     | auditor123     | Auditor       |

Cada rol entra a su propio panel. Si abre el de otro rol, devuelve 403.
Las cuentas que crea el administrador piden cambiar la contraseña en el primer ingreso.

## Módulos implementados

**Administrador** (`admin`)
- Usuarios: listado con búsqueda, crear, editar, cambiar rol, bloquear y desbloquear
  (un admin no puede modificar su propia cuenta), bloqueo automático tras 3 intentos fallidos
  y cambio de contraseña obligatorio en el primer ingreso.
- Empleados: listado, crear y editar.
- Pacientes: listado.

**Laboratorio** (RF025–RF029)
- Órdenes de examen con flujo `Pendiente → Muestra tomada → En proceso → Listo` (o `Cancelado`).
- Carga, validación y publicación de resultados (`Cargado → Validado → Publicado`).
- Si el valor es crítico, al publicar se genera una notificación para el médico que pidió el examen.

**Farmacia** (RF040, RF042, RNF017)
- Despacho de recetas ítem por ítem. Unidades requeridas = (24 ÷ frecuencia en horas) × días.
  Se permite despacho parcial; la receta pasa a `Completada` cuando todos los ítems están entregados.
- El stock se descuenta por **PEPS**: sale primero el lote que vence antes, y nunca se usan lotes vencidos.
- Entradas de lote, bajas con motivo (`Merma`, `Rotura`, `Vencido`) y alertas de vencimiento (30 días).

**Auditoría** (administrador y auditor)
- Registro de las acciones en `AUDITORIA`, con dominios `DOM01` (usuarios), `DOM02` (seguridad),
  `DOM03` (clínico) y `DOM04` (financiero/inventario), y filtros por texto, criticidad y fechas.

## Pendiente

- Auditoría: inicios de sesión, intentos fallidos y cierre de sesión (DOM02), filtro por dominio, exportar,
  y usuario de BD con solo `INSERT`/`SELECT` sobre `AUDITORIA` (hoy la app usa `root`).
- Microservicio Quarkus: verificación OTP.
- Farmacia: venta en mostrador con comprobante (RF041).
- Pantallas aún en construcción: recepción, enfermería, caja, paciente, citas e internación.
- Panel del médico: usa datos de ejemplo y todavía no muestra sus notificaciones.
- Recuperar contraseña (`/recuperar-contrasena`) y registro de pacientes (`/register`).

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
| Puerto 8001, 8081 o 3309 ocupado | Apaga lo que lo usa, o cambia el puerto izquierdo en `docker-compose.yml` |
| `container name ... already in use` | Hay contenedores viejos: `docker compose down` y vuelve a levantar |
| `Check constraint ... violated` | El SQL se guardó mal (debe ser UTF-8): corrige y haz `docker compose down -v` |
| La app no conecta a la BD | Mira `docker compose logs mysql`; espera a que MySQL termine de iniciar |
| Falta una tabla o columna (por ejemplo `LOTE_MEDICAMENTO` o `CAMCON_USR`) | Tu BD es de una versión anterior: `docker compose down -v` y levanta de nuevo |
| Los paneles de Laboratorio o Farmacia salen vacíos | Falta cargar los datos clínicos de prueba (ver sección Base de datos) |