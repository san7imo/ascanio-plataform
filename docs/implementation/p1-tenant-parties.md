# Entrega P1 — Contrapartes privadas y provisionales

Fecha: 15 de noviembre de 2026. Base: 75e7c68, con F4 integrada mediante [PR #9](https://github.com/san7imo/ascanio-plataform/pull/9). Rama: feat/tenant-parties. Entrega local de P1 del [plan](../implementation-plan.md); O0/O1 y la operación Kroma permanecen pendientes.

## Recorrido y datos admitidos

Contrapartes permite listar, buscar por nombre visible, paginar, crear, consultar y editar fichas dentro del workspace autorizado. Admite personas y organizaciones sin cuenta ni Party global. Los nombres repetidos, contactos coincidentes e identificadores iguales en otras fichas/tenants no provocan fusiones ni asociaciones. No se consulta un directorio global ni se envían correos a los contactos.

Cada ficha conserva tipo y nombre visible, nombre legal declarado opcional, correo/teléfono opcionales, notas privadas y detalle opcional de procedencia manual. Se puede corregir el tipo declarado mediante una revisión nueva mientras la ficha sea provisional. No se almacenan bancos, impuestos, representación, afiliaciones ni derechos en este recorrido.

Las actividades admiten varias opciones o ninguna, mediante business_types y su pivot local: sello, edición, gestión, interpretación, producción, escritura, servicios DJ u otra actividad. Son un catálogo de referencia preparado por migración; P1 no incorpora su editor. No son roles de aplicación, clases de identidad ni créditos de obras. Una editorial sin grabaciones y una operación híbrida pueden registrarse sin crear catálogo musical.

Los identificadores admitidos son IPI/CAE, ISNI u otro, con valor textual y emisor declarado opcional. Se conservan ceros iniciales; se rechazan valores numéricos, vacíos y duplicados del mismo esquema/valor dentro de la ficha. No se impone unicidad global ni se valida contra registros externos. No se acredita identidad legal, validez del identificador o registro ante sociedades. Se admiten hasta veinte identificadores por ficha; las actividades son opcionales y se validan contra el catálogo.

El servidor fija tenant, actor, source=manual y verification_status=provisional. party_id permanece nulo, protegido también por CHECK PostgreSQL. No hay claims, importación, verificación, invitaciones, recursos musicales, grants o carga de documentos en P1. Un detalle escrito en procedencia no constituye consentimiento de otro tenant ni autoriza compartir datos.

## Acceso y proyección

| Capacidad | Comportamiento |
|---|---|
| parties.view | Nombre visible, tipo, versión y estado/procedencia básicos dentro del workspace |
| parties.view_private | Junto con parties.view, contacto, nombre legal, identificadores, actividades, notas, procedencia e historial |
| parties.create | Junto con ambas consultas anteriores, crear fichas locales |
| parties.update | Junto con ambas consultas anteriores, editar una ficha de ese tenant |

También se requiere cuenta activa/verificada y tenant.view por membership vigente. assigned_resources continúa denegando acceso cuando no existe una asignación soportada; P1 no introduce un ID de recurso genérico para habilitarlo. No hay acceso externo por profesión, actividad, contacto o participación.

La migración agrega las cuatro capacidades al bundle inicial protegido del propietario en workspaces existentes; nuevos espacios reciben ese mismo bundle. Los roles ordinarios permanecen sin ampliación automática. Las capacidades son delegables únicamente conforme a los controles F2/F3 existentes. Esta concesión explícita del bundle no es un bypass de propietario ni acredita derechos musicales.

ResolveWorkspace establece contexto antes de recuperar filas privadas. Queries, actions y policies exigen el mismo contexto y permisos actuales; la ruta determina el tenant y no el body, sesión o header. Los IDs anidados se recuperan con tenant_id explícito. Las consultas básicas seleccionan columnas de resumen; contacto, hijos e historial se leen y proyectan únicamente con autorización privada. No se devuelve el modelo completo en Inertia ni se exponen IDs de cuentas/globales.

La búsqueda y sus conteos usan exclusivamente el nombre visible local. Un lector básico no puede inferir correos, identificadores o notas mediante filtros; el listado no incorpora esos campos, aunque tenga permiso privado. Las vistas privadas de detalle/edición registran party.private_viewed con versión, actor y tenant, sin copiar sus valores al audit log. No existe endpoint separado de historial ni descarga/exportación.

## Revisiones e integridad

SaveTenantParty valida en el módulo y guarda ficha, identificadores, actividades, revisión y auditoría dentro de una transacción. Bloquea cuenta, workspace y ficha en ese orden. Una actualización exige la versión que el usuario abrió; dos ediciones concurrentes de v1 producen una sola v2. La otra falla con un mensaje explícito, conserva el texto del formulario y ofrece recargar/revisar los datos actuales. No hay merge silencioso ni última escritura ganadora.

Cada guardado válido conserva un snapshot nuevo con los datos admitidos, actividades/etiquetas e identificadores de ese momento, autor y tiempo UTC. Las revisiones se muestran paginadas en la zona horaria del workspace. Los cambios de contacto, nombre, actividades o identificadores no actualizan snapshots anteriores. No son contratos, evidencia legal ni el futuro módulo Documents; sus consumidores deberán referenciar explícitamente una revisión exacta cuando se implementen.

Tablas privadas nuevas: tenant_parties, party_identifiers, tenant_party_business_types y tenant_party_revisions, con tenant_id obligatorio y FKs compuestas a la ficha; el padre tiene UNIQUE(tenant_id,id). business_types es referencia global sin datos personales. Las revisiones tienen unicidad por tenant/ficha/versión y triggers que rechazan UPDATE, DELETE y TRUNCATE. La cuenta runtime dispone solo de SELECT/INSERT sobre ellas y SELECT sobre el catálogo. Esto no ofrece protección absoluta frente al administrador de infraestructura.

La auditoría de mutaciones registra IDs de ficha/revisión y versión, sin nombres, correos, notas ni snapshots completos. Si falla la auditoría, la transacción revierte padre, hijos y revisión. Una ficha con historial no se elimina por este recorrido; P1 no incorpora borrado ni purga.

## Migración y operación

La migración aditiva 2026_11_15_000003 no modifica migraciones F1–F3. El upgrade de bundles registra access.parties.upgraded con actor_type=migration y actor_id nulo; preserva los eventos anteriores y aumenta access_version de las memberships afectadas. Las invitaciones anteriores basadas en esa autoridad quedan invalidadas; deben revisarse y crearse de nuevo conforme a F3. No se amplía silenciosamente un snapshot de invitación.

Pausar escrituras/workers antiguos durante un upgrade autorizado, aplicar con credencial migradora y ampliar únicamente los [grants runtime documentados](../operations/postgresql-runtime-role.md). Se permite rollback de un esquema P1 prístino; se rechaza si borraría fichas/revisiones, evidencia de backfill, capacidades ordinarias asignadas o propuestas de invitación que las usan. Corregir hacia delante y preservar historial. Las pruebas reconstruyen exclusivamente ascanio_test, sin confundirlo con recuperación de una instalación compartida.

No se agregan paquetes, servicios, colas, caches de datos privados, archivos ni proveedores. No hay cambios de producción, conexión a Kroma o datos reales. Capacidad, retención, cifrado operativo y recuperación deben resolverse con O0/O1 antes de datos reales; los campos privados actuales y snapshots no incorporan cifrado por columna.

## Verificación y siguiente entrega

La entrega se verifica mediante pruebas HTTP/actions de aislamiento A/B, proyecciones, búsqueda/conteos/paginación, permisos distintos de una misma cuenta, revocación, campos protegidos, borradores sin contacto/identificadores y rollback transaccional. PostgreSQL comprueba FKs y revisiones inmutables; una carrera con procesos independientes comprueba ediciones concurrentes. Se conserva la regresión F1–F4 y se amplía la prueba con LOGIN runtime restringido a creación/edición de fichas e imposibilidad de reescribir historial/catálogo.

Verificación ejecutada:

- composer run ci:check con PHP 8.4: 143 pruebas / 1502 aserciones sobre PostgreSQL/Redis; Pint, lint, formato, tipos, tooling y auditorías Composer/npm sin avisos.
- npm run build con PHP_BINARY=php8.4; Composer validate --strict y check-platform-reqs aprobados.
- Chrome: creación de organización con actividades múltiples e IPI con ceros iniciales; edición, conflicto con cambios del formulario conservados, recarga explícita y snapshots v1/v3. Logout/login, permisos privados distintos A/B y búsqueda por dato oculto sin resultados. Siete capturas a 1365/390 px; inspección visual, sin overflow horizontal ni excepciones JavaScript.
- Upgrade desde F4, backfill auditado, preservación de eventos/épocas y rollback prístino frente a rechazo con historial. Runtime real restringido crea/edita y no puede modificar revisiones/catálogo. La carrera nueva usa dos procesos/conexiones PostgreSQL independientes.
- Enlaces/Markdown y git diff --check revisados. Consultar CI del PR para PHP 8.3/8.4.

La migración se aplicó únicamente a ascanio local tras comprobar APP_ENV=local, PostgreSQL en 127.0.0.1:55432, base ascanio y ausencia de DB_URL efectiva. Los fixtures de navegador se retiraron y ascanio_test se reconstruyó; no se envió correo ni se accedió a Kroma. El siguiente módulo es D1: documentos privados, dependiente de O1 para capacidad, almacenamiento y escaneo, seguido por G1 y catálogo según ADR 0001. P1 no cierra el hito 2 ni Foundation operativo; preparar D1 no autoriza uploads reales ni contratos.
