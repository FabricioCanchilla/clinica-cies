# Clínica CIES

Sistema web para la Clínica CIES La Paz (Av. Tejada Sorzano #393, Miraflores, La Paz, Bolivia):
citas con anticipo en línea, atención médica, laboratorio, farmacia, enfermería e internación,
caja, auditoría, reportes con gráficos y planificación de la capacidad de camas.

Proyecto de Programación 3 e Investigación Operativa II, UNIFRANZ.

**Stack:** Laravel 13 · PHP 8.4 · MySQL 8.4 · Quarkus 3.15 (Java 21: OTP y comprobantes PDF) · Mailpit · Docker.

## Integrantes del grupo

| Nombre completo | Rol | Usuario GitHub |
|-----------------|-----|----------------|
| Aranda Silva Alan Cristhofer | Líder del proyecto, desarrollo backend y vistas, panel del médico | [alanhack777](https://github.com/alanhack777) |
| Canchilla Mamani Isaac Fabricio | Docker y base de datos, módulos de usuarios, empleados y auditoría | [FabricioCanchilla](https://github.com/FabricioCanchilla) |
| Quispe Mamani Rosmery | Desarrollo backend y vistas, módulo de pacientes | [rosmeryqm2020-afk](https://github.com/rosmeryqm2020-afk) |
| Copa Pérez Neyda Milena | Documentación, pruebas y módulo de caja | [Milenacopa120-0409](https://github.com/Milenacopa120-0409) |

---

## 1. Correr el sistema (5 pasos)

**Requisitos:** Docker Desktop abierto y Git. No hace falta instalar PHP, Java, Composer ni MySQL.

```bash
git clone https://github.com/FabricioCanchilla/clinica-cies.git
cd clinica-cies
docker compose up --build -d
docker compose logs -f app        # espera el mensaje «Listo» (Ctrl+C para salir del log)
```

Abre **http://localhost:8001** y entra con `admin` / `admin123`.

La primera vez tarda varios minutos: descarga las imágenes, compila Quarkus, instala las
dependencias, crea la base de datos y carga **un año de datos de demostración** (médicos,
pacientes, citas, consultas, pagos e internaciones). No cierres Docker hasta ver `Listo`.

> **Código de verificación (OTP).** El administrador, la caja y el auditor ingresan con un
> segundo factor, y el anticipo de las citas se confirma con OTP. Los correos no salen a
> Internet: llegan a **Mailpit**, en http://localhost:8025. Abre ahí el último correo y copia el código.

### Direcciones

| Servicio | Dirección | Acceso |
|----------|-----------|--------|
| Sistema | http://localhost:8001 | usuarios de la tabla de abajo |
| Correo de pruebas (Mailpit) | http://localhost:8025 | códigos OTP, recuperación de contraseña |
| phpMyAdmin | http://localhost:8081 | root / root123 |
| MySQL | 127.0.0.1:3307 | root / root123 (la app usa `cies_app`) |
| Quarkus (OTP y comprobantes) | http://localhost:8082 | lo usa Laravel internamente |

### Opción B: PHP local (para desarrollar)

Todo lo demás sigue en Docker; solo la app corre con tu PHP 8.4 y Composer.

```powershell
docker compose up -d mysql quarkus mailpit phpmyadmin
composer install
copy .env.example .env      # solo la primera vez
php artisan key:generate    # solo la primera vez
php artisan db:seed         # solo si la BD está vacía
php artisan serve           # http://localhost:8000
```

---

## 2. Usuarios de prueba

La contraseña es siempre **usuario + `123`**.

| Usuario | Rol | Qué ver |
|---------|-----|---------|
| `admin` | Administrador (con OTP) | Dashboard con gráficos, reportes, usuarios, empleados, planificación de camas, auditoría |
| `recepcion` | Recepción | Citas del día, check-in, pacientes, seguros, órdenes de pago |
| `medico` | Médico (Medicina General) | Agenda, atención, recetas, órdenes de examen, estadísticas |
| `enfermeria` | Enfermería | Camas e internación con control de limpieza, insumos de familiares |
| `caja` | Caja (con OTP) | Apertura, cobros por orden de pago, anulaciones, cierre |
| `paciente` | Paciente | Agendar y pagar citas, historial, recetas, resultados, mi reporte |
| `laboratorio` | Laboratorio | Órdenes de examen y resultados |
| `farmacia` | Farmacia | Despacho de recetas y stock por lote (PEPS) |
| `auditor` | Auditor (con OTP) | Auditoría con exportación a Excel y PDF |

**Médicos** (14 especialidades, 17 médicos, cada uno con su horario y consultorio):

| Especialidad | Usuarios |
|--------------|----------|
| Medicina General | `medico`, `pcondori` |
| Ginecología y Obstetricia | `amamani`, `jvargas` |
| Pediatría | `crojas`, `mgutierrez` |
| Psicología | `dsalazar` |
| Odontología | `rparedes` |
| Urología | `faliaga` |
| Medicina Interna | `mrios` |
| Traumatología | `scalle` |
| Cardiología | `garce` |
| Dermatología | `lapaza` |
| Otorrinolaringología | `asinani` |
| Endocrinología | `vmercado` |
| Gastroenterología | `rtapia` |
| Cirugía General | `hblanco` |

Los pacientes de demostración entran con su **CI** como usuario y la clave `Cies` + CI
(por ejemplo `4810235` / `Cies4810235`); el sistema les pide cambiarla al primer ingreso.

Cada rol entra solo a su panel; si abre el de otro rol, el sistema responde 403.

---

## 3. Qué hace el sistema

| Módulo | Funciones principales |
|--------|-----------------------|
| **Público** | Portada y páginas Nosotros, Servicios (médicos, horarios y tarifas), Investigación, Ubicación (red La Paz y mapa) y Contacto con datos de cies.org.bo (`config/clinica.php`); **Agendar cita** lleva al formulario del paciente o al acceso/registro; botón de emergencia y WhatsApp |
| **Paciente** | Agendar cita (el horario queda reservado 15 min), pagar el anticipo con firma y OTP, cancelar y reprogramar, historial, recetas en PDF, resultados, pagos y comprobantes, orden de pago, **mi reporte** |
| **Recepción** | Citas del día y check-in (tolerancia de 15 min), alta presencial de pacientes, validación de seguros, disponibilidad de médicos, consultorios y camas, órdenes de pago |
| **Médico** | Agenda real, atención con diagnóstico **CIE-10 obligatorio**, receta con alerta de alergias, órdenes de examen, **historia clínica completa** (antecedentes, alergias, adendas, signos, resultados), **derivaciones e interconsultas**, **consentimiento informado con firma**, acceso de emergencia auditado, estadísticas |
| **Laboratorio** | Flujo de la orden, carga, validación y publicación de resultados, alerta de valor crítico |
| **Farmacia** | Despacho de recetas ítem por ítem, stock por lote con PEPS, bajas y alertas de vencimiento, **venta en mostrador** o cargo a hospitalizados con recibo PDF y anulación |
| **Enfermería** | Insumos que traen los familiares (firma + OTP + comprobante); camas e internación con limpieza y aislamiento; **pacientes internados**: signos vitales (RN40), dosis con **5 correctos** (pulsera/código), omisiones con incidencia y aviso al médico, notas, **balance hídrico** y **cambio de turno** |
| **Caja** | Apertura y cierre con arqueo, cobro del saldo por **orden de pago**, descuentos con autorización, anulaciones, comprobantes PDF |
| **Administración** | Dashboard con gráficos, **reportes de atención**, reporte por paciente, usuarios, empleados, supervisión de citas, caja e internación |
| **Configuración** | Parámetros (anticipo, inactividad, días de agenda, descuento, limpieza, retención), especialidades, tarifas, precios de farmacia, **matriz de permisos por rol** y **respaldos** con prueba de restauración |
| **Auditoría** | Registro inmutable por dominio y criticidad, filtros, exportación a Excel y PDF, alertas críticas |
| **Planificación de camas (IO II)** | Recomendador de capacidad de camas a partir del historial de ocupación (ver sección 5) |

### Reportes y gráficos
- **Dashboard del administrador:** atenciones del mes frente al mes anterior, pacientes atendidos y nuevos, ingresos,
  asistencia, especialidad más frecuentada, ocupación de camas; gráficos de los últimos 30 días, pacientes que más
  visitan la clínica, atenciones por especialidad, especialidad más frecuentada cada mes, pacientes por médico,
  estado de las citas, ingresos de 12 meses, perfil por sexo y edad, y camas.
- **Reportes de atención** (`/admin/reportes`): todo filtrable por **día, semana, mes, año o rango**, por especialidad
  y por médico. Exporta a Excel (queda auditado) e imprime en PDF.
- **Reporte por paciente:** citas, diagnósticos, recetas, exámenes, pagos, internaciones e insumos, con PDF.
  Lo ven el administrador y recepción; el médico solo el de sus pacientes; el paciente, el suyo.
- **Mis estadísticas del médico:** sus atenciones, pacientes frecuentes, diagnósticos y la demanda por especialidad.

### Camas: reglas de bioseguridad
- Solo se interna en una cama **Disponible** y sin limpieza pendiente.
- Al dar de alta, la cama pasa a **Limpieza** y no se puede usar hasta completar su lista de verificación
  (sábanas, superficies, colchón).
- Si el paciente tenía **aislamiento** (COVID-19, tuberculosis, etc.), la limpieza es **terminal**: además exige
  ventilación y un tiempo mínimo de acción del desinfectante (45 min, `CIES_LIMPIEZA_TERMINAL_MIN`).
- Ropa sucia en una cama libre la saca de servicio; en una cama ocupada genera una tarea de cambio.

### Orden de pago
Número `OP-<código de cita>` con el servicio, el anticipo ya pagado y el saldo. La emiten recepción y
administración, el paciente la ve en sus citas y la caja la busca escribiendo ese número.

---

## 4. Base de datos

No usamos migraciones. MySQL ejecuta solo, la primera vez que se crea el volumen, los scripts de `database/sql/`:

| Script | Contenido |
|--------|-----------|
| `01_estructura.sql` | Las 48 tablas del diccionario de datos |
| `02_usuario_auditoria.sql` | Usuario `cies_app`: no puede modificar ni borrar `AUDITORIA` |
| `03_usuario_quarkus.sql` | Usuario del microservicio Quarkus |
| `04_ajustes_defensa.sql` | AJ07 aislamiento en `INTERNACION` y AJ08 tabla `LIMPIEZA_CAMA` |
| `05_ajustes_v6.sql` | AJ01 estados «En espera»/«En consulta», AJ04 dosis sin insumo, AJ05 consentimiento, derivaciones, notas de enfermería, balance hídrico y parámetros, CIE-10, venta en mostrador y respaldos |

Los datos se cargan con `php artisan db:seed` (lo hace solo el contenedor si la BD está vacía):

| Seeder | Datos |
|--------|-------|
| `DatosInicialesSeeder` | Roles, administrador, dominios de auditoría, métodos de pago, servicios |
| `UsuariosPruebaSeeder` | Un usuario por rol y la paciente de prueba |
| `DatosClinicosPruebaSeeder` | Órdenes de examen, receta y lotes de medicamentos |
| `HistorialInternacionSeeder` | Sala de 10 camas y 3 años de internaciones (planificación de camas) |
| `CatalogosV6Seeder` | Catálogo CIE-10 (y codificación de las consultas de demo), medicamentos con precio y lotes, permisos por rol |
| `DemostracionV6Seeder` | 90 días de exámenes con resultados y valores críticos, recetas, 30 días de ventas en farmacia y cuidados de enfermería de los internados (dosis, signos, balance, turnos) |
| `DemostracionClinicaSeeder` | 14 especialidades, 10 consultorios, 17 médicos con horario, pacientes adultos y niños, un año de citas con consultas y pagos, estados de cama de ejemplo |

**Cada vez que cambie algo en `database/sql/` o en los seeders, recrea la base:**

```bash
docker compose down -v
docker compose up --build -d
```

`down -v` borra la base de datos y todo lo que hayas registrado a mano.

---

## 5. Aporte de Investigación Operativa II

Menú del administrador → **Planificación de camas** (`/admin/planificacion-camas`).

- Lee el historial de ocupación mensual de las camas (tablas `CAMA` e `INTERNACION`) y lo clasifica en estados de demanda.
- Estima la matriz de transición de la cadena de Márkov, proyecta la demanda de los próximos meses, el estado
  estable y los tiempos de retorno.
- Evalúa las alternativas de capacidad con valor esperado, valor esperado de la información perfecta y simulación
  de Montecarlo, y recomienda una decisión que queda auditada.
- El motor está en `app/Support/ModeloCamas.php` y se valida contra los valores del informe con
  `php artisan test --filter=ModeloCamasTest`.

---

## 6. Comandos útiles

```bash
docker compose up -d                 # encender
docker compose down                  # apagar (conserva los datos)
docker compose down -v               # apagar y BORRAR la base de datos
docker compose logs -f app           # ver el log de la aplicación
docker exec -it clinica_cies_app php artisan view:clear     # si una vista no se actualiza
docker exec -it clinica_cies_app php artisan test           # pruebas (incluye FuncionalidadesInformeTest sobre la BD de demo)
docker exec -it clinica_cies_app php artisan respaldo:crear  # copia de seguridad ahora (también a las 02:00)
docker exec -it clinica_cies_app php artisan respaldo:probar # restaura la última copia en clinica_cies_restauracion y verifica
docker exec -it clinica_cies_app php artisan schedule:list   # tareas programadas (el contenedor corre schedule:work)
```

## 7. Flujo de trabajo en equipo

- `main` es la rama estable. Cada tarea va en su rama `feature/...` y entra a `main` por Pull Request.
- Antes de empezar: `git pull origin main`.
- **Nunca** subir el archivo `.env`.

## 8. Problemas comunes

| Problema | Solución |
|----------|----------|
| `Cannot connect to the Docker daemon` | Docker Desktop está apagado: ábrelo y espera a que inicie |
| La página no abre o da error de conexión | Todavía está cargando: mira `docker compose logs -f app` y espera `Listo` |
| Puerto 8001, 8025, 8081, 8082 o 3307 ocupado | Cierra lo que lo usa o cambia el puerto izquierdo en `docker-compose.yml` |
| `container name ... already in use` | `docker compose down` y vuelve a levantar |
| Los gráficos salen vacíos o faltan médicos | La carga de datos no terminó o falló: `docker compose down -v` y `docker compose up --build -d` |
| Falta una tabla o columna | Tu base es de una versión anterior: `docker compose down -v` y levanta de nuevo |
| `Access denied for user 'cies_app'` | Volumen viejo: `docker compose down -v`; con PHP local, revisa `DB_USERNAME`/`DB_PASSWORD` en `.env` |
| No llega el código OTP | Ábrelo en Mailpit (http://localhost:8025) y revisa que el contenedor `clinica_cies_quarkus` esté corriendo |
| `Class "Dompdf\..."` u `OpenSpout` not found | Faltan dependencias: `docker compose up -d` o `composer install` |
| Los gráficos no se dibujan | Chart.js, los íconos y los estilos del panel médico se cargan desde Internet: revisa la conexión |
