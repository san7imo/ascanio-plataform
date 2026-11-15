# Entrega F4 — Cierre técnico de Foundation

Fecha: 15 de noviembre de 2026. Base: d894210, con F3 integrada mediante [PR #8](https://github.com/san7imo/ascanio-plataform/pull/8). Rama: feat/foundation-readiness. F1–F4 quedan comprobadas técnicamente para sus entidades actuales. El cierre operativo del hito 1 sigue pendiente de O0/Kroma y de ensayar los procedimientos en un entorno autorizado.

## Comportamiento entregado

AppServiceProvider limpia TenantContext antes/después de cada job, al producirse una excepción, al agotarse intentos y entre iteraciones del worker. Cada callback resuelve la instancia scoped vigente; no captura una instancia anterior al reinicio del scope. Los jobs de invitaciones conservan además su limpieza propia mediante finally y su revalidación transaccional.

La prueba con un daemon Redis real encadena tareas de A y B, una delegación revocada y correo global de cuenta. Una tarea sintética deja deliberadamente contexto abierto y otra falla después de establecerlo. Todos los jobs corren en el mismo PID; los observadores comprueban contexto vacío antes/después y en error/fallo. Se simula únicamente el transporte de correo, manteniendo Redis, commits, consultas y deserialización reales.

Esta prueba detectó que el cambio de access_version lanzaba una excepción HTTP que el job interpretaba como fallo de entrega. InvitationAuthority usa ahora AuthorizationException para esa denegación: el job registra blocked/authority_changed sin enviar ni reintentar como si fallara SMTP. La aceptación sigue denegada y las memberships independientes se conservan. Un segundo recorrido fuerza fallo del proveedor y agotamiento, comprueba el error saneado, el comando cifrado en failed_jobs y la ejecución limpia de la tarea siguiente. Solo el fixture reduce tries a uno; producción conserva tres intentos.

Verificación y recuperación de cuenta pasan por SendAccountEmail, encolado después del commit y cifrado mediante ShouldBeEncrypted. Conserva User explícito, propósito y hash del correo esperado; el reset token queda dentro del comando cifrado. Antes del envío comprueba cuenta activa, destinatario sin cambios y, según propósito, correo pendiente de verificar o reset token todavía válido. Estos son trabajos globales de autenticación: no requieren membership ni eligen un tenant. Siguen usando las notificaciones y protocolos estándar de Laravel/Fortify.

El transporte admite SMTP y array sintético; log se rechaza para no escribir enlaces secretos. Las excepciones del proveedor se sustituyen por un error operativo acotado sin conservar la excepción original. Un rollback no despacha mensajes. No se promete entrega exactamente una vez: un fallo posterior a la aceptación SMTP puede duplicar correo. El usuario puede solicitar otro enlace si caducó durante la espera. Los jobs fallidos se gestionan con acceso operativo restringido, sin inspeccionar ni publicar tokens.

Las pantallas existentes de seguridad, confirmación, recuperación, verificación, perfil, apariencia y componentes accesibles quedan en español. lang/es y lang/es.json cubren validación utilizada, autenticación y notificaciones estándar. La configuración predeterminada usa es con fallback en; no se cambia el protocolo de los enlaces de cuenta. Las páginas de error públicas muestran mensajes españoles acotados, sin detalles de excepciones. El header alternativo elimina enlaces del scaffold y la búsqueda sin función.

El idioma en/en_US o pt guardado como preferencia de workspace no implica que exista ya una interfaz completa traducida a esos idiomas. La aplicación continúa almacenando timestamps en UTC y proyecta fechas de memberships/invitaciones en la zona del workspace mediante sus consultas existentes.

## Matriz de cierre para entidades existentes

| Superficie | Evidencia ejecutada | Límite |
|---|---|---|
| Workspace, memberships, roles, asignaciones | Suite F1/F2: consulta, búsqueda/conteo/paginación, binding y mutaciones A/B; roles distintos para una misma cuenta; revocación inmediata | Sin catálogo ni directorio global privado |
| Delegación y gobierno | MFA fresca, no escalamiento, último propietario, transferencia y cinco carreras F1/F2 con procesos independientes | Gobierno no equivale a representación legal ni firma |
| Invitaciones | Destinatario exacto, expiración/replay, rotación, retirada/restauración de autoridad, snapshots, aceptación atómica y dos carreras F3 | Solo equipo; grants a recursos futuros siguen pendientes |
| Worker compartido | FoundationWorkerTest: mismo daemon/PID, A/B, revocación, correo global y contextos limpios en éxito/excepción/fallo | Transporte sintético; no supervisión real Kroma |
| Correo después del commit | InvitationDeliveryTest y AccountDeliveryTest: commits reales, rollback sin envío, fallo de encolado/reenvío, tokens obsoletos/cuenta inválida | Sin garantía de llegada a buzón ni exactamente una entrega externa |
| Secretos de correo | Redis y failed_jobs cifrados; log bloqueado; fallos de proveedor saneados; pruebas de props/auditoría F3 | Logs de proxy/webserver y acceso operativo requieren O0 |
| Auditoría PostgreSQL | Suite runtime con login efímero restringido; historial protegido, catálogos sin escritura y trazabilidad de migración | Sin RLS ni privilegios Kroma verificados |
| UX y Fortify | Regresión de cuenta/MFA y pruebas de errores/mails españoles; Chrome a 1365 y 390 px | Solo superficies actuales, sin afirmar traducción completa en/pt |

Los datos privados de negocio todavía no se almacenan en caché ni archivos; las autorizaciones se consultan de nuevo. No se añade una infraestructura vacía de caches/locks/storage por tenant. Los jobs privados declaran tenant/solicitante/invitación y su displayName incluye tenant; la cola compartida es transporte, no fuente de autoridad. Rate limits de cuenta y la señal global queue:restart son controles de infraestructura deliberadamente globales. Los nuevos caches/locks/archivos privados deberán incluir tenant y la proyección/usuario cuando corresponda, con pruebas propias.

No hay descargas, exports, catálogo, derechos, contratos o CWR implementados. Esta matriz no acredita el aislamiento de esas superficies futuras. Tampoco se habilitan uploads ni URLs públicas para archivos privados.

## Verificaciones ejecutadas

- composer run ci:check con PHP 8.4: 127 pruebas / 1218 aserciones sobre PostgreSQL y Redis; Pint, lint, Prettier, tipos, tooling y auditorías Composer/npm sin avisos.
- npm run build con PHP_BINARY=php8.4; Composer validate --strict y check-platform-reqs aprobados.
- Chrome: login MFA con TOTP, alternancia a recuperación, confirmación de contraseña, seguridad/códigos, configuración MFA con QR cargado, perfil, apariencia móvil, error 404 y solicitudes de recuperación/verificación. Nueve capturas; inspección visual y comprobación de ausencia de overflow horizontal/excepciones JavaScript.
- Revisión de enlaces locales, Markdown y git diff --check. Consultar los checks del PR para la ejecución remota en PHP 8.3/8.4.

No hay dependencias ni migraciones nuevas, ni reescritura de migraciones existentes. No se reutiliza código de Dilo. Los fixtures usan exclusivamente ascanio_test y example.invalid; el esquema dedicado se reconstruye y se retiran credenciales/perfiles de navegador al terminar. Sin conexión a Kroma ni envíos a personas reales.

## Continuación

La [guía operativa](../operations/foundation-readiness.md) identifica las puertas pendientes y el procedimiento que debe ensayarse antes de staging. O0 no está resuelto; no se declara cerrado operativamente Foundation. P1 puede prepararse como siguiente entrega local tras integrar F4; D1 necesita además O1, y los uploads reales continúan deshabilitados. Un plan de módulos posteriores no autoriza a implementarlos todos en esta rama.
