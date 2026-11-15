# Entrega F2 — Gobierno del workspace

Fecha: 15 de noviembre de 2026. Base: 4f0187a. Rama: feat/workspace-governance-f2.
Estado: implementación completa; consultar la validación de esta entrega y los checks de su PR. La separación de privilegios se comprueba localmente, sin acreditar compatibilidad operativa en Kroma.

## Comportamiento

Roles y capacidades añade creación, búsqueda/paginación, edición y eliminación de bundles privados. Membresías añade reemplazo de asignaciones con scope, suspensión/reactivación/revocación y transferencia del gobierno a un miembro existente. Las pantallas nuevas están en español. No existe alta manual de terceros ni invitaciones: F3 incorpora ese recorrido; las pruebas usan fixtures sintéticos.

Cada operación deriva el workspace de una ruta autorizada. WorkspaceAccess comparte predicados entre policies, consultas y delegación; las acciones vuelven a autorizar dentro de la transacción, con cuentas y workspace bloqueados. Se rechazan targets de otro tenant y campos de gobierno enviados por el cliente. Las consultas incluyen tenant incluso para pivots, counts, búsquedas, paginación y proyecciones. No hay bypass por nombre de rol ni por ser administrador de plataforma.

El catálogo global contiene siete capacidades; los roles/asignaciones son privados:

| Capacidad | Condición y límite |
|---|---|
| tenant.view | Membership/cuenta/workspace activos y scope tenant |
| tenant.settings.update | Ajustes mínimos del mismo workspace; conserva el recorrido F1 |
| tenant.members.view | Lista local sin emails, identidades globales ni secretos |
| tenant.members.revoke | Gestionar estados dentro de autoridad delegable; solo un propietario puede cambiar una membership propietaria |
| tenant.roles.manage | Administrar roles ordinarios dentro de las capacidades delegables |
| tenant.members.assign_roles | Reemplazar asignaciones ordinarias, sin modificar gobierno |
| tenant.ownership.transfer | Propietario vigente además de capability; no delegable por roles ordinarios |

Tener una capacidad no permite delegarla. role_permissions.is_delegable lo declara explícitamente. Cambiar/eliminar un rol o retirar asignaciones comprueba sus capacidades anteriores y las nuevas; reactivar una membership también comprueba las capacidades que recuperaría. Esto evita escalamiento por retirada parcial, sustitución o reactivación. La UI muestra las decisiones del servidor, que vuelve a comprobarlas al escribir.

El scope assigned_resources no concede acceso global. Sin objetivos/asignaciones reales autorizados sigue denegando. F2 no crea recursos musicales ni grants genéricos para hacerlo funcionar.

## Confirmación y revocación

Crear/editar/eliminar roles, asignar roles, cambiar estados y transferir requieren contraseña actual y MFA TOTP confirmado. Un paso TOTP consumido por una mutación de gobierno no se reutiliza en otra, aunque sea en otro workspace o sesión. La cuenta se bloquea y conserva last_sensitive_totp_step. Los códigos se verifican con la biblioteca ya utilizada por Fortify; no se añade un proveedor ni una dependencia.

La confirmación y su consumo ocurren dentro de la misma transacción que la mutación y la auditoría. Un fallo revierte los tres. Se acepta una ventana TOTP de un paso a cada lado para desfase del reloj, conservando monotonía; un código de un paso futuro consumido exige esperar uno posterior. Los códigos de recuperación de login no sustituyen esta confirmación de gobierno. Fortify mantiene su recorrido independiente de login/recuperación/MFA.

Diez intentos por minuto y cuenta limitan conjuntamente los endpoints sensibles, incluyendo varios workspaces. Los formularios borran contraseña/código al terminar la petición; el handler no los flashea en old input. Secretos y contador no aparecen en props ni auditoría.

No hay caché de permisos: cada petición consulta autoridad vigente. Cambiar roles/scopes o suspender/revocar elimina ese camino en las demás sesiones inmediatamente, preservando membresías independientes. No se cierra toda la cuenta por retirar un workspace. Una operación que retira la propia autoridad redirige al selector, evitando una página que ya no puede abrir.

## Propietario y concurrencia

Membership.is_owner sigue siendo un hecho de gobierno, separado de roles profesionales y de propiedad musical. El bundle con system_kind=owner queda protegido del editor/asignador ordinario. Solo la transferencia cambia ese gobierno en la aplicación.

El destinatario debe ser otra membership activa del mismo workspace, no propietaria, cuya cuenta esté activa, tenga correo verificado y MFA confirmado. Se bloquean ambas cuentas en orden determinista y después el tenant; se revalida dentro de la transacción. La transferencia añade al destinatario el bundle protegido, cambia ambos hechos de ownership y retira únicamente ese bundle del anterior. Los roles independientes permanecen. Tenant.operator_party_id no cambia y no se declara titularidad ni representación legal.

Suspensión/revocación y las restricciones PostgreSQL mantienen al menos un propietario activo; el borrado de cuenta con historial sigue bloqueado. Las carreras con conexiones/procesos independientes cubren transferencias simultáneas y transferencia frente a revocación del destinatario, además de las carreras F1. PostgreSQL puede abortar un conflicto de locks; las acciones reintentan la transacción hasta tres veces y vuelven a comprobar autoridad.

## Migración y auditoría

La migración aditiva 2026_11_15_000001_add_workspace_governance añade system_kind a roles, is_delegable a sus capacidades y el contador de confirmaciones a User. No modifica la migración compartida de F1.

El catálogo se prepara antes de las escrituras runtime. El upgrade exige pausar escrituras y workers F1 durante el cambio de versión: no dejar procesos antiguos creando workspaces después del backfill. En un upgrade de F1, la migración identifica su bundle inicial, comprueba que no esté asignado a no propietarios, lo protege y aplica las siete capacidades a las memberships propietarias. El recorrido global se limita a esta migración administrativa, está documentado en el código y probado con dos tenants. Un mapping inesperado falla atómicamente y requiere revisión; no se adivinan nuevos propietarios.

Los eventos conservan actor_type=user por defecto con actor_id obligatorio. El backfill añade un evento por workspace, actor_type=migration y actor_id nulo, con fuente/capacidades sin inventar un actor humano. Un CHECK exige la correspondencia. No se cambia el contenido de eventos existentes. Nuevos eventos registran acciones de roles, asignaciones, estados y transferencia con IDs/códigos y diferencias redactadas; sin nombres, emails, passwords, códigos ni secretos MFA.

El rollback sobre un upgrade que haya registrado evidencia de migración se rechaza: usar una corrección hacia delante o un procedimiento de recuperación autorizado que preserve esa historia. En un esquema limpio sin esa evidencia se comprobó rollback/reaplicación de F2 y F1. El orden inválido del rollback total del scaffold histórico (users antes de sessions) permanece registrado; no usar ese rollback como recuperación de despliegue.

Antes de usar la UI en desarrollo, aplicar artisan migrate con PHP compatible y conexión local comprobada. No implica migración de producción. El [procedimiento de privilegios](../operations/postgresql-runtime-role.md) distingue runtime de migración. Los privilegios del dueño de las bases locales no son configuración de producción. La migración F2 se aplicó en este equipo tras comprobar APP_ENV=local, PostgreSQL en 127.0.0.1:55432, base ascanio y ausencia de DB_URL externa. Los servicios Compose utilizados para la entrega se detienen conservando volúmenes al finalizar.

## Verificación y límites

La regresión incluye los recorridos F1 y los casos nuevos: roles distintos por tenant, targets/campos manipulados, delegación restringida incluso al retirar/reactivar, propietario protegido, MFA ausente/no confirmado/incorrecto/reutilizado, rate limit, rollback de mutación/consumo/auditoría, transferencia inválida y conservación de roles independientes.

La prueba de privilegios crea un LOGIN efímero con contraseña aleatoria y se conecta con él, sin SET ROLE desde una sesión privilegiada. Verifica identidad de sesión, ausencia de ownership/superuser/BYPASSRLS/membresía del migrador y capacidad real de operar la aplicación y añadir eventos. UPDATE/DELETE/TRUNCATE, ALTER/DROP, cambio a rol privilegiado, session_replication_role e inserción de un actor de migración se rechazan por permisos. La limpieza elimina únicamente ese login y sus grants en ascanio_test. No supone inmutabilidad frente al administrador de infraestructura.

La revisión Chrome con datos sintéticos comprobó login con MFA, alta de rol, asignación con scope assigned_resources, transferencia, pérdida de acceso del anterior propietario y entrada del destinatario. Se inspeccionaron capturas de roles en escritorio y membresías a 390 px, sin overflow horizontal ni excepciones JavaScript. El servidor de revisión y Chrome se cerraron; las credenciales sintéticas temporales se eliminaron. No se añadieron dependencias de navegador al proyecto.

Se ejecutó composer run ci:check con PHP 8.4: 91 pruebas / 776 aserciones, Pint, lint, formato, tipos, una prueba de tooling y auditorías Composer/npm aprobados sin avisos. Build Vite, Composer validate --strict y check-platform-reqs aprobados. Tras completar los mensajes de validación en español se repitió la suite de gobierno. Enlaces locales, Markdown y git diff --check se revisan para esta entrega. Los checks remotos del PR comprueban además PHP 8.3/8.4. Kroma, SMTP real, invitaciones, resource grants, documentos, firma y CWR siguen pendientes. No se reutilizó código de Dilo ni se añadieron paquetes. Foundation aún requiere F3/F4 y O0; la siguiente entrega es F3.
