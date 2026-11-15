# Entrega F3 — Invitaciones de equipo

Base: 1614736, con F2 integrada mediante [PR #7](https://github.com/san7imo/ascanio-plataform/pull/7). Rama: feat/team-invitations. Implementación y validación local completadas; consultar los checks de su PR. F3 no confirma correo/hosting de producción ni cierra Foundation.

## Recorrido y límites

Invitaciones permite crear, buscar, paginar, reenviar y revocar propuestas de membresía en el workspace autorizado. Requiere tenant.members.invite, tenant.members.assign_roles, tenant.view y facultad para delegar todos los permisos propuestos. Crear, reenviar y revocar exigen contraseña/MFA y comparten el límite sensible de F2. El bundle protegido de propietario no se puede conceder por invitación.

El correo se normaliza a minúsculas sin eliminar alias ni buscar cuentas globalmente. La propuesta fija roles ordinarios, sus permisos/delegabilidad y scopes; vigencia configurable de uno a catorce días, siete por defecto. No crea cuentas, identidades, fichas de Parties ni ownership por enviar una invitación.

El destinatario abre el enlace, inicia sesión o crea/verifica su cuenta, revisa los permisos y confirma. La aceptación usa la cuenta activa con correo verificado exactamente igual al destinatario. Participar en otros workspaces no impide aceptar. Se crea solo la membresía y asignaciones aprobadas; para una membership activa no propietaria se agregan esas asignaciones sin retirar ni reducir roles independientes. assigned_resources no concede acceso global y puede redirigir al selector sin abrir el workspace.

Una membership suspendida/revocada no se reactiva por invitación; un propietario tampoco se modifica por este camino. Se requiere el gobierno F2 correspondiente. Los grants a documentos/catálogo/splits/contratos y los enlaces a TenantParties siguen pendientes de sus recursos reales, según el ADR 0001.

## Tokens y contexto de recepción

Cada enlace lleva 32 bytes aleatorios codificados en hexadecimal. La tabla conserva únicamente SHA-256, propósito, destinatario, tenant, caducidad y estados. El token aparece solo en el fragmento del enlace de correo, no en path/query. El GET público no consulta invitaciones ni consume acceso; scanners sin aceptación autenticada no crean membresías.

Claim borra inmediatamente el fragmento del historial visible, envía el token por POST con CSRF y lo limpia del formulario. El servidor comprueba ID exacto, propósito y hash antes de recuperar datos privados. Es una lectura acotada de colaboración previa a la membership, no un directorio global. Después guarda solo ID/hash y vencimiento de prueba de una hora en la sesión cifrada; sobrevive al login/registro/verificación. Una cuenta existente aún sin verificar conserva la prueba mientras verifica su correo; Review/Accept siguen bloqueados hasta entonces. No devuelve el token en props, errores ni old input. La página pública exige no-referrer y no-store.

Review expone solo nombre del workspace, vencimiento y propuesta de roles/capacidades al destinatario verificado con prueba vigente. Una cuenta equivocada no recibe el correo esperado, nombres privados ni información de otros participantes. La aceptación comprueba otra vez cuenta, destinatario, prueba, propósito, hash, estado y tiempo. El token no es login ni firma.

Reenviar rota el token y aumenta delivery_version, conserva el vencimiento y anula enlaces/pruebas anteriores. Solo el creador puede reenviar bajo su misma autoridad; otro administrador autorizado puede revocar y crear una propuesta nueva con su propia procedencia. Una propuesta aceptada o revocada no vuelve a consumirse. Solo se permite una propuesta pending por destinatario/tenant; una anterior vencida se marca expired al crear su reemplazo.

## Autoridad e integridad

La aceptación bloquea invitador/destinatario en orden, el workspace y la invitación; comprueba al invitador original dentro de esa transacción. La propuesta conserva una FK compuesta a su membership y su access_version de origen. Cambiar estados, asignaciones, definiciones de roles usados o transferir gobierno incrementa las versiones afectadas. Aceptar roles en una membership existente también actualiza su versión. Retirar y restaurar una delegación no rehabilita invitaciones anteriores.

Además, los permisos actuales de cada rol deben coincidir con su snapshot y seguir siendo delegables por el invitador. Un cambio de permisos, falta de rol, cuenta/contexto inválidos o versión de autoridad distinta deniega sin conceder acceso. Reenviar no actualiza silenciosamente esa propuesta; se necesita una nueva.

La migración aditiva 2026_11_15_000002 crea team_invitations y team_invitation_roles con tenant_id obligatorio, UUIDs, estados, unicidad y FKs compuestas. Agrega access_version a memberships y la octava capacidad del catálogo; los propietarios existentes reciben la capacidad mediante un backfill acotado y auditado como migration. No se reescriben F1/F2.

Eliminar un rol desvincula su FK actual en las propuestas, preservando su snapshot histórico. Un snapshot no autoriza por sí solo: aceptar exige el rol actual con FK válida. No se usa un recurso genérico tipo+ID. Los eventos registran acciones, IDs, versión de envío y vencimiento sin correos, nombres privados, tokens o payloads del proveedor.

El upgrade requiere pausar escrituras/workers antiguos durante el cambio de versión. En este equipo se aplicó únicamente a ascanio local, tras comprobar APP_ENV=local, PostgreSQL en 127.0.0.1:55432 y ausencia de DB_URL externa. El rollback se rechaza si existe historial de invitaciones; usar correcciones hacia delante y recuperación autorizada. Se probó upgrade de F2 existente y rollback/reaplicación sin historia. El problema histórico del rollback total del scaffold no se modifica.

## Envío, fallos y operación

El job se despacha después del commit; un rollback no envía correo. Lleva IDs explícitos de tenant/invitación/solicitante y versión, revalida autoridad y limpia TenantContext al comenzar y terminar, también en error. Su identificador incluye tenant e invitación; usa la cola compartida atendida por el worker convencional. No necesita un worker por tenant.

El token necesario para construir el correo viaja en el comando cifrado de la cola mediante ShouldBeEncrypted; no existe una copia en claro en la tabla. Esto requiere la misma APP_KEY segura en web/workers. No registrar jobs, cuerpos de correo ni payloads descifrados.

SMTP se admite como transporte real; array se identifica como simulación. log y otros transportes no revisados se bloquean para no escribir secretos en logs. El default local log sigue siendo válido para el scaffold, pero no envía estas invitaciones. Para revisarlas localmente usar un receptor SMTP sintético; no copiar enlaces de logs ni configurar destinatarios reales como fixture.

Los estados distinguen pendiente, transporte aceptado, simulado, fallo y bloqueo, con código de error acotado y reintento manual. Si falla el encolado después del commit, la invitación y la confirmación MFA ya están guardadas; el estado queue_unavailable y un aviso operativo limitado a IDs/tipo de excepción lo hacen visible y se reenvía con una confirmación nueva. Un fallo de SMTP señala retry con una excepción saneada, sin arrastrar mensajes del proveedor ni tokens a failed_jobs.

El job hace hasta tres intentos con backoff y timeout de 45 segundos. SMTP tiene timeout configurable MAIL_TIMEOUT, 15 segundos por defecto. En F3 el envío se serializa bajo locks para comprobar revocaciones y duplicados; los límites efectivos de SMTP/PHP/worker/retry_after deben validarse en O0. Una entrega completada o versión obsoleta no se repite. Un fallo después de que SMTP aceptó el mensaje puede producir correo duplicado al reintentar; no se promete exactamente una entrega externa. Las concesiones de acceso sí se consumen una vez bajo transacción.

Aceptación del transporte no demuestra llegada a buzón. SMTP/TLS real, supervisión de workers, retención de jobs fallidos y operación Kroma siguen pendientes. El [procedimiento PostgreSQL](../operations/postgresql-runtime-role.md) incluye las tablas nuevas; el test runtime también ejecuta creación/envío sintético con su login restringido.

## Verificación ejecutada

- composer run ci:check con PHP 8.4 aprobado: Pint, lint, formato, tipos, tooling y auditorías Composer/npm sin avisos. Suite final php artisan test: 117 pruebas / 1123 aserciones; incluye la cuenta existente aún sin verificar.
- Build Vite, Composer validate --strict y check-platform-reqs aprobados.
- HTTP/acciones: destinatario incorrecto, registro/verificación, cuenta existente en otro tenant, replay, vencimiento, rotación, revocación, roles cambiados/eliminados, restauración de autoridad, scopes, contexto y campos/targets manipulados.
- PostgreSQL: FKs compuestas y dos carreras nuevas con procesos independientes, dos aceptaciones y aceptación contra revocación. El conjunto conserva las cinco carreras F1/F2.
- Commit real frente a rollback, fallo de encolado y recuperación, transporte log bloqueado, errores saneados y payload real de Redis cifrado, con limpieza de la cola sintética.
- Chrome: crear con MFA y SMTP local sintético, reclamar el fragmento como invitado, login con MFA al destino previsto, revisión/aceptación y conservación del workspace independiente. Capturas desktop y móvil a 390 px inspeccionadas; sin token en URLs HTTP comprobadas, overflow horizontal o excepciones JavaScript.

Los fixtures usan ascanio_test y direcciones example.invalid. Los procesos, perfiles, credenciales y mensajes del receptor sintético se retiran al terminar; los servicios Compose se detienen conservando volúmenes. No se añadió un proveedor, paquete, contenido legal ni código Dilo. No se conectó a Kroma ni se envió correo a personas reales.

La siguiente entrega es F4: cerrar la matriz integrada de jobs/revocación y traducciones de Foundation. La evidencia técnica F3 no cierra O0/O1 ni la preparación operativa del hosting.
