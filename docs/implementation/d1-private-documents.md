# Entrega D1 — Documentos y versiones privadas

Fecha: 21 de noviembre de 2026. Base: 1c4a088, con P1 integrada mediante [PR #10](https://github.com/san7imo/ascanio-plataform/pull/10). Rama: feat/private-documents. Núcleo técnico desarrollado con fixtures sintéticos; O0/O1 y la habilitación operativa de archivos reales siguen pendientes.

## Recorrido y límites

El workspace autorizado dispone de un listado de documentos con búsqueda por título y paginación, detalle e historial de versiones. Crear un documento recibe su primera versión; añadir otra conserva todos los originales anteriores. Cada versión fija número, archivo, nombre normalizado, MIME determinado por el servidor, bytes, SHA-256 y actor. No hay edición del archivo, borrado, purga, visor inline, generación contractual o firma.

El perfil técnico inicial admite PDF y texto con extensión coincidente, comprobados mediante fileinfo sobre los bytes recibidos. Los límites por archivo, tenant y conjunto de archivos gestionados se configuran explícitamente; sus defaults son cero y DOCUMENT_UPLOADS_ENABLED=false. Las pruebas usan archivos pequeños y límites sintéticos; no se presupone ese presupuesto para Kroma ni se admiten archivos musicales/Office por extensión arbitraria.

La interfaz y backend permanecen cerrados para cargas reales. CapacityProbe y MalwareScanner se vinculan por defecto a implementaciones no disponibles. Activar solo la variable de entorno no evita esas denegaciones: hacen falta adaptadores reales revisados y límites O1 medidos. SyntheticDocumentEnvironment vive exclusivamente en tests, exige APP_ENV=testing, PostgreSQL ascanio_test y DB_URL vacío; no hay selector HTTP/configuración de producción que active el simulador.

## Acceso y custodia

| Capacidad | Alcance actual |
|---|---|
| documents.view | Listado, título/descripción y metadata de versiones del tenant: nombre, MIME, tamaño, checksum, fecha y estado |
| documents.upload | Con documents.view, cargar versiones y solicitar de nuevo un análisis fallido |
| documents.download | Con documents.view, descargar una versión clean de ese tenant tras reautorizar y verificar sus bytes |

Se exige cuenta activa/verificada, membership y tenant.view vigentes. Las tres capacidades se agregan al bundle inicial protegido; los roles ordinarios no reciben concesiones automáticas. No hay bypass de propietario ni permisos derivados de profesión, representación o derechos. assigned_resources continúa cerrado sin una asignación soportada. G1 añadirá acceso externo y grants a DocumentVersion con FKs reales; D1 solo tiene acceso de equipo.

El servidor deriva tenant/actor y rutas; no confía en storage_path, estado, checksum, tamaño o MIME enviados por el cliente. Resuelve workspace antes de recuperar documentos y limita cada versión por tenant/documento/ID. Búsquedas, conteos, paginación y proyecciones usan el mismo límite. La metadata no incluye ruta física, claves de reintento, membership/epoch ni datos de cuentas. Una URL firmada no concede descarga: el endpoint autenticado vuelve a aplicar policy y estado.

El disco documents usa exclusivamente driver local, raíz privada configurable y rutas relativas tenants/{tenant}/documents/{document}/versions/{uuid}/original. La raíz debe quedar fuera de los web roots conocidos; O1 debe inventariar también los de otros proyectos de la cuenta. El disco local del scaffold y documents tienen serve=false. No se generan temporaryUrl ni enlaces públicos, no se enlaza storage privado y no se requiere S3. Los modos privados configurados son archivos 0600 y directorios 0700, sujetos a verificación real de permisos en Kroma.

Las descargas envían attachment, application/octet-stream, nosniff y private/no-store; las páginas de metadata también usan private/no-store. Se verifica una copia temporal acotada a tamaño registrado+1 y SHA-256 antes de enviar bytes. Si el original falta o cambió, se bloquea la versión y se audita la incidencia. Se autoriza y audita el inicio de entrega, sin afirmar recepción completa por el cliente ni validez legal del archivo.

## Transacción, capacidad e idempotencia

UploadDocumentVersion posee su propio commit y rechaza una transacción envolvente. Valida, reautoriza bajo locks, registra la versión, escribe un original nuevo y guarda auditoría antes del commit. No se reintenta automáticamente el callback con efectos filesystem. Si falla, se elimina únicamente la ruta nueva cuando se confirma que la versión no quedó registrada. Si no puede confirmarse/limpiarse, se conserva el archivo privado y se registra cleanup pending por IDs para reconciliación; no se purga evidencia existente.

Un upload_key aleatorio queda como hash por tenant/actor. Repetir el mismo formulario/contenido devuelve la versión existente; cambiar sus datos con la misma clave falla. Una nueva versión exige el contador que abrió el usuario. La carrera de dos appends sobre v1 produce un solo v2; no sobrescribe el original ni asigna dos veces el número. Los formularios preservan archivo/cambios ante conflicto y requieren recargar/revisar.

La admisión comprueba límite de archivo, bytes gestionados del tenant y total gestionado por la aplicación, capacidad de cuenta fresca, reservas e inodos. Incluye versiones en cuarentena, rechazadas y fallidas; no libera capacidad mediante cambios de estado. La suma global de size_bytes es una excepción documentada de infraestructura para un único presupuesto compartido: se ejecuta solo en admisión autorizada, no devuelve filas/identidades ni métricas de otros tenants al producto.

El presupuesto se protege mediante un advisory lock PostgreSQL de transacción, namespace ASC/DOC, bajo READ COMMITTED y lock_timeout local de cinco segundos; después se bloquean cuenta/workspace/documento. El lock dura hasta commit/rollback y no depende del vencimiento de una lease de caché. Se ejecutó una carrera de dos cuentas y tenants distintos sobre ese presupuesto. La semántica de liberación transaccional está descrita en [PostgreSQL 17](https://www.postgresql.org/docs/17/explicit-locking.html#ADVISORY-LOCKS).

CapacitySnapshot debe medir la cuota/inodos reales de cuenta, incluidos otros sitios/correo, con timestamp válido de hasta sesenta segundos. No se sustituye por df ni se reutilizan los valores de octubre como margen actual. El presupuesto gestionado cuenta originales registrados; DB, logs, temporales, copias y posibles huérfanos necesitan el presupuesto operativo/reservas O1. Otros procesos de la cuenta no participan en este lock: el probe, reservas y manejo de errores no prometen espacio físico infinito.

## Cuarentena, jobs y reintentos

El original permanece en una ruta privada única; la cuarentena se impone por estado/policy, sin duplicar/mover el archivo para liberarlo. Los estados son quarantined, clean, infected, scan_failed y blocked. Solo clean permite descarga; metadata visible o un porcentaje de progreso no lo sustituye.

El escaneo se despacha después del commit mediante un job cifrado con tenant, versión, solicitante y generación explícitos. Un rollback no despacha. Si no se puede encolar, la versión queda scan_failed/queue_unavailable y conserva su original. El job revalida cuenta, membership/epoch y documents.upload; cambios de autoridad lo bloquean. Limpia contexto al comenzar/terminar, también en error; el worker compartido conserva los hooks F4.

El escáner recibe una copia verificada de los bytes exactos. Se vuelve a comprobar el original tras un resultado clean. Infección, resultado desconocido, ausencia/error del escáner, archivo ausente o checksum alterado no liberan el archivo. Los errores de proveedor se sustituyen por un fallo acotado, sin mensaje original ni cuerpo del documento en failed_jobs. Hay hasta tres intentos, backoff y timeout de 45 segundos; O1 debe comprobar límites reales del adaptador/worker.

Reintentar desde la aplicación solo admite scan_failed, reautoriza al solicitante actual y crea una generación nueva con su membership/epoch. Los jobs/callbacks de generaciones anteriores no modifican la nueva. clean/infected/blocked no se reabren por este recorrido; un bloqueo o rechazo requiere otra versión o revisión operativa, conservando evidencia. No existe un motor genérico de callbacks/inbox ni proveedor de firma simulado.

## Esquema y operación

La migración aditiva 2026_11_21_000001 crea documents y document_versions con tenant_id obligatorio, FKs compuestas y unicidad tenant/documento/número. Los campos originales de versión son inmutables; triggers rechazan cambios de payload, DELETE/TRUNCATE y reaperturas indebidas. Runtime tiene SELECT/INSERT y UPDATE solo de las columnas de escaneo, sin modificar checksum/ruta/original ni desactivar guards. No ofrece protección absoluta frente al administrador de infraestructura.

El backfill del bundle registra access.documents.upgraded como migration, sin actor humano inventado, y aumenta access_version de las memberships afectadas. Las invitaciones anteriores basadas en esa autoridad requieren revisión/propuesta nueva. Se preservan los eventos históricos; los roles ordinarios no se amplían. Pausar escrituras/workers durante un upgrade autorizado y aplicar los [grants runtime](../operations/postgresql-runtime-role.md) después de migrar. El rollback solo admite estado prístino; rechaza historia, backfill y concesiones/propuestas que la perderían. No se modifica historia F1–P1.

La [preparación O1](../operations/documents-readiness.md) conserva las decisiones pendientes. El usuario confirmó disponibilidad general de capacidad/almacenamiento el 21 de noviembre; no se aportaron cuota/inodos actuales, ruta privada persistente, escáner, retención/cifrado o recuperación. No hubo conexión a Kroma ni habilitación de cargas reales. No hay dependencias nuevas, almacenamiento externo, datos reales o código Dilo reutilizado.

## Verificación

La regresión ejecutada incluye HTTP/actions A/B, metadata sin descarga incluso con URL firmada, archivo alterado/ausente, infección y fallos sintéticos, reintentos/generaciones, rollback, idempotencia, perfiles MIME/tamaño, capacidad/reservas/frescura, FKs y versiones congeladas. Se prueban commits visibles desde una conexión independiente, un worker Redis real con dos tenants/contexto/revocación, runtime PostgreSQL restringido y carreras de append/presupuesto con procesos independientes.

Verificación ejecutada:

- composer run ci:check con PHP 8.4: 165 pruebas / 1801 aserciones sobre PostgreSQL/Redis; Pint, lint, formato, tipos, tooling y auditorías Composer/npm sin avisos.
- npm run build con PHP_BINARY=php8.4; Composer validate --strict y check-platform-reqs aprobados.
- Chrome a 1365/390 px: gate cerrado, carga sintética en cuarentena, worker Redis, descarga exacta, versiones sin sobrescritura, PDF aceptado, rechazo/fallo/reintento y permisos distintos A/B. Siete capturas inspeccionadas; selector visible en español, sin overflow horizontal ni excepciones JavaScript. El harness de loopback usa exclusivamente testing/ascanio_test y adaptadores sintéticos.
- Markdown/enlaces locales y git diff --check revisados; consultar CI del PR para PHP 8.3/8.4.

La migración se aplicó solo a ascanio local tras verificar APP_ENV=local, PostgreSQL 127.0.0.1:55432, base ascanio, ausencia de DB_URL efectiva y cargas reales deshabilitadas. Los archivos, cola, perfiles y credenciales sintéticos se retiran y ascanio_test se reconstruye al terminar. No hubo conexión remota, envíos a personas reales ni activación del módulo para archivos reales. La operación continúa pendiente de O0/O1; la siguiente entrega local, tras integrar este núcleo, es G1: grants tipados a DocumentVersion con fixtures, sin habilitar por ello archivos reales.
