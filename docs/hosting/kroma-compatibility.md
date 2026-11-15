# Compatibilidad de Kroma — comprobación de lectura

Fecha: 12 de octubre de 2026. Repositorio actualizado a `64858b8` mediante fast-forward desde `aaf3328`, sin cambios locales pendientes ni sobrescrituras. Se leyeron las instrucciones actualizadas de AGENTS.md y README.md.

## Resultado y alcance

**Compatibilidad del stack de producción sin confirmar; capacidad de cuenta parcialmente documentada.** El usuario identificó la cuenta Kroma y aportó el texto del panel cPanel, pero indicó expresamente que no se realice una conexión directa al servidor. Este informe se limita a esos datos y a lecturas locales del repositorio. No se accedió al panel ni se usaron las URLs de sesión recibidas; sus tokens y los identificadores de acceso no se incluyen en este documento.

No se inició sesión en Kroma ni se ejecutaron comandos remotos, consultas de datos, instalaciones, cambios de configuración, despliegues, restauraciones o borrados. Los resultados locales y de GitHub Actions de la entrega Foundation no son evidencia de capacidades del hosting.

Producción y sus archivos permanecerán en Kroma. El almacenamiento objetivo es local privado, fuera de todos los document roots, persistente entre releases. No se propone contratar servicios externos ni sustituir el stack sin una decisión explícita.

## Matriz de comprobación

| Requisito | Estado de Kroma | Evidencia de lectura necesaria |
|---|---|---|
| PHP web 8.3+ | Sin confirmar | Versión y handler asignados al dominio concreto en MultiPHP/PHP Selector; extensiones y límites efectivos de ese handler. Una versión CLI no prueba la web. |
| PHP CLI 8.3+ | Sin confirmar | Binario permitido, versión (`php -v`), ini (`php --ini`) y módulos (`php -m`). Revisar el binario seleccionado, no solo el predeterminado. |
| Composer 2 | Sin confirmar | Ubicación y versión (`command -v composer`, `composer --version` con el PHP compatible). No ejecutar install/update durante esta inspección. |
| Extensiones | Sin confirmar | PDO PostgreSQL, mbstring, DOM/XML/XMLWriter, fileinfo, OpenSSL, tokenizer, ctype, session y filter; cURL y zip/unzip para herramientas. Predis no exige phpredis. Comprobar web y CLI por separado. |
| PostgreSQL | Sin confirmar | Motor/versión del servidor ofrecido a la cuenta y autorización para crear bases/roles/migrar. `psql --version` solo identifica un cliente. Leer la información del panel o `SELECT version()` en una conexión ya autorizada; sin leer tablas de negocio. |
| Otros motores | Sin confirmar | Listado del panel de MySQL/MariaDB/PostgreSQL y versiones reales del servidor. Su presencia no autoriza sustituir PostgreSQL. |
| Redis | Sin confirmar | Servicio habilitado para la cuenta, endpoint autorizado, versión y límites de memoria/conexiones/persistencia. Un binario redis-cli o redis-server no prueba un servicio accesible. PING/INFO únicamente en el endpoint conocido y autorizado. |
| Cron | Sin confirmar | Función Cron Jobs habilitada, frecuencia mínima y límites del proveedor; `crontab -l` si SSH está disponible. No crear una tarea. No publicar comandos de otros proyectos ni secretos. |
| Workers persistentes | Sin confirmar | Permiso explícito del proveedor para procesos duraderos, límites CPU/memoria/procesos y mecanismo de supervisión/reinicio ya disponible. La presencia de SSH o de un proceso aislado no prueba permiso operativo. |
| Ubicación de aplicación | HOME identificado por el usuario; ubicación admitida sin confirmar | HOME de la cuenta, document roots de todos los dominios/subdominios y permisos para alojar código fuera de ellos, sirviendo únicamente `public/`. No mover ni crear directorios remotos. |
| Almacenamiento privado | Sin confirmar | Ruta privada admitida fuera de todos los web roots, acceso de escritura del PHP web, restricciones open_basedir y persistencia entre releases. La inspección de permisos no sustituye una futura prueba de escritura autorizada. |
| Límites PHP/HTTP | Sin confirmar | Valores web/CLI de upload_max_filesize, post_max_size, memory_limit, max_execution_time, max_input_time, open_basedir y disable_functions; además límites HTTP del servidor/proxy y restricciones CloudLinux/LVE si existen. No crear un phpinfo público. |
| Cuota de disco/inodos | Datos de panel aportados por el usuario; sin lectura directa | Estadísticas de cuenta cPanel, cuota de disco/inodos y consumo de sitios, correo y backups. `quota -s` puede complementar; `df -h` mide filesystem y no prueba cuota de cuenta. |
| Backups | Sin confirmar | Herramienta habilitada, cobertura de archivos privados y BD, frecuencia, retención, ubicación, consumo de cuota y fecha del último backup satisfactorio. No generar ni descargar backups en esta comprobación. |
| Restauración | Sin confirmar | Permiso y procedimiento de restauración (panel o soporte), granularidad, recuperación de BD/archivos privados, último ensayo documentado y tiempos esperados. No ejecutar una restauración. |

## Datos disponibles del panel aportado por el usuario

Estos valores se transcriben del texto compartido en esta conversación el 12 de octubre. Son evidencia aportada por el usuario, no mediciones independientes ni un contrato de capacidad de Ascanio.

| Dato | Valor mostrado | Implicación |
|---|---|---|
| Identificación de cuenta | Usuario, dominio principal, IP compartida y directorio HOME proporcionados | Cuenta identificada; no acredita acceso SSH, puertos ni permiso para procesos persistentes. No se publican aquí datos de conexión. |
| SSL del dominio principal | Active | TLS indicado para el dominio existente; el dominio y certificado de Ascanio aún no están definidos. |
| Disco de cuenta | 23,57 GB / 29,3 GB (80,44%) | Margen aritmético aproximado de 5,73 GB compartido con sitios, correo, temporales y backups; no exclusivo de Ascanio. |
| Memoria física | 197,79 MB / 2 GB (9,66%) | Límite de cuenta mostrado; no es el memory_limit de PHP ni memoria dedicada para Redis/workers. |
| Número de procesos | 26 / 100 | Capacidad y uso instantáneos de cuenta; no prueba permiso para daemons duraderos. |
| Procesos entrantes | 2 / 30 | Límite mostrado de concurrencia entrante; no equivale a 30 workers autorizados. |
| Database Disk Usage | 452,49 MB / 6,17 GB (7,16%) | No identifica motor/versiones ni prueba que esa cuota sea disco adicional al de la cuenta. |
| Bases de datos | 3 / ilimitado mostrado | No acredita PostgreSQL ni permisos DDL o roles. |
| Uso de archivos | 208.488 / ilimitado mostrado | Contador visible sin límite en esta pantalla; pueden existir restricciones de política no mostradas. |
| CPU | 0 / 100 (0%) | Medidor de cuenta; no permite inferir núcleos dedicados ni garantías de CPU sostenida. |
| IOPS | 0 / 2.048 | Límite mostrado; evaluar carga junto con BD y otros sitios. |
| I/O | 0 bytes/s / 48,83 MB/s | Límite mostrado; respaldos y procesamientos compartirían recursos. |
| Banda ancha | 3,79 GB / 292,97 GB (1,29%) | Consumo/cupo mostrado, compartido con la cuenta. |
| Sitios y correo existentes | 4 dominios adicionales, 14 subdominios, 45 cuentas de correo | Hay cargas y datos existentes que deben preservarse; no son una instalación vacía. |

## Información que falta, sin conexión directa

No se solicita una contraseña, clave privada, token cPanel ni acceso SSH. Para completar la comprobación, aportar valores de las siguientes pantallas o una respuesta técnica ya disponible del proveedor, omitiendo URLs de sesión y datos sensibles:

1. **Server Information y MultiPHP Manager/PHP Selector:** sistema/servidor, versiones PHP disponibles, versión/handler del dominio previsto y extensiones habilitadas. Para CLI, una salida ya disponible de `php -v`, `php --ini`, `php -m` y `composer --version`, o confirmación del proveedor con las rutas de los binarios compatibles. No se ejecutarán esos comandos remotamente desde esta sesión.
2. **Bases de datos / información del servidor:** motores disponibles y versión real de servidor de cada uno; confirmar PostgreSQL dentro de Kroma y permisos para crear bases, roles y ejecutar migraciones. El número de bases y la cuota no responden a esto.
3. **Redis y Cron Jobs:** si Redis está habilitado para esta cuenta, versión, endpoint autorizado y restricciones; si cron está disponible y su frecuencia mínima. Puede aportarse respuesta técnica del proveedor sin credenciales.
4. **Workers:** política del proveedor sobre procesos PHP persistentes, reinicio/supervisión permitidos, tiempos máximos y límites de CPU/memoria. La tabla de procesos no confirma esa política.
5. **Domains/document roots y permisos de storage:** listado de raíces públicas (sin contenido de archivos), ubicación admitida para código fuera de ellas, restricciones open_basedir y permisos del PHP web para una ruta privada persistente en el HOME. El directorio principal mostrado no prueba esas condiciones.
6. **MultiPHP INI/PHP Options y límites HTTP:** upload_max_filesize, post_max_size, memory_limit, max_execution_time, max_input_time, open_basedir, disable_functions y límite del servidor/proxy para solicitudes. No crear un phpinfo público para obtenerlos.
7. **Backup/Backup Wizard/JetBackup:** funciones efectivamente disponibles, qué incluyen (BD y archivos privados), fechas de copias satisfactorias, frecuencia, retención, ubicación y cuota consumida; procedimiento de restauración, si lo ejecuta usuario o soporte y último ensayo documentado. No generar ni restaurar una copia durante esta comprobación.
8. **Cuota:** confirmar si los 6,17 GB de BD cuentan dentro de los 29,3 GB totales, límites de inodos no mostrados y qué backups/correos computan. No sumar ambos límites como espacio disponible.

## Ajustes condicionados a la evidencia

- Si existe PHP 8.3+ con las extensiones necesarias, seleccionar en una tarea posterior el binario web/CLI compatible y usarlo también en Composer, cron y workers. La preparación `.local/` del equipo de desarrollo no se supone compatible ni instalable en Kroma.
- Si PostgreSQL o Redis no están habilitados, confirmar primero una opción disponible dentro del servidor/plan Kroma. Una alternativa de colas/caché de Laravel o de motor de BD sería una decisión de stack con impacto y pruebas; no está adoptada por este informe.
- Si no se permiten workers duraderos, evaluar posteriormente un worker acotado invocado por cron dentro de los límites reales, con protección contra solapamientos y reintentos. No configurarlo durante esta inspección ni asumir que sustituye todos los requisitos.
- Validar después la ubicación del código, document root `public/`, almacenamiento privado persistente y descargas autenticadas. No usar enlaces públicos para documentos privados.
- El margen indicado de 5,73 GB y la memoria compartida de 2 GB requieren un presupuesto de capacidad para código, BD, Redis/workers, temporales, logs y backups, antes de habilitar uploads. Definir límites de subida coherentes con HTTP/PHP y reservas usando la cuota real compartida. No liberar espacio borrando datos existentes.
- Antes de datos reales, definir cobertura/retención de backups y un ensayo de restauración en un entorno autorizado. Disponer de un botón de backup no demuestra recuperabilidad.

## Verificaciones locales de esta tarea

- `git fetch origin` y `git merge --ff-only 64858b8`: completados.
- `git status --porcelain=v1`: limpio tras incorporar el commit documental.
- Revisión del diff documental y lectura de AGENTS.md/README.md actualizados.
- Recepción y clasificación del texto cPanel aportado por el usuario; no se realizó conexión directa ni acceso a sus URLs de sesión.
- Revisión de enlaces locales, Markdown y whitespace del informe.
- No se repitieron pruebas de aplicación: esta incorporación y este informe son documentales, sin cambios de código, dependencias ni migraciones.
