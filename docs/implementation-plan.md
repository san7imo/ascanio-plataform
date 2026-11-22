# Plan de implementación de Ascanio

Fecha de auditoría: 14 de noviembre de 2026. Base revisada: `origin/main` en `b345691`.
Decisión: conservar el roadmap del README, ajustar sus dependencias y ejecutarlo mediante las entregas de este documento. Este plan documenta trabajo futuro; no acredita su implementación ni autoriza despliegues.

Actualización D1: 21 de noviembre de 2026. Base de entrega: `1c4a088`, con P1 integrada mediante [PR #10](https://github.com/san7imo/ascanio-plataform/pull/10). La [auditoría previa](implementation/repository-audit-2026-11-15.md) conserva el corte anterior a F2; su borrador local fue revisado y completado en [la entrega F2](implementation/f2-workspace-governance.md). Las secciones 1–3 conservan el diagnóstico inicial; el estado vigente es:

| Entrega | Estado vigente | Evidencia / siguiente paso |
|---|---|---|
| Arquitectura y scaffold | Integrados | PRs #1 y #2; sin merge pendiente de las dos ramas originales |
| P0 | Integrado | [PR #4](https://github.com/san7imo/ascanio-plataform/pull/4); auditorías repetidas sin avisos en esta fecha |
| F1 | Integrado y comprobado | [PR #5](https://github.com/san7imo/ascanio-plataform/pull/5); regresión incluida en F2 |
| F2 | Integrado | [PR #7](https://github.com/san7imo/ascanio-plataform/pull/7); regresión incluida en F3 |
| F3 | Integrado | [PR #8](https://github.com/san7imo/ascanio-plataform/pull/8); regresión incluida en F4 |
| F4 | Integrado | [PR #9](https://github.com/san7imo/ascanio-plataform/pull/9); cierre técnico con operación pendiente O0 |
| P1 | Integrado | [PR #10](https://github.com/san7imo/ascanio-plataform/pull/10); regresión incluida en D1 |
| D1 | Integrado mediante [PR #11](https://github.com/san7imo/ascanio-plataform/pull/11), merge 06f3c34 | [Versiones privadas, cuarentena, descarga y límites](implementation/d1-private-documents.md); 165 pruebas / 1801 aserciones; cargas reales deshabilitadas y O1 pendiente |
| G1 | Integrado mediante PR #12, merge aca2ad7 | [PR #12](https://github.com/san7imo/ascanio-plataform/pull/12); 181 pruebas / 2126 aserciones y CI PHP 8.3/8.4 aprobados en su entrega; no repetidos por este cambio documental |
| PUB0 | Completado: inspección de lectura, inventario y capturas | [Referencias fechadas](references/ascaniopub/README.md), rutas, capturas desktop/móvil y decisión de conservar compra actual |
| PUB1 | En ejecución: PUB1.1–PUB1.2 preparados en rama para revisión | Layout/marca y portada locales; [entrega parcial y siguiente paso](implementation/pub1-public-site.md#primera-entrega-pub11-y-pub12). PUB1.3–PUB1.5 pendientes |
| PUB2–PUB3 | Pendientes | Integración de recorridos y validación completa según §4.1; sin nuevo motor de comercio |
| O0 / O1 | Pendientes de evidencia técnica | Titular aportó PHP CLI/extensiones por SSH; faltan cuota/inodos, endpoints de servicios, ruta privada, escáner y operación medidos. [Preparación O1](operations/documents-readiness.md) |
| Resto de hitos 2–6 | Diseño objetivo | Parties/Documents/G1 integrados y fachada pública en implementación; catálogo y módulos musicales posteriores siguen pendientes |

Actualización de alcance público: 22 de noviembre de 2026 UTC. Por instrucción del titular se incorpora la réplica institucional de ascaniopub.com, conforme al [ADR 0002](adr/0002-public-institutional-site.md) y al [inventario con capturas](references/ascaniopub/README.md). La elección comercial es replicar páginas de productos y **conservar la compra actual**, sin carrito/pagos propios. La referencia y el plan se integraron mediante PR #13 en f732ecb. La implementación local posterior entrega primero PUB1.1–PUB1.2; no se publica la réplica.

Actualización del orden ejecutable: 21 de noviembre de 2026 (America/Bogota). El estado de integración se contrastó con los PRs abiertos; las fechas UTC de las referencias se conservan. El detalle inmediato de PUB1 está en su [paquete de implementación](implementation/pub1-public-site.md).

## 1. Fuentes, producto y estado comprobado

En la auditoría inicial se leyeron todos los documentos Markdown disponibles entonces en el checkout: [README](../README.md), [AGENTS](../AGENTS.md), [THIRD_PARTY_NOTICES](../THIRD_PARTY_NOTICES.md) y el [informe Kroma](hosting/kroma-compatibility.md), que estaba pendiente de commit. Se inventariaron los Markdown de todas las ramas locales/remotas y se compararon sus diferencias. También se revisaron historial, PRs, búsqueda de issues, dependencias, scripts, rutas, modelo User, harness de pruebas y workflow de CI.

El PRD 360º, los planes comercial/funcional y los reels mencionados en README no están adjuntos ni versionados en estas ramas. No se afirma haber releído esos originales. Sus referencias no amplían el alcance del MVP. No se encontró otro plan de implementación separado ni issues de trabajo: el plan disponible era README §12, complementado por sus decisiones pendientes y AGENTS.

Ascanio es un SaaS para operaciones musicales diversas. Un User es una cuenta; Party es identidad mínima; Tenant es custodia y espacio operativo; Membership es acceso de equipo; TenantParty es ficha privada. La profesión no determina los permisos. Work es composición; Recording es grabación; Release contiene ocurrencias ordenadas de recordings. Autoría, titularidad, administración, cobro, reparto de ingresos, representación y acceso permanecen separados.

El MVP permite trabajar una composición sin recording, gestionar catálogo y derechos con alcance explícito, acordar splits versionados, colaborar sin exponer el catálogo completo, generar dos tipos de split sheet, firmar una versión exacta y preparar un registro para un receptor concreto. No incluye royalties, pagos, contabilidad, billing, distribución, API pública, IA ni la visión 360º completa.

| Entrega existente al inicio de la planificación | Evidencia y límite histórico |
|---|---|
| Arquitectura de dominio | README/AGENTS integrados mediante PR #1; decisiones de firma, CWR y hosting pendientes |
| Base técnica y cuenta global | PR #2; Laravel 12, Vue 3, TypeScript, Inertia, Fortify, UUID de User y sesiones cifradas |
| Desarrollo y calidad | PostgreSQL/Redis locales, lockfiles, PHPUnit, Pint, ESLint, Prettier, tipos y build; CI para PHP 8.3/8.4 |
| Funcionalidad actual | Cuenta, verificación de correo, recuperación, MFA y ajustes; dashboard de bienvenida |
| Foundation de negocio | Sin tenants, memberships, roles por tenant, grants, invitaciones de equipo o auditoría de dominio |
| Módulos musicales | Sin implementación; no existen modelos/tablas privadas de catálogo, derechos o contratos |
| Producción/staging | Sin despliegue configurado ni compatibilidad Kroma confirmada |

En la revisión local anterior de esta misma sesión se ejecutaron 43 pruebas/153 aserciones sobre PostgreSQL y Redis, Pint, lint, formato, typecheck, build, Composer validate/check-platform-reqs y diff --check, satisfactoriamente. Las auditorías reportaron 11 avisos en 6 paquetes Composer y 12 dependencias npm afectadas (11 moderadas y una alta). Son resultados fechados, no una certificación de seguridad ni checks repetidos por editar este plan. La primera ejecución de pruebas falló por servicios detenidos; tras iniciarlos pasó. Los servicios quedaron detenidos.

## 2. Auditoría de ramas y base de trabajo

| Rama auditada | Resultado | Tratamiento |
|---|---|---|
| `docs/domain-tenancy-architecture` (`882b37f`) | Ancestro de main; PR [#1](https://github.com/san7imo/ascanio-plataform/pull/1) integrado el 12 de octubre | Ya integrada; no repetir merge ni borrar la referencia |
| `feat/technical-foundation` (`64858b8`) | Ancestro de main; PR [#2](https://github.com/san7imo/ascanio-plataform/pull/2) integrado el 16 de octubre | Ya integrada; código coincidente con main |
| `main` local (`671c7eb` al comenzar) | Anterior al merge de Foundation | Actualizar por fast-forward al main integrado; conservar historia |
| `review/technical-foundation-local` (`378d67c`) | Commit alternativo local; cero diferencias en archivos distintos de Markdown respecto a main; README/AGENTS anteriores a la decisión Kroma | Preservar como referencia; no incorporar documentación obsoleta ni duplicar la entrega |
| `origin/main` (`b345691`) | Contiene ambos PRs; sin commits adicionales pendientes en esas dos ramas remotas | Base común de próximas entregas |

El texto accidental `||||...` del README se corrige y el informe histórico Kroma se conserva. El plan se prepara en una rama documental nacida del main integrado. Los siguientes cambios funcionales parten de main actualizado, con ramas cortas y PRs; no se crea develop ni un despliegue implícito.

## 3. Resultado de la auditoría del roadmap

| Hallazgo | Decisión de ejecución |
|---|---|
| El orden general sigue las dependencias de negocio y limita el MVP | Conservar los hitos 0–6; no reiniciar el proyecto ni sustituir la arquitectura |
| Cada hito agrupa demasiado trabajo para un PR y carece de backlog operativo | Descomponer en IDs, dependencias y criterios verificables; estos IDs pasan a issues al iniciar cada entrega |
| Scaffold implementado puede confundirse con Foundation cerrada | Mantener Foundation pendiente; cerrar por comportamiento multi-tenant y operación, no por cantidad de archivos |
| Grants completos en Foundation requieren recursos de hitos posteriores | Aplicar [ADR 0001](adr/0001-implementation-sequencing.md): añadir objetivos tipados con cada módulo real |
| Derechos requieren evidencia antes del Vault contractual | Adelantar Documents base al hito 2; mantener generación/firma/Vault contractual en hito 4 |
| Identidad mínima es necesaria para `Tenant.operator_party_id` antes de Parties | Entregar Party/subtipos y vínculo de cuenta mínimos en Foundation; Parties se ocupa después de fichas privadas y relaciones |
| Auditoría y notificaciones no pueden esperar al final de Foundation | Incorporarlas con la primera mutación sensible y extenderlas en cada entrega |
| Kroma está definido como destino, pero su compatibilidad no está probada | Trabajar localmente; mantener una puerta de cierre operativa sin cambiar stack ni adquirir servicios |
| CI no despliega y no ejecuta auditorías de dependencias | Corregir la descripción de staging; incorporar la política de auditoría en mantenimiento |
| Las dependencias tienen avisos posteriores a la entrega técnica | Priorizar P0 antes del siguiente código funcional; actualizar dentro de compatibilidad comprobada |
| Las fechas históricas no consideran decisiones/equipo actuales | Estimar cada entrega al iniciarla y recalibrar tras Foundation; sin promesa de 12–18 semanas |

## 4. Reglas de ejecución y cierre

README fija dominio/arquitectura; AGENTS fija ingeniería; este plan fija secuencia; el issue/tarea de cada entrega fija alcance y aceptación. [ADR 0001](adr/0001-implementation-sequencing.md) sustituye únicamente el orden anterior de grants/Documents. Cualquier cambio futuro explicita la decisión que reemplaza.

Cada entrega produce un recorrido usable o una dependencia técnica acotada y comprobable. Incluye migraciones aditivas cuando correspondan, constraints PostgreSQL, policies/consultas/proyecciones equivalentes, UX en español, auditoría redactada, pruebas pertinentes y documentación del comportamiento real. No publicar endpoints de mutación incompletos entre PRs.

Todas las tablas/pivots privadas llevan tenant_id y relaciones compuestas; los globales se limitan a las excepciones del README. El servidor deriva el contexto autorizado, sin primer tenant por defecto ni confiar en tenant_id enviado por el cliente. Cachés, locks, jobs y archivos incluyen tenant; los jobs revalidan autoridad y limpian contexto incluso al fallar. Los scopes no sustituyen policies.

No se crean tablas vacías de todo el roadmap, repositorios genéricos, un motor de workflows, RLS no probado ni un bypass global de administración. IdentityAccess posee los grants aunque sus objetivos pertenezcan a otros módulos. Los consumidores poseen sus vínculos de evidencia; Documents no depende de Contracts.

La matriz mínima de acceso que cada entrega debe preservar es:

| Contexto | Acceso permitido únicamente si está autorizado | Denegación que debe probarse |
|---|---|---|
| Equipo con membership activa | Capacidades asignadas y alcance tenant o recursos concretos | Recurso de otro tenant, campos no autorizados y permisos retirados |
| Owner del workspace | Gobierno/equipo/configuración según capacidades | Firma por terceros, ownership musical automático y edición de versiones congeladas |
| Participante externo con grant | Recurso/versión y proyección exactos durante su vigencia | Catálogo completo, split ajeno y archivos no concedidos |
| Manager/representante | Recursos concretos con relación vigente y autorización de aplicación | Acceso por profesión, nombre coincidente o mera relación de representación |
| Firmante destinatario | Documento íntegro exacto a firmar, con facultad verificada | Otra versión, firma por otro participante y contratos/bancos ajenos |
| Administración de plataforma | Operaciones de servicio expresamente permitidas | Lectura universal de catálogo o bypass de policies de negocio |

Para código, ejecutar los comandos reales de README: PostgreSQL/Redis locales dedicados, generación Wayfinder antes de tipos, artisan test, Pint --test, lint:check, format:check, typecheck, build, Composer validate/check-platform-reqs y diff --check. Las dependencias modificadas añaden auditoría y revisión de compatibilidad; las pruebas críticas incluyen concurrencia con conexiones/procesos independientes. No usar SQLite como única evidencia de aislamiento.

Para documentos, revisar consistencia, enlaces locales, Markdown y diff --check. No afirmar nuevos resultados de aplicación sin ejecutarlos. Cada PR identifica qué se probó y qué sigue pendiente. Antes de fusionar, verificar head/base y checks de ese commit; no force-push, no reescritura de migraciones compartidas ni cambios de producción.

## 4.1. Fachada pública institucional — PUB0 a PUB3

La solicitud del titular modifica el aplazamiento de la web institucional, con sustitución explícita en [ADR 0002](adr/0002-public-institutional-site.md). Sitio público significa la web anónima de Ascanio. Portal de participantes significa el área autenticada de G1 y módulos posteriores. La marca pública no modifica la custodia, las policies ni las capacidades del core.

| ID / dependencias | Entrega | Criterios de cierre |
|---|---|---|
| PUB0 / solicitud del titular | Inventario del sitio actual, rutas, recursos, estados y capturas; decisión comercial | Evidencia real fechada y galería desktop/móvil; distinguir páginas visibles, productos, legales, rutas heredadas y enlaces externos; no afirmar que una compra o formulario funciona por haberlo visto |
| PUB1 / PUB0 y base integrada revisada | Réplica visual del sitio institucional: layout, marca, navegación, portada, páginas de servicios, guía, contacto, listado y fichas de productos, páginas legales vigentes | Conservar composición, tipografía, imágenes, banners, tarjetas, acordeones y responsive de referencia; recursos versionados, sin hotlinks como dependencia de entrega; rutas públicas anónimas y entrada a la aplicación identificada; sin consultas privadas |
| PUB2 / PUB1, destinos y mecanismos comprobados | Conectar navegación, contacto, preferencias de cookies, enlaces comerciales existentes, buscador de dominios y acceso Fortify | Formularios con validación/CSRF/rate limits y errores reales; correo solo mediante transporte autorizado, sin éxito ficticio; mantener compra actual y enlaces externos, sin carrito/pagos nuevos; endpoints de búsqueda reales permitidos o acceso explícito al servicio existente; cookies/tracking según configuración revisada |
| PUB3 / PUB1–PUB2; O0 aplicable antes de staging/publicación | Validación de paridad, accesibilidad, SEO y transición desde el sitio actual | Comparar a 1440 px y 390 px, incluyendo menú móvil, teclado/foco, acordeones, slider y consentimiento; comprobar rutas/estados/links/metadata/404 y mapa de redirecciones; probar que invitaciones/documentos siguen requiriendo autorización; preservar compra existente y planear cambio de dominio solo en una tarea de producción autorizada |

Alcance editorial: la portada y las ocho páginas principales de la referencia, las tres fichas comerciales detectadas, el listado de productos y las páginas legales que se confirmen vigentes. El sitemap también enumera cuenta/carrito/checkout, presupuesto y contenido heredado. Las rutas de compra se preservan en su sistema actual; la ficha de cuenta comercial no se convierte en el portal de G1 ni se vinculan usuarios WordPress por email. El [inventario](references/ascaniopub/README.md) declara qué rutas se visitaron y cuáles solo se descubrieron. No se añade un blog/CMS, buscador global de tenants o motor de tiendas por la existencia de entradas en el sitemap.

El sitio actual usa WordPress/Elementor y componentes de slider, cookies y comercio. La réplica se implementará en el stack Laravel/Vue existente, con contenido público separado de los módulos de negocio. La compra conserva su backend existente, fuera del core SaaS; antes del corte debe definirse la convivencia de rutas/hosts en Kroma y comprobar enlaces, recursos y callbacks. Conservar un href absoluto al dominio que se va a reemplazar no demuestra continuidad de la tienda. No mover el WordPress, cambiar DNS, vhosts o document roots en PUB0 ni contratar servicios.

La referencia detectó carga diferida del JavaScript: antes y después de interacción aparecen estados distintos. El slider inicializado tiene un contenedor padre de altura cero y puede superponerse al contenido. Se conservan capturas/diagnóstico de ambos estados. La réplica debe mostrar las secciones en orden, reservar altura para imágenes/banners y funcionar con teclado/móvil; se registrará cualquier diferencia deliberada de accesibilidad o corrección de incidencias sin sustituir el diseño de marca.

Las menciones comerciales de distribución, copyright, hosting o asesoría se reproducen como contenido institucional revisado. No habilitan DDEX, royalties, billing, firma ni facultades legales en el SaaS. Los datos comerciales, precios, enlaces y textos legales se revisan con el titular al preparar publicación; el snapshot no certifica disponibilidad ni validez jurídica. No copiar credenciales, IDs de analítica, tracking o servicios de plugins por defecto.

PUB1 puede desarrollarse localmente con recursos públicos y fixtures. O0/O1 siguen bloqueando sus operaciones dependientes y los archivos reales. Un contacto pendiente de SMTP o una decisión de enrutamiento no paraliza el catálogo ni autoriza sustituir servicios por resultados simulados. La implementación posterior tendrá pruebas HTTP de páginas públicas y denegaciones sobre rutas privadas, checks reales de frontend/build y una comparación visual con esta referencia; la entrega PUB0 ejecutó solo comprobaciones documentales y de capturas. La primera implementación PUB1.1–PUB1.2 registra sus pruebas reales y límites en el [paquete PUB1](implementation/pub1-public-site.md).

## 5. Trabajo inmediato y Foundation

Estado inicial de la planificación: P0 y todas las entregas F/O estaban pendientes. Estado al 15 de noviembre: P0 integrado mediante [PR #4](https://github.com/san7imo/ascanio-plataform/pull/4); [registro de cambios, pruebas y límites](security/dependency-maintenance.md). F1 integrado mediante [PR #5](https://github.com/san7imo/ascanio-plataform/pull/5), con sus tres jobs remotos aprobados y regresión local repetida: [recorridos, migración y pruebas](implementation/f1-workspace-foundation.md). F2 está implementado y validado localmente, incluidos roles/delegación, MFA, transferencia, UI y conexión PostgreSQL restringida: [evidencia y límites](implementation/f2-workspace-governance.md). F3 implementa invitaciones de equipo con entrega cifrada, autoridad revalidada y aceptación atómica: [recorridos y pruebas](implementation/f3-team-invitations.md). F3 está integrada mediante PR #8. [F4](implementation/f4-foundation-readiness.md) cierra técnicamente la matriz de entidades actuales, con daemon Redis real y mensajes españoles. La [guía operativa](operations/foundation-readiness.md) documenta las puertas pendientes O0/O1; no hay conexiones ni operaciones en Kroma.

### P0 — Mantenimiento de dependencias y controles

Dependencia: base técnica integrada. Entrega completada en `fix/dependency-security`; los criterios siguientes se conservan como referencia de cierre y mantenimiento.

- Revisar los avisos Composer/npm actuales, su cadena de dependencia y la exposición del código. Contrastar parches y requisitos con fuentes oficiales en la ejecución; no fijar en este plan versiones supuestamente seguras para siempre.
- Aplicar cambios compatibles con Laravel 12, PHP 8.3/8.4, Node 22 y el frontend existente, conservando lockfiles. Una incompatibilidad real se documenta antes de cambiar una versión mayor; no usar audit fix --force ni cambiar el stack por conveniencia.
- Añadir auditorías a CI con una política explícita: ningún fallo silenciado; una excepción justificada identifica aviso, exposición, responsable y caducidad. Revisar también el efecto en herramientas de desarrollo y dependencias transitivas.
- Cierre: checks completos aprobados, avisos resueltos o excepciones concretas revisadas y documentadas; instalación desde lockfiles verificada. Esta entrega no añade dominio musical.

### O0 — Compatibilidad operativa de Kroma, en paralelo

Dependencia: información del proveedor/cPanel aportada de forma autorizada. No bloquea el trabajo local; sí el cierre operativo de Foundation y cualquier despliegue dependiente del hosting.

- Completar la matriz de [Kroma](hosting/kroma-compatibility.md): PHP web/CLI y extensiones, Composer, PostgreSQL, Redis, cron, workers/reinicio, privilegios, rutas privadas, límites HTTP, correo/TLS, región, cuota/inodos y backups/restauración.
- Definir cuentas de migración/runtime y privilegios mínimos, incluyendo auditoría no reescribible por runtime. El usuario dueño de las bases de desarrollo no acredita esa separación.
- Registrar evidencias fechadas, restricciones y alternativas internas con impacto. El margen de 5,73 GB del 12 de octubre no se presume vigente ni exclusivo de Ascanio.
- Cierre: compatibilidad demostrada o incompatibilidad concreta con decisión explícita de solución. Sin respuesta, estado pendiente; sin fallback silencioso a MySQL ni servicios externos de pago.
- Se conserva la restricción histórica de no conexión directa. Una inspección remota o despliegue requerirá una instrucción específica; no hacen parte de esta planificación.

### F1 — Primera operación multi-tenant completa

Dependencia: P0. Rama sugerida: `feat/workspace-foundation`.

Entregar el mínimo global de Party/Person/Organization necesario para el operador del tenant y el vínculo opcional de User, con estado/procedencia y subtipo compatible. No crear directorio global ni vincular identidades existentes por nombre/email/IPI. Una declaración propia y un correo verificado no certifican identidad legal; reclamar una organización existente requiere facultad acreditada y no se habilita por escribir su nombre.

Crear Tenant, Membership, permisos/roles mínimos necesarios y contexto explícito. El alta del workspace, operador admitido, membership y rol inicial se realiza en una transacción. Incluir creación/selección de espacio y ajuste mínimo de nombre, idioma/zona horaria y branding cuando se exponga; verificar acceso antes de route binding, consulta y serialización. No dejar acciones abiertas hasta F2.

La cuenta debe poder suspenderse/revocarse y el contexto debe comprobar su vigencia. Desde esta entrega, registrar altas/cambios sensibles en auditoría con actor/tenant/UTC y cambios redactados. Preparar invalidación de acceso sin desconectar indiscriminadamente los otros tenants del mismo usuario. La eliminación de cuenta no puede dejar un workspace sin owner ni borrar historia por cascada.

Criterios de aceptación:

- Una misma cuenta verificada crea/opera A y B; otra cuenta sin autoridad no descubre ni abre sus datos privados.
- Sin contexto, con workspace suspendido o con membership revocada se deniega. Cambiar A→B→A no reutiliza estado o permisos de otro contexto.
- Body/header/FK manipulados no eligen tenant/owner/operador ajeno. Lists, búsquedas, conteos y paginación de memberships/configuración aplican el mismo acceso.
- UUID, unicidad tenant+user y FKs compuestas impiden relaciones privadas cruzadas; las excepciones globales son explícitas.
- Party no queda sin subtipo o con subtipos incompatibles por los caminos de escritura admitidos; los vínculos verificados no se infieren de coincidencias.
- Un fallo durante el alta revierte todo; reenvíos no duplican el workspace por accidente. Eliminar/desactivar al último owner falla de forma segura.
- Las pruebas PostgreSQL cubren rutas, acciones, consultas, constraints y props Inertia; la interfaz refleja decisiones del servidor.

Fuera de F1: catálogo, claims automáticos de terceros, recursos compartidos y firma. Si hace falta dividir el PR, separar persistencia interna de la publicación del recorrido; no exponer una mutación sin autorización.

### F2 — Gobierno del workspace y administración de capacidades

Dependencia: F1. Entregar roles privados por tenant, catálogo global de capacidades, access_scope tenant/assigned_resources, asignaciones y policies explícitas. Añadir gestión de equipo, delegación limitada y transferencia de ownership con confirmación de identidad/MFA según la operación sensible.

Cierre: mismo usuario con roles diferentes en A/B; owner/admin no delega capacidades fuera de su autoridad; no existe Gate::before universal; suspensión/revocación invalida los caminos dependientes y cachés. Transferencia y retirada concurrente de owners preservan al menos uno activo, también ante borrado de cuenta. Permisos assigned_resources sin un objetivo autorizado deniegan; no equivalen a acceso completo. Runtime no puede editar/borrar eventos de auditoría en una prueba con rol restringido PostgreSQL. Si esos privilegios aún no pueden garantizarse en Kroma, no se declara cumplimiento operativo.

Desglose de la entrega F2, implementada desde `main` actualizado:

| Paso | Implementación acotada | Prueba de aceptación |
|---|---|---|
| F2.1 | Catálogo de capacidades actuales y marca explícita de delegabilidad; rol protegido de gobierno; migración aditiva para F1 existente | Upgrade con varios tenants sin privilegios cruzados ni cambios de historia; ningún nombre de rol actúa como bypass |
| F2.2 | Acciones, requests, policies y consultas de roles/asignaciones; pantallas españolas | Crear/editar/retirar solo capacidades delegables; comprobar tanto permisos anteriores como nuevos; FK/IDs cruzados, búsqueda/conteos y props aislados |
| F2.3 | Suspender/reactivar/revocar membresías y transferir gobierno en transacciones | Revalidar autoridad dentro del lock; conservar un propietario activo; cambios efectivos en otras sesiones sin afectar membresías independientes |
| F2.4 | Confirmación sensible con Fortify/MFA, sin proveedor ni dependencia nuevos | Contraseña incorrecta, MFA ausente/no confirmado, código inválido o reutilizado y rate limit; secretos fuera de props, auditoría y old input |
| F2.5 | Prueba con conexión PostgreSQL runtime restringida y procedimiento de privilegios | Runtime real sin ownership/superuser/BYPASSRLS puede operar y añadir auditoría, pero no actualizar/borrar/truncar ni desactivar sus protecciones |
| F2.6 | Regresión integrada, revisión visual y documentación de upgrade/límites | Suite PostgreSQL con carreras independientes, Pint, lint, formato, tipos, build y auditorías; registrar resultados de ese head |

Los pasos son partes de F2, no hitos nuevos ni permisos para abrir otros módulos. No publicar una UI que prometa transferencias o MFA antes de completar las acciones y sus pruebas.

Decisiones adoptadas para esta entrega de gobierno F2:

- La transferencia requiere propietario vigente, capability específica y confirmación de contraseña/MFA. El destinatario es otro miembro activo del mismo workspace, con cuenta activa, correo verificado y MFA confirmado. La transferencia cambia gobierno del workspace, nunca identidad legal ni derechos musicales.
- El rol protegido de propietario no se edita/asigna por el editor ordinario. Las demás capacidades solo se conceden, modifican o retiran dentro de la autoridad delegable del actor; tener una capacidad no basta para delegarla. `assigned_resources` sigue denegando operaciones sin un recurso real autorizado.
- Exigir confirmación sensible también al cambiar roles, asignaciones o estados de membresías. Un código TOTP consumido no se reutiliza para otra mutación, incluso en otro workspace. La transacción debe revertir el consumo si falla la mutación/auditoría.
- Conservar los roles independientes del propietario anterior. Rechazar targets ajenos, cuentas inválidas y carreras que dejen el espacio sin propietario activo. Revalidar delegación al reactivar una membresía para evitar escalamiento indirecto.
- Migrar F1 sin reescribir su migración ni atribuir cambios automáticos a un actor humano ficticio. Especificar procedencia de cualquier backfill y recuperación compatible con la auditoría append-only.

La entrega F2 acredita estos recorridos con acciones, requests, rutas, UX y pruebas; el antiguo commit de borrador permanece como referencia. Las invitaciones/altas de terceros pertenecen a F3. El test de privilegios local no confirma permisos ni disponibilidad operativa en Kroma.

### F3 — Invitaciones de equipo y notificaciones

Dependencia: F2. Invitaciones de membership ligadas a tenant, destinatario y propósito; token aleatorio guardado como hash, expirable, revocable, de consumo atómico y con rate limits. Cuentas nuevas verificadas y existentes usan el mismo flujo autorizado, aunque ya pertenezcan a otro tenant. No vincular aún TenantParties inexistentes ni conceder recursos futuros.

Cierre: aceptar crea solamente la membership/roles autorizados; destinatario equivocado, token vencido/revocado/reutilizado y dos aceptaciones concurrentes fallan correctamente. La autoridad del invitador se revalida al aceptar; no sobrevive una delegación retirada. Correo tras commit, reintentos sin duplicar concesiones y fallos visibles. No hay tokens en auditoría, errores ni props compartidas. SMTP real depende de O0; las pruebas usan transporte sintético.

### F4 — Jobs, revocación y cierre de Foundation

Dependencia: F3 para las pruebas integradas; sus controles se incorporan desde la primera entrega que introduce un job/cache.

Probar tenant/requester/recurso explícitos, autoridad actual y limpieza de contexto en éxito/error y al encadenar tareas de A y B en un worker. Rechazar trabajo delegado tras revocación. Aplicar namespace de tenant a caché, locks y storage; separar usuario/proyección cuando corresponda. Probar notificaciones after-commit y ausencia de envío al hacer rollback.

Cierre técnico: F1–F4 y su matriz de seguridad aprobados, mensajes/pantallas existentes traducidos al español, tiempos UTC con display de zona y regresión de Fortify. Cierre del hito 1: además O0 resuelto y operación mínima documentada (correo, workers, logs, copias y recuperación). Un bloqueo operativo se registra sin atribuirlo a una funcionalidad musical aún inexistente.

## 6. Hito 2 — Parties, documentos base y catálogo

| ID / dependencias | Entrega | Criterios de cierre |
|---|---|---|
| P1 / F1–F4 | TenantParties provisionales, identidades locales, identificadores y actividades; privacidad/procedencia | Ficha sin cuenta ni Party global obligatoria; datos privados separados por tenant; ninguna búsqueda global por email/IPI; cambios no alteran snapshots históricos |
| D1 / F1–F4, O1 | Documents/DocumentVersion, estado de archivo, checksum, storage relativo privado, validación/cuarentena y descarga autenticada | MIME/tamaño/malware verificados; fallo de escaneo no publica; archivo fuera de web roots; URL firmada sola no basta; metadata no concede adjuntos; descarga revocada falla; archivos/versiones no se sobrescriben |
| G1 / D1, F2–F3 | Grants e invitaciones para DocumentVersion real, con objetivo tipado único | FK tenant+document_version; destinatario/proyección/expiración/revocación/procedencia comprobados; invitado sin membership obtiene solo documento/version autorizados; no hereda catálogo |
| C1 / P1, G1, F2 | Work y créditos de composición; ampliar grants/asignaciones al objetivo Work | Composición sin recording/ISRC/UPC/ISWC; strings opcionales; lists/counts/binding/proyección aislados; editar no concede visibilidad de todo; nuevo objetivo conserva CHECK de grant único |
| C2 / C1 | Recording, vínculo N:M con Work, créditos y ArtistProfile solo/group/project | Una grabación admite varias obras y una obra varias grabaciones; grupo con varias TenantParties; permiso del artista no se propaga por coincidencia de nombre; grants tipados por recording/perfil |
| C3 / C2 | Release y ocurrencias ordenadas de recordings | Recording reutilizable; orden por disco/posición único en release; FKs de todos los pivots coherentes con tenant; identificadores opcionales sin fugas por unicidad global |
| P2 / P1, C2, D1 | Relaciones de representación, afiliaciones y evidencia tipada poseída por Parties | Manager con facultad/vigencia y autorización de aplicación independientes; no acceso automático para miembros de una organización ni propiedad por relación profesional |

O1 entrega antes de D1 la política de capacidad: medición actual de cuota/inodos compartidos, límites por archivo/tenant, reservas, streams/temporales, logs, backups/retención, cifrado de datos/documentos y mecanismo de escaneo disponible en Kroma. Define gestión de claves, restauración y denegación de nuevas subidas al agotarse el margen. No se borran evidencias para recuperar espacio. Si falta esta dependencia, D1 puede desarrollarse con fixtures locales; uploads reales siguen deshabilitados y D1 no se cierra operativamente.

Cada nuevo objetivo grant/invitación incorpora FKs reales, CHECK de exactamente uno, validación de misma custodia, policy y prueba de campo/versión/revocación. No se usa un tipo+ID libre ni se crean tablas de split/contrato por adelantado.

Cierre del hito: P1/D1/G1/C1–C3/P2; pruebas con editorial sin grabación, artista independiente y equipo de sello; invitado con documento/obra autorizados sin acceso a todo el catálogo. Matriz A/B completa para entidades existentes, incluidos archivos y trabajos asíncronos.

## 7. Hito 3 — Derechos, splits y colaboración

| ID / dependencias | Entrega | Criterios de cierre |
|---|---|---|
| R1 / hito 2, decisiones de alcance de derechos | Declaraciones de composición/master, autoría, ownership, administración y cobro; evidencia tipada | NUMERIC(9,6), base/derecho/territorio/vigencia explícitos; PR/MR/SR distinguibles; administración referencia interés/mandante; no duplicación de ownership; borradores incompletos no se activan |
| R2 / R1 | SplitSet, SplitVersion, participantes congelados, propuesta/publicación/aceptación y activación | Totales exactos según base; sin floats/normalización; locks y concurrencia probados; versiones publicadas/aceptadas/firma pendientes inmutables; v2 draft no archiva v1 efectiva; intervalos equivalentes sin solapamientos |
| R3 / R2, G1 | Grants/invitaciones a SplitVersion, vínculo verificado de TenantParty y portal Mis participaciones | Cuenta/destinatario exactos, aceptación atómica; solo participación propia salvo divulgación explícita; no catálogo ajeno; dos publishers colaboran en tenant custodio sin sincronización entre workspaces |

R1/R2 requieren definir en su tarea la naturaleza, base, derechos, territorios, vigencias y prerrequisitos de activación; no se inventan defaults jurídicos/económicos. Una activación que depende de contrato firmado permanece bloqueada hasta hito 4. Activar un split de ownership crea/vincula su fuente de derechos en una transacción; no mantiene dos porcentajes actuales editables. Claims parciales/disputed conservan su estado y procedencia.

Ampliar matriz con 0/100%, precisión decimal, share de share, total inválido, publicación/aceptación/activación concurrentes, sustitución temporal y auditoría de historia. Representación no basta para acceder ni firmar. Una membership revocada no elimina un grant independiente válido, pero tampoco conserva permisos derivados de esa membership.

Cierre: sello con productor externo sin ownership automático, editorial con administración parcial, artista con manager, grupo y dos publishers con divulgación limitada. El portal funciona antes de regalías y no muestra saldos inventados.

## 8. Hito 4 — Contratos, firma y Vault contractual

Precondiciones: hito 3, D1/O1 operativos, jurisdicción/idioma/plantillas revisados y proveedor/método con evidencia y coste admitido. Seleccionar proveedor no autoriza contratarlo ni tratar datos reales. No integrar un simulador como firma de producción.

| ID | Entrega | Criterios de cierre |
|---|---|---|
| K1 | Plantillas versionadas de split sheet composición/master; generación desde snapshots y enlaces de versiones exactas | Validación de inputs; texto revisado fuera del controller; payload/archivo congelados; mismos inputs trazables; cambios crean versión nueva; original separado de firmado/evidencia |
| K2 | ContractParties, solicitudes/destinatarios, representación comprobada, grants ContractVersion/DocumentVersion y revisión del firmante | Cada firmante ve el documento íntegro exacto; no contratos/bancos ajenos; owner/admin no firma por terceros; v1 nunca aprueba v2; archivo privado reautoriza cada descarga |
| K3 | Adapter de firma, webhooks auténticos, deduplicación y reintentos | Tenant resuelto por IDs del proveedor conocidos; eventos repetidos/desordenados no corrompen estados; checksums/provider IDs/evidencias separados; fallo recuperable visible; signed no activa derechos automáticamente |

Cierre: recorrido completo en entorno de pruebas admitido por el proveedor, denegaciones de firmante equivocado, callbacks adversos, versionado/retención y descarga de evidencia. Activación valida los prerrequisitos de cada acuerdo aparte de completar firma. No afirmar validez legal universal.

## 9. Hito 5 — Registration y primer CWR

Precondiciones: catálogo/derechos y evidencia aplicables, receptor/versión/perfil oficial, identificador de remitente admitido y fixtures sintéticos acordados. Registro de composición no exige universalmente grabación, ISRC/UPC, ISWC ni contrato que el proceso no requiera.

| ID | Entrega | Criterios de cierre |
|---|---|---|
| T1 | Readiness por destino y versión de reglas; expedientes y seguimiento manual | Blockers prevalecen sobre progreso; cambios invalidan nuevas operaciones; snapshot histórico se conserva; submitted/acknowledged/accepted requieren evidencias distintas |
| T2 | Mapper/validator/generator CWR del único perfil inicial, export privado e inmutable | Especificación oficial y receiver requirements versionados; bytes/encoding/headers/GRT/trailers/counts/territorios/partes/shares/campos requeridos probados; output determinista; casos no soportados fallan explícitamente |
| T3 | Validación independiente y revisión con receptor; trazabilidad del resultado | Fixture/export revisado con evidencia y correcciones; export no equivale a envío ni a aceptación; pruebas locales no se presentan como aceptación externa |

Revalidar autoridad en el job y comprobar cambios de inputs al generar; un assessment obsoleto no autoriza el export. Usar inputs congelados con criterio explícito de vigencia. SFTP, ACK automático y otros perfiles siguen fuera de esta iteración.

## 10. Hito 6 — Staging y validación del MVP

Preparar staging aislado con datos sintéticos y artefactos reproducibles solo tras resolver Kroma. Documentar configuración, migraciones, persistencia de storage entre releases, reinicio de workers, scheduler, SMTP/TLS, logs/alertas, capacidad, backup/restore y rollback compatible con versiones de BD. No supone un despliegue autorizado por este plan.

Pruebas de cierre:

1. Sello con productor externo: crédito/ingreso no implica ownership ni acceso total.
2. Editorial con obra no grabada y administración parcial: sin pools/identificadores inventados.
3. Artista independiente con manager delegado y revocación de autoridad.
4. Misma cuenta con varios espacios y roles; cambio y jobs sin contexto residual.
5. Grupo con varios integrantes y contrapartes sin cuenta propia.
6. Dos publishers con acceso limitado a versiones y documento exacto a firmar.
7. Descargas, búsquedas/conteos, exports y cambios denegados entre tenants; expiración/replay/escalamiento y concurrencia cubiertos.
8. Restauración de BD, archivos privados y claves en un entorno autorizado; medir RPO/RTO y comprobar coherencia de evidencia/hashes.

Cerrar con defectos críticos resueltos, límites conocidos aceptados, operación/capacidad medidas, políticas de datos/soporte/retención aprobadas y responsable de cada procedimiento. La producción necesita instrucción específica y ambiente intencionalmente configurado. No se declara validado todo el producto con un solo cliente.

## 11. Decisiones pendientes, responsables y momento

Los responsables son funciones, sin asumir nombres ni disponibilidad de equipo. Al abrir cada issue se asigna una persona y se estima esa entrega. Un bloqueo afecta su dependencia, no convierte todo el roadmap en trabajo detenido.

| Decisión / evidencia | Responsable de resolver | Debe existir antes de |
|---|---|---|
| Compatibilidad, región, permisos, workers, BD/Redis, rutas y cuota Kroma | Titular de cuenta con proveedor; desarrollo evalúa impacto | Cierre de Foundation y cualquier despliegue dependiente |
| Límites/capacidad, escaneo, cifrado/claves, logs, backups y restauración | Operación/desarrollo con titular | O1/D1 y datos/archivos reales |
| Verificación de vínculo personal y facultad de representante | Producto con revisión de seguridad/jurídica aplicable | Habilitar cada claim de identidad/organización; sin asociación automática |
| Alcances delegables, MFA y proyecciones de divulgación | Producto/desarrollo | F2 para gobierno; G1/R3/K2 para recursos concretos |
| Base/tipo/derecho/territorio/vigencia y reglas de activación | Producto con revisión de contenido jurídico aplicable | Cada operación R1/R2 |
| Jurisdicciones, plantillas y proveedor/método de firma | Producto/titular con revisión jurídica y técnica | K1–K3 |
| Receptor, versión CWR, remitente, capacidad y fixtures | Producto con receptor; desarrollo valida | T2; aceptación externa requiere T3 |
| Retención, soporte, residencia, protección de datos y RPO/RTO | Titular/operación con asesoría aplicable | Piloto con datos reales |
| Contenido público vigente, rutas de legado y datos comerciales/legales | Titular con desarrollo | PUB1; revisión antes de publicación |
| Convivencia de la réplica y compra existente, destinos de contacto/búsqueda y tracking | Titular/operación con desarrollo y proveedor | PUB2/PUB3; conservar compra actual, sin pagos propios |
| Tiempo disponible del equipo y tamaño de entregas | Titular/equipo | Compromisos de calendario; reestimar después de Foundation |

Coedición/sincronización entre workspaces no es una decisión pendiente para poder comenzar: queda fuera del MVP. Incorporarla exigiría una decisión nueva sobre tenancy/visibilidad. No existen obligaciones de comprar otro servidor, S3 u otro servicio por la aprobación de este plan.

## 12. Orden de ejecución y siguiente entrega

**PUB1 está en ejecución**, acotado por su [paquete de trabajo](implementation/pub1-public-site.md). Main f732ecb contiene G1 (PR #12, aca2ad7) y el plan/referencia públicos (PR #13, f732ecb), con CI aprobado de la combinación. PUB1.1–PUB1.2 están preparados para revisión en feat/public-institutional-site, sin integrar ni publicar; la próxima implementación es PUB1.3 (seis servicios y guía). PUB0 está completado como inspección y referencia.

### 12.1. Secuencia de trabajo

| Orden | Trabajo | Resultado que habilita el siguiente paso |
|---|---|---|
| 1 | Completado: integrar G1 y el plan público, PRs #12 y #13 | Main f732ecb conserva el acceso privado, ADR 0002, PUB0–PUB3 y la compra existente; conflicto documental resuelto y CI de la combinación aprobado |
| 2 | Implementar PUB1 desde ese main, en feat/public-institutional-site | Layout/marca, portada, seis servicios, guía, contacto, listado/tres productos y tres legales; revisión local de dieciséis páginas, sin nueva tienda ni cambios del sitio actual |
| 3 | Implementar las partes definidas de PUB2 | Navegación, acceso, consentimiento y enlaces comprobados; conectar contacto/búsqueda solo con destino real autorizado. Conservar la compra existente; las dependencias no resueltas quedan registradas por recorrido |
| 4 | Ejecutar la validación local de PUB3 | Comparación desktop/móvil, accesibilidad, SEO, rutas y privacidad aprobadas; lista explícita de pendientes operativos para publicar |
| 5 | Retomar C1, sin esperar el despliegue | Work/composición y créditos locales, incluyendo obras sin recording; grants tipados y políticas ampliados sobre el recurso real |
| 6 | Continuar C2 → C3 → P2 y después R1–R3 | Recording/vínculos, releases, representación/evidencia y finalmente derechos/splits según dependencias y decisiones de cada tarea |
| 7 | Continuar K1–K3 y T1–T3 cuando existan sus decisiones | Contratos/firma con contenido/proveedor definidos y Registration/CWR con receptor/perfil oficial; no usar su planificación como autorización para implementar todo |

O0/O1 avanzan en paralelo. Si alguna integración PUB2 o el cierre operativo PUB3 queda pendiente por soporte, correo o enrutamiento, se termina lo independiente, se conserva el estado pendiente y se avanza con C1. La prioridad de interfaz pública no convierte el despliegue en una dependencia del catálogo. Las puertas de hitos 4–6 siguen vigentes.

### 12.2. Primer paquete: cómo ejecutar PUB1

1. Inventariar y versionar recursos de marca; preparar layout, navegación y mapa de rutas.
2. Construir la portada y compararla con las capturas antes de extender la plantilla.
3. Completar servicios y guía con sus acordeones.
4. Completar presentación de productos, contacto y legales, con destinos existentes o dependencias documentadas; no publicar acciones incompletas como funcionales.
5. Verificar el conjunto con datos sintéticos, pruebas de aislamiento aplicables, checks reales de frontend/backend y revisión visual. Abrir un PR enfocado que muestre evidencia y límites; integrar antes de ampliar alcance.

El [paquete PUB1](implementation/pub1-public-site.md) contiene las rutas, pasos PUB1.1–PUB1.5, criterios de aceptación y exclusiones. No hace falta decidir firma, CWR o compatibilidad completa de Kroma para trabajar este layout local. Los precios/textos legales y contenido heredado necesitan revisión del titular antes de publicación, sin atribuirles validez por estar en una captura.

### 12.3. Pendientes operativos y su impacto

| Pendiente | Quién aporta la evidencia | Qué bloquea | Qué puede continuar |
|---|---|---|---|
| PostgreSQL/Redis asignados, endpoints, límites y workers de Kroma | Titular/proveedor; desarrollo contrasta | Cierre O0 y despliegue de la aplicación | Desarrollo y pruebas locales con PostgreSQL/Redis |
| Cuota/inodos, ruta privada, escáner, capacidad y retención | Titular/proveedor con desarrollo | Cierre O1, adaptadores reales y cargas de archivos reales | G1/catálogo con fixtures; réplica de recursos públicos |
| Transporte/destino de contacto y búsqueda de dominios | Titular/proveedor con desarrollo | Habilitar esos recorridos PUB2 en operación | Navegación, páginas, endpoints sintéticos de prueba y otros recorridos reales ya definidos |
| Convivencia de rutas y compra WordPress existente | Titular/operación con desarrollo | Reemplazar el sitio público o su raíz de dominio | Réplica local y enlaces de referencia; no mover ni modificar la tienda |
| Contenido comercial/legal vigente y rutas heredadas | Titular con revisión de contenido aplicable | Publicar textos definitivos y ejecutar redirecciones/retiradas | Maquetación basada en la referencia y lista de diferencias pendientes |
| Copias, claves y restauración comprobada | Titular/operación con desarrollo | Preparación operativa, piloto con datos reales y recuperación | Verificación técnica local con fixtures, sin afirmar recuperabilidad de Kroma |

DOCUMENT_UPLOADS_ENABLED permanece false. No se adquieren servicios, cambian motores/hosting, usan datos privados reales ni despliega por seguir este orden. Conservar compra actual significa mantener su operación real y sus destinos verificados, no implementar cobros propios ni copiar identidades comerciales al SaaS.

### 12.4. Regla de seguimiento

Cada entrega identifica tres estados distintos: **validada localmente**, **integrada en main** y **habilitada en operación**. Las evidencias y pruebas pertenecen al commit ejecutado; una captura no cierra un formulario y una suite local no cierra Kroma. Al iniciar la siguiente entrega se actualiza esta tabla, sin porcentajes subjetivos ni fechas prometidas sin capacidad de equipo acordada.

Los avisos de herramientas P0 y el rollback histórico users/sessions conservan su seguimiento; no se reescriben migraciones compartidas. Contracts, royalties, pagos propios y recursos sin contexto/policies continúan fuera del trabajo inmediato.

Cada issue toma uno de los IDs anteriores y consigna objetivo, dependencias, campos/recursos visibles, estados, migraciones, pruebas de aceptación, decisiones requeridas y exclusiones. Dividirlo si no permite revisión enfocada; completar backend/UX/pruebas del mismo comportamiento antes de abrir otro frente. Este documento no crea automáticamente issues ni presupone que existan.

Al cerrar una entrega, actualizar su estado con PR/commit, checks ejecutados y límites; mantener las decisiones pendientes explícitas y revisar las próximas dependencias. Separar cerrado técnicamente de pendiente operativo cuando aplique; no usar porcentajes subjetivos de avance ni convertir una referencia histórica de plazos en compromiso.
