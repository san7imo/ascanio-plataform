# Entrega F1 — Primer recorrido multi-tenant

Fecha: 14 de noviembre de 2026. Base: `439208a` (P0). Rama: `feat/workspace-foundation`.
Estado: implementación y validación local completadas; consultar los checks del PR para su validación remota. F1 no cierra Foundation operativo ni confirma Kroma.

## Comportamiento entregado

Una cuenta activa con correo verificado puede abrir Mis espacios, crear varios workspaces y seleccionar uno explícitamente. Cada alta usa una transacción: identidad personal mínima declarada cuando falta, Tenant, membership propietaria, rol inicial, capacidades y eventos de auditoría. Un nonce de formulario emitido por el servidor, hash almacenado y lock de la cuenta evitan duplicación al reenviar/aceptar concurrentemente. Reutilizar la clave con datos distintos se rechaza. Un reenvío antiguo tampoco conserva acceso tras retirar la membership/capability.

El operador inicial es la persona declarada por la propia cuenta, reutilizada solo mediante su FK verificada de cuenta. No se busca ni enlaza una identidad por email, nombre o IPI. Party no almacena bancos, impuestos, nombres legales o contratos. Hay subtipos Person/Organization con integridad, pero no una interfaz para reclamar una organización existente: exige resolver representación. Correo verificado y declaración de cuenta no son KYC ni prueba de titularidad legal.

La ruta identifica el workspace. ResolveWorkspace consulta una membership del usuario con capability tenant.view, estados de cuenta/workspace/membership válidos y scope tenant antes del binding; no carga un recurso de otro tenant para autorizarlo después. El contexto vive en un binding scoped y se limpia en finally, incluido el caso de error. current_workspace_id de sesión es solo una preferencia de navegación; no existe selección implícita del primer workspace. Formularios/headers no deciden tenant, propietario, operador, roles ni estado.

WorkspaceAccess concentra los predicados compartidos por resolución, policies y selector. WorkspaceQueries aplica esos límites a búsqueda, conteos y paginación. El selector es una lectura de descubrimiento acotada a la propia cuenta, aun sin contexto seleccionado; no es un directorio global. No se usa withoutGlobalScopes, una conexión privilegiada ni un bypass de administración. No hay un global scope mágico que haga seguro cualquier Eloquent query futuro: los nuevos recorridos deben pasar por contexto, consulta autorizada y policy.

Los ajustes editables son nombre, idioma preferido y zona horaria. Los campos de gobierno/identidad enviados por el cliente se rechazan o quedan fuera de la allowlist. La UI de F1 es española; guardar en o pt como preferencia no significa traducción completa de la aplicación. Las fechas nuevas se almacenan con timezone en UTC y la membership se muestra según la zona del workspace. Branding con archivos/cargas permanece fuera de alcance.

## Acceso y estados

| Operación | Condición |
|---|---|
| Crear espacio | Cuenta activa y correo verificado; nonce válido del formulario |
| Resolver/consultar espacio | Membership y workspace activos, tenant.view con scope tenant |
| Editar ajustes | tenant.settings.update en ese mismo contexto |
| Consultar membresías | tenant.members.view en ese mismo contexto |
| Revocar membresía | tenant.members.revoke, contraseña actual y target recuperado dentro del workspace |

El rol inicial incluye únicamente esas cuatro capacidades. Los códigos de rol no conceden acceso por sí mismos; assigned_resources sin objetivo autorizado deniega. is_owner es una condición de gobierno en Membership, no un rol de negocio global ni propiedad musical.

La revocación es idempotente, se audita una vez y se reevalúa en cada petición de cualquier sesión; no se cachean snapshots de autoridad. Revocar en A no elimina una membership independiente de B. No hay altas de terceros, editor de roles o transferencia de ownership en la UI: F2/F3 los completan. Los escenarios con miembros adicionales usan fixtures sintéticos, no un backdoor de producción.

User dispone de active/suspended/revoked. Login y solicitudes de sesiones existentes comprueban el estado vigente, sin habilitar un setter público de estado global. Un workspace suspendido y una membership suspendida/revocada no conceden acceso. Los cambios administrativos de estado y su interfaz/autorización quedan para la entrega que los publique.

La retirada del último propietario activo se impide en la acción y en PostgreSQL, también al suspender una cuenta. Locks de workspace, triggers y comprobaciones diferidas permiten una transacción válida sin dejar un workspace huérfano. Eliminar cuentas con cualquier historial de memberships se rechaza sin cerrar su sesión ni borrar registros; las cuentas anteriores sin historial conservan su recorrido de eliminación. Transferencia/anonimización/retención detalladas se resolverán antes de ampliar ese flujo.

## Migración e integridad

La migración aditiva `2026_11_14_000001_create_identity_access_foundation.php` crea:

- Globales: parties, persons, organizations, tenants y permissions. Añade users.status y users.person_id nullable/único, sin crear identidades automáticamente para cuentas existentes.
- Privadas: tenant_memberships, roles, role_permissions, membership_roles y audit_events; todas con tenant_id obligatorio. Padres privados incluyen UNIQUE(tenant_id,id); pivots de roles/memberships usan FKs compuestas.
- CHECKs de estados/scopes/locale y coherencia de revocación, unicidad tenant+user y validación diferida del subtipo exacto de Party.
- Guardas del último owner y auditoría append-only frente a UPDATE, DELETE y TRUNCATE normales. Las funciones de constraints no son SECURITY DEFINER ni consultas administrativas de catálogo.

Los eventos registran actor, tenant, acción, recurso, UTC y campos/cambios redactados. No guardan contraseñas, nonces, emails o nombres de espacios como payload. El runtime HTTP no dispone de endpoints para reescribirlos. Separar privilegios runtime/migración y probar el rol restringido corresponde a F2/O0; no se afirma inviolabilidad frente al dueño de tablas, superuser o administrador de infraestructura.

La migración requiere PostgreSQL y permisos de funciones/triggers; SQLite no acredita su comportamiento. Se comprobó rollback de esta migración y nueva aplicación, preservando users y el historial anterior. El rollback total del scaffold previo tiene un orden inválido (users antes de sessions, cuya FK depende de users); se detectó al preparar los tests y no se reescribió esa migración compartida. No usar un rollback total como procedimiento de despliegue. Un mantenimiento posterior debe resolver ese recorrido sin alterar historia ejecutada. Las carreras reconstruyen solo ascanio_test con migrate:fresh, con guard de conexión/base/DB_URL.

En desarrollo, ejecutar `source scripts/php-env.sh` y `php8.4 artisan migrate` antes de usar el flujo. Esta entrega no autoriza migraciones/despliegues de producción. Kroma continúa sin comprobación de permisos/capacidades; no se añaden servicios externos.

La migración se aplicó en este equipo después de comprobar APP_ENV=local, PostgreSQL en 127.0.0.1:55432, base ascanio y ausencia de DB_URL externa. Los servicios Compose iniciados para esta validación se detienen al terminar; sus volúmenes se conservan.

## Verificación ejecutada

- `composer run ci:check` con PHP 8.4: 70 pruebas / 472 aserciones, Pint, lint, formato, tipos, prueba de tooling y auditorías aprobados.
- Build Vite aprobado con las páginas nuevas; Composer validate --strict y check-platform-reqs comprobados.
- HTTP: A/B, roles diferentes para una misma cuenta, ausencia/limpieza de contexto, búsqueda/conteo/paginación, campos Inertia limitados, IDs/client fields manipulados, revocación inmediata, sesión independiente, reenvíos y rollback completo ante fallo de auditoría.
- PostgreSQL: FKs compuestas, scopes/estados, subtipo obligatorio/compatible, vínculos inmutables de subtipo y rechazo de cambios de auditoría.
- Tres carreras con dos procesos/conexiones, barrera de salida y locks mantenidos mientras se solapan: misma clave de creación produce un workspace; revocaciones de owners y suspensiones de sus cuentas conservan un propietario activo.
- Rollback/reaplicación de la migración F1 en la base dedicada, sin modificar migraciones previas.

La verificación remota mantiene la matriz PHP 8.3/8.4 y el job dependency-audit. No se realizó validación visual manual en navegador, firma, CWR, uploads ni pruebas de aislamiento de módulos inexistentes. Las suites usan datos sintéticos; no se consultó Kroma.

## Próxima entrega

F2 completa administración de roles/capacidades delegables, transferencia de ownership, condiciones MFA y prueba de privilegios de auditoría con un rol runtime restringido. F3 añade invitaciones reales. F4 cierra jobs, revocación integrada y traducciones; O0 mantiene la puerta operativa de Kroma. Grants y Documents siguen el [ADR 0001](../adr/0001-implementation-sequencing.md).
