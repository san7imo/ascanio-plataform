# ADR 0001 — Orden de implementación de acceso y evidencia

Fecha: 14 de noviembre de 2026.
Estado: adoptado para la planificación; implementación pendiente.

## Contexto

El roadmap original de [README](../../README.md) §12 incluía todos los grants en Foundation, antes de crear sus objetivos. Su §9 exige que cada grant referencie exactamente un recurso real mediante FKs tipadas. Implementar todos esos grants primero obligaría a crear tablas vacías de módulos futuros o a sustituir la integridad por un ID genérico.

El mismo roadmap situaba Documents/Vault en el hito 4. Sin embargo, los derechos y relaciones de representación del hito 3 necesitan evidencia documental con integridad y autorización. No deben guardar archivos públicos ni referencias arbitrarias mientras esperan al módulo contractual.

## Decisión y alcance de la sustitución

Se ajusta el roadmap existente y se mantiene su numeración de hitos. Este ADR sustituye exclusivamente:

1. La exigencia de completar todos los grants de recursos dentro del hito 1 de la versión de README en `64858b8`.
2. La interpretación de que todo Documents debe esperar al hito 4 de ese roadmap.

Foundation implementa cuenta/contexto válidos, identidad global mínima, memberships, capacidades, alcance de roles, policies, invitaciones de equipo, revocación, auditoría y ejecución de jobs. Las capacidades para recursos aún inexistentes no habilitan operaciones. El alcance `assigned_resources` deniega acceso mientras no exista una asignación concreta autorizada.

IdentityAccess incorpora grants con FKs compuestas tipadas a medida que existen objetivos: documentos y catálogo en hito 2, SplitVersions en hito 3, ContractVersions en hito 4. Cada ampliación actualiza el CHECK de objetivo único, las policies, las consultas/proyecciones y sus pruebas. No se habilita acceso externo a un tipo de recurso antes de completar esas comprobaciones. La misma regla rige para invitaciones a recursos/versiones.

Documents entrega un núcleo en hito 2: versiones privadas, checksum, estado de archivo, carga controlada y descarga autenticada. Rights y Parties poseen sus propios enlaces documentales tipados cuando requieren evidencia. Documents no depende de Contracts ni de Rights. Las cargas permanecen deshabilitadas hasta comprobar límites, cuota, análisis de malware y almacenamiento admitido. La generación de contratos y el Vault de originales, firmados y evidencias del proveedor permanecen en hito 4.

## Consecuencias

- No se declara Foundation completa por tener únicamente login y scaffold. Su cierre requiere las pruebas de aislamiento de sus entidades reales y la comprobación operativa de Kroma.
- El acceso externo y sus pruebas se entregan antes de cerrar el hito 2; el portal de participaciones sigue en hito 3. No se difiere la autorización hasta después de publicar recursos.
- Las migraciones agregan solamente objetivos existentes; no crean un esquema completo especulativo.
- Datos incompletos pueden permanecer en borrador. Una operación que exige evidencia no se activa sin ella.
- La matriz de pruebas crece con cada recurso. Las descargas y exports se prueban cuando existen; no se presentan pruebas de memberships como evidencia de aislamiento de contratos todavía inexistentes.

No se modifica la estrategia compartida PostgreSQL/tenant_id, la identidad global mínima, la custodia por tenant, la integridad tipada, las reglas de derechos, los límites del MVP ni el destino Kroma. La división de responsabilidades de [AGENTS.md](../../AGENTS.md) y README continúa vigente. Los detalles ejecutables están en el [plan de implementación](../implementation-plan.md).
