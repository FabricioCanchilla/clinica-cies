# Clínica CIES

Sistema de gestión administrativa para la Clínica CIES (La Paz, Bolivia).
Proyecto de Programación 3, UNIFRANZ.

**Stack:** Laravel 13 · PHP 8.4 · MySQL 8.4 · Docker.

## Integrantes del grupo

| Nombre completo | Rol | Usuario GitHub |
|-----------------|-----|----------------|
| Aranda Silva Alan Cristhofer | Líder del proyecto, desarrollo backend y vistas, panel del médico | [alanhack777](https://github.com/alanhack777) |
| Canchilla Mamani Isaac Fabricio | Docker y base de datos, módulos de usuarios, empleados y auditoría | [FabricioCanchilla](https://github.com/FabricioCanchilla) |
| Quispe Mamani Rosmery | Desarrollo backend y vistas, módulo de pacientes | [rosmeryqm2020-afk](https://github.com/rosmeryqm2020-afk) |
| Copa Pérez Neyda Milena | Documentación, pruebas y módulo de caja | [Milenacopa120-0409](https://github.com/Milenacopa120-0409) |

## Requisitos

- **Opción A (Docker):** solo Docker Desktop (abierto y corriendo) y Git. No hace falta instalar PHP, Composer, XAMPP ni MySQL.
- **Opción B (PHP local, para desarrollar):** Docker Desktop, Git, PHP 8.4 o superior y Composer.

## Levantar el sistema

### Opción A: todo en Docker (recomendada)

```bash
git clone https://github.com/FabricioCanchilla/clinica-cies.git
cd clinica-cies
docker compose up --build
```

La primera vez tarda unos minutos: descarga las imágenes, instala las dependencias,
crea las 48 tablas, el usuario `cies_app` y carga los datos de prueba. Cuando aparezca
el mensaje `Listo`, abre el sistema en el navegador.

Para dejarlo corriendo en segundo plano: `docker compose up -d --build`
y ver el avance con `docker compose logs -f app`.

### Opción B: PHP local

Solo la base de datos va en Docker; la app corre con tu PHP.

```powershell
docker compose up -d mysql
composer install
copy .env.example .env      # solo la primera vez
php artisan key:generate    # solo la primera vez
php artisan db:seed         # solo si la BD está vacía
php artisan serve
```

Se abre en http://localhost:8000.

### Direcciones

| Servicio    | Dirección                                                              |
|-------------|------------------------------------------------------------------------|
| Sistema     | http://localhost:8001 (Opción A) · http://localhost:8000 (Opción B)    |
| phpMyAdmin  | http://localhost:8081 (root / root123)                                 |
| MySQL       | 127.0.0.1:3307 (root / root123)                                        |

La app se conecta con el usuario `cies_app`, que solo puede hacer `SELECT` e `INSERT` sobre
`AUDITORIA` (los eventos son inmutables). Para tareas de administración de la BD usa `root`.
El usuario se crea con `database/sql/02_usuario_auditoria.sql`, que solo se ejecuta cuando
se crea el volumen de MySQL. Si agregas una tabla nueva a `01_estructura.sql`, recrea la BD
con `docker compose down -v` para que reciba sus permisos.

## Comandos útiles

```bash
docker compose up -d          # encender
docker compose down           # apagar (conserva los datos)
docker compose down -v        # apagar y BORRAR la base de datos y las dependencias
docker compose logs -f app    # ver logs de la aplicación
docker exec -it clinica_cies_app php artisan <comando>   # artisan dentro del contenedor
docker exec -it clinica_cies_db mysql -uroot -p clinica_cies -e "SELECT * FROM USUARIO;"
```

**Después de cada `git pull`:** si trae cambios en `composer.json` o `composer.lock`
(por ejemplo, las librerías de exportación de auditoría), instala las dependencias en el mismo
entorno donde corre la app. Con Docker basta `docker compose up -d` (el contenedor corre
`composer install` al arrancar); con PHP local, `composer install`.

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
  `LOTE_MEDICAMENTO` y columnas nuevas (`CAMCON_USR`, `EST_RES`, `CRI_RES`, `CANENT_DRE`, entre otras),
  y el usuario `cies_app` solo existe en volúmenes creados con el script `02_usuario_auditoria.sql`.

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
  `DOM03` (clínico) y `DOM04` (financiero/inventario).
- Pantalla `/admin/auditoria` con filtros combinables: texto, usuario, paciente/expediente
  (nombre, apellido, CI, `COD_PAC` o `NRO_HIS`), rol, acción, criticidad, dominio y rango de fechas.
- Exportación a **Excel** (`.xlsx`) y **PDF** (A4 horizontal, máximo 2000 filas) respetando los filtros activos.
  Cada exportación queda auditada (`Exportar Excel` / `Exportar PDF`, criticidad Media, `DOM02`).
- Seguridad (`DOM02`): inicios de sesión, intentos fallidos, cierres de sesión, intentos sobre
  cuentas bloqueadas y bloqueo automático (criticidad `Crítica`).
- Los eventos anteriores al soporte de paciente muestran "—" en la columna Paciente
  (la tabla es inmutable, no se corrigen).

## Auditoría: cómo registrar eventos (helper `Auditoria`)

Todo evento importante se guarda en la tabla `AUDITORIA` con el helper `App\Services\Auditoria`.

```php
use App\Services\Auditoria;

Auditoria::registrar(
    string $accion,        // qué pasó, máx. 20 caracteres (ej. 'Actualizar', 'Login', 'Login fallido')
    string $criticidad,    // 'Baja' | 'Media' | 'Alta' | 'Crítica'
    string $dominio,       // 'DOM01' | 'DOM02' | 'DOM03' | 'DOM04'
    string $entidad,       // tabla afectada, máx. 30 (ej. 'USUARIO')
    string $registro,      // código del registro afectado, máx. 15 (ej. 'USR00005')
    string $detalle,       // descripción legible, se corta a 255
    ?object $actor = null, // quién lo hizo; si es null se usa Auth::user()
    ?string $codPac = null // paciente afectado (COD_PAC); si es null se usa $actor->COD_PAC si existe
);
```

**Qué completa solo el helper:** `COD_AUD` (correlativo `AUD00000001`), `FEC_AUD`, `IP_AUD`,
`DIS_AUD` (navegador, cortado a 60), `COD_USR` y `COD_ROL` del actor, y `COD_PAC`
(`$codPac` si lo pasas; si no, `$actor->COD_PAC` cuando existe).

**Dominios**

| Código | Dominio | Se usa para |
|--------|---------|-------------|
| DOM01 | Usuarios | Crear, editar, bloquear/desbloquear usuarios |
| DOM02 | Seguridad | Login, intentos fallidos, logout, bloqueos, exportaciones |
| DOM03 | Clínico | Laboratorio y despachos de Farmacia |
| DOM04 | Financiero | Inventario: entradas y bajas de lote |

**Criticidades**

| Criticidad | Cuándo usarla | Ejemplo |
|------------|---------------|---------|
| Baja | Acción de rutina | Login correcto, logout |
| Media | Algo que conviene revisar | Contraseña incorrecta, intento sobre cuenta bloqueada, exportar reportes |
| Alta | Cambio importante | Cambio de rol, baja de lote |
| Crítica | Seguridad grave | Bloqueo automático por intentos fallidos |

**Ejemplos de uso**

```php
// Dentro de un servicio o controlador con usuario autenticado
Auditoria::registrar(
    'Actualizar', 'Alta', 'DOM01', 'USUARIO', $usuario->COD_USR,
    "Cambio de rol de {$usuario->USR_USR} a {$nuevoRol}"
);

// Cuando todavía no hay sesión (ej. login fallido): pasar el actor a mano
Auditoria::registrar(
    'Login fallido', 'Media', 'DOM02', 'USUARIO', $user->COD_USR,
    "Contraseña incorrecta para {$user->USR_USR}",
    $user
);

// Acción que afecta a un paciente distinto del actor: pasar codPac con argumento con nombre
Auditoria::registrar(
    'Despachar', 'Media', 'DOM03', 'DETALLE_RECETA', $item->COD_DRE,
    "Despacho de {$medicamento}",
    codPac: $codPac
);
```

**Reglas**

- `COD_USR` es obligatorio (FK a `USUARIO`), por eso un login con un usuario que no existe no se
  puede guardar en `AUDITORIA`; ese caso solo va a `storage/logs`.
- En el logout, auditar **antes** de `Auth::logout()`; después `Auth::user()` ya es `null`.
- Nunca poner contraseñas ni datos sensibles en `$detalle`.
- Los textos con tilde (`'Crítica'`) deben guardarse en UTF-8.
- Pasar `codPac` cuando la acción afecta a un paciente distinto del actor (despacho de recetas,
  resultados de laboratorio). Inventario, usuarios, empleados y login **no** llevan paciente.
- `codPac` se pasa con argumento con nombre (`codPac: ...`) para no confundirlo con el actor.
- `AUDITORIA` es inmutable: no se editan ni se borran eventos (el usuario `cies_app` no tiene
  permisos de `UPDATE` ni `DELETE` sobre esa tabla).

## Pendiente

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
| Puerto 8001, 8081 o 3307 ocupado | Apaga lo que lo usa, o cambia el puerto izquierdo en `docker-compose.yml` |
| `container name ... already in use` | Hay contenedores viejos: `docker compose down` y vuelve a levantar |
| `Check constraint ... violated` | El SQL se guardó mal (debe ser UTF-8): corrige y haz `docker compose down -v` |
| La app no conecta a la BD | Mira `docker compose logs mysql`; espera a que MySQL termine de iniciar |
| El contenedor `app` dice "No se pudo leer la base de datos" tras un `git pull` | Tu volumen es anterior al usuario `cies_app`: `docker compose down -v` y levanta de nuevo |
| `Access denied for user 'cies_app'` | Igual que arriba, o falta `DB_USERNAME`/`DB_PASSWORD` en tu `.env` (copia los de `.env.example`) |
| Falta una tabla o columna (por ejemplo `LOTE_MEDICAMENTO` o `CAMCON_USR`) | Tu BD es de una versión anterior: `docker compose down -v` y levanta de nuevo |
| Los paneles de Laboratorio o Farmacia salen vacíos | Falta cargar los datos clínicos de prueba (ver sección Base de datos) |
| `Class "OpenSpout\..."` o `Class "Dompdf\..."` not found | Faltan dependencias de Composer: `docker compose up -d` (Docker) o `composer install` (PHP local) |