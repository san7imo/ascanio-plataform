# Auditoría del repositorio y siguiente ejecución

Fecha: 15 de noviembre de 2026 (America/Bogota). Base funcional: `95e3618`, igual en `main` y `origin/main` al comenzar esta revisión. Alcance: documentación, ramas, estado funcional y comprobación local de la base integrada. Sin operaciones en Kroma.

## Decisión

Conservar el [plan existente](../implementation-plan.md), actualizar sus estados y precisar F2. El roadmap mantiene las dependencias correctas con el [ADR 0001](../adr/0001-implementation-sequencing.md): Foundation primero, Documents antes de evidencia de Rights y grants tipados cuando existen sus objetivos. Rehacerlo desde cero no resuelve una carencia detectada.

La base usable hoy es cuenta global más primer recorrido multi-tenant. Foundation aún no está completa y el MVP musical no está implementado. El siguiente trabajo funcional es terminar F2 en una rama nacida de `main`, con backend, UX y pruebas de su mismo alcance. Este informe no autoriza desplegar ni implementar todos los hitos.

## Fuentes revisadas y límites

Se revisaron los ocho Markdown versionados de la base: [README](../../README.md), [AGENTS](../../AGENTS.md), [THIRD_PARTY_NOTICES](../../THIRD_PARTY_NOTICES.md), [plan](../implementation-plan.md), [ADR 0001](../adr/0001-implementation-sequencing.md), [Kroma](../hosting/kroma-compatibility.md), [P0](../security/dependency-maintenance.md) y [F1](f1-workspace-foundation.md). Se inventariaron sus versiones en todas las ramas locales/remotas disponibles y se compararon las referencias de revisión con sus entregas remotas.

También se comprobaron historial, ascendencia de ramas, los cinco PRs cerrados/integrados, CI del head F1, scripts reales, rutas, acceso por contexto, políticas, acciones y pruebas de workspaces, y migraciones de cuenta/IdentityAccess. Es una auditoría de avance y preparación de ejecución; no una auditoría exhaustiva de seguridad del producto ni del servidor.

Los documentos originales PRD/comercial/funcional y reels citados históricamente no están versionados aquí; no se presentan como releídos. El informe Kroma contiene evidencia histórica del 12 de octubre, no una medición actual. No se consultó el repositorio Dilo para reutilización ni se incorporó su código.

## Ramas y merges

Se ejecutó `git fetch origin --prune` y se comprobó ascendencia mediante `git merge-base --is-ancestor`. Todas las ramas remotas de entrega son ancestros de `95e3618`; ninguna aporta commits remotos pendientes a esa base.

| Rama remota / head | Estado comprobado | Tratamiento |
|---|---|---|
| `docs/domain-tenancy-architecture` / `882b37f` | [PR #1](https://github.com/san7imo/ascanio-plataform/pull/1) integrado | Conservar referencia; no repetir merge |
| `feat/technical-foundation` / `64858b8` | [PR #2](https://github.com/san7imo/ascanio-plataform/pull/2) integrado | Conservar referencia; scaffold en main |
| `docs/implementation-plan` / `2c95ac6` | [PR #3](https://github.com/san7imo/ascanio-plataform/pull/3) integrado | Ajustar el plan integrado |
| `fix/dependency-security` / `344787c` | [PR #4](https://github.com/san7imo/ascanio-plataform/pull/4) integrado | Mantener auditorías y seguimientos |
| `feat/workspace-foundation` / `cfb2b56` | [PR #5](https://github.com/san7imo/ascanio-plataform/pull/5) integrado | F1 es la base de F2 |
| `main` / `95e3618` | Local y remoto coinciden al comenzar | Base común; no crear develop |

Las ramas locales de entrega anteriores apuntan a los mismos heads que sus remotas. `review/implementation-plan-local`, `review/dependency-security-local` y `review/workspace-foundation-local` tienen SHAs diferentes pero árboles idénticos a sus entregas remotas correspondientes. `review/technical-foundation-local` solo difiere de su entrega remota en README/AGENTS, anteriores a la decisión de almacenamiento Kroma. No deben fusionarse para duplicar entregas o restaurar documentación antigua. No se borraron ramas ni se reescribió historia.

Al retomar existían 13 archivos modificados/nuevos de F2 en `feat/workspace-governance`, sin commit ni verificación completa. Se preservaron en el commit local `6310877` de esa rama y en el stash `510fd0f` (también sus archivos nuevos). La rama permanece local y fuera de `main`; el stash es copia de respaldo, no debe aplicarse otra vez sobre el mismo commit.

Ese borrador agrega un catálogo y delegabilidad, confirmación sensible, acciones de roles y una migración de upgrade. Le faltan transferencia/estados/asignaciones completos, requests/controladores/rutas, interfaz y pruebas F2. El commit de preservación no afirma que funcione ni que su migración esté lista. No ejecutar ni fusionar ese upgrade por el hecho de existir.

## Estado real y brechas

| Área | Entregado | Trabajo pendiente |
|---|---|---|
| Cuenta y base técnica | Fortify, correo verificado, MFA de cuenta, PostgreSQL/Redis, CI y lockfiles | Completar traducciones del scaffold y seguimientos de tooling |
| F1 multi-tenant | Alta/selección de espacios, identidad personal mínima, membership propietaria, cuatro capacidades iniciales, aislamiento de consultas/props y auditoría | No hay administración completa de roles, transferencia ni invitaciones |
| Revocación | Contraseña actual, efecto inmediato y membresías independientes; protección de último propietario | Añadir condiciones MFA/gobierno de F2; no confundir MFA de cuenta con MFA ya exigido por estas mutaciones |
| Auditoría | Registro transaccional y triggers contra UPDATE/DELETE/TRUNCATE normales | Probar una conexión runtime realmente restringida; roles de producción pendientes |
| Persistencia | Migración F1 aditiva y constraints PostgreSQL; prueba de rollback/reaplicación F1 | Recuperación del scaffold histórico: su down elimina users antes de sessions, que tiene FK a users |
| Operación | Entorno local/CI verificable | Kroma: PHP/BD/Redis/workers/permisos, storage privado, SMTP, capacidad y backups/restauración sin confirmar |
| Dominio musical | Arquitectura y criterios documentados | Parties locales, documentos, catálogo, derechos/splits, contratos/firma y Registration/CWR |

Los resultados iniciales del plan (43 tests y avisos de dependencias) eran históricos; su redacción podía confundirse con el estado actual. El plan ahora distingue ese diagnóstico de F1 integrada y de las auditorías repetidas. P0 ya incorporó el job dependency-audit; no es una tarea nueva pendiente. La protección de rama que lo exige no se comprobó ni configuró en esta revisión.

## Orden de ejecución

1. **F2:** revisar y completar el borrador contra el desglose F2.1–F2.6 del plan. Delimitar capacidades delegables, proteger gobierno, confirmar mutaciones sensibles y probar transferencias/revocación concurrentes; cerrar con UI y rol PostgreSQL restringido. Rebasar o integrar la documentación actual antes de retomarlo, sin force-push compartido.
2. **F3:** invitaciones de equipo con destinatario verificado, tokens de un uso, expiración/revocación, aceptación atómica y autoridad revalidada. Añadir notificaciones tras commit; sin grants a recursos inexistentes.
3. **F4:** jobs con tenant/solicitante explícitos, limpieza de contexto y revalidación tras revocación; completar traducciones y regresión integrada de Foundation.
4. **O0 en paralelo y O1 antes de uploads:** obtener evidencia del proveedor/cPanel sin asumir disponibilidad del stack. Resolver cuota, privilegios, persistencia privada y restauración antes del piloto o despliegue dependiente.
5. **Hito 2 y siguientes:** Parties/documentos/grants/catálogo → derechos y splits → contratos/firma → Registration/CWR → staging y validación. Cada entrega conserva las puertas del plan, sin adelantar el MVP financiero.

No hay fundamento para asignar un porcentaje global ni una fecha de lanzamiento: faltan capacidad del equipo y decisiones operativas, de firma y del receptor CWR. Cada entrega debe tener responsable y estimación al iniciarla; el tamaño se valida después de F2 con tiempo real de ejecución/revisión.

## Verificación de esta revisión

Ejecutado con PHP 8.4.18, Node 22.17.0, npm 10.9.2 y PostgreSQL/Redis locales:

- `composer run ci:check`: 70 pruebas / 472 aserciones aprobadas, Pint, ESLint, formato, tipos, una prueba de tooling y auditorías Composer/npm sin avisos. La suite incluye tres carreras con procesos PostgreSQL independientes y rollback/reaplicación de F1.
- `npm run build` con `PHP_BINARY=php8.4`, `composer validate --strict` y `composer check-platform-reqs`: aprobados. Los archivos web y lockfiles corresponden a la base integrada; el borrador F2 no añade UI ni dependencias.
- CI remoto del head F1 `cfb2b56`: [ejecución 43678589603](https://github.com/san7imo/ascanio-plataform/actions/runs/43678589603) aprobada en `verify (8.3)`, `verify (8.4)` y `dependency-audit`, comprobado mediante GitHub.
- Revisión documental, enlaces relativos y `git diff --check` para los cambios de este informe/plan.

Las pruebas se limitaron a `ascanio_test` y claves/colas Redis sintéticas; no se aplicó la migración F2 ni una migración de producción. Sin validación visual manual nueva, sin cargas/descargas privadas de módulos futuros, firma ni CWR. Las pruebas de F1 no acreditan F2. Los servicios Compose utilizados se detienen conservando volúmenes al terminar esta revisión.
