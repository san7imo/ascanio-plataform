# Ascanio Publishing Platform

Plataforma SaaS B2B multi-tenant para la gestión integral de artistas, obras musicales, derechos, contratos, registros, regalías y operaciones asociadas a la industria musical.

> **Estado actual:** inicio de desarrollo / greenfield  
> **Primer tenant de validación:** Dilo Records  
> **Arquitectura objetivo:** monolito modular, API-first y multi-tenant desde el día 1

---

## Visión

Ascanio Publishing no es una nueva versión de Dilo Records.

Es una plataforma nueva e independiente, diseñada para funcionar como producto SaaS para sellos, editoriales, managers y artistas. Dilo Records será el primer tenant real y servirá para validar los flujos del producto antes de incorporar nuevos clientes.

La idea central es registrar la información musical una sola vez y reutilizarla a lo largo de todo el ciclo operativo:

```text
Artista
  ↓
Obra / Composición
  ↓
Grabación / Master
  ↓
Colaboradores
  ↓
Splits
  ↓
Contratos
  ↓
Firmas
  ↓
Metadata Readiness
  ↓
CWR / Registros
  ↓
Royalties
  ↓
Operación, Analytics e IA
```

---

## Objetivo del MVP

El MVP debe permitir que Dilo Records complete de extremo a extremo el flujo principal de una obra musical dentro de Ascanio.

### Alcance funcional inicial

1. Multi-tenancy.
2. Usuarios, membresías, roles y permisos.
3. Marca blanca básica por tenant.
4. Artistas.
5. Obras y composiciones.
6. Grabaciones / masters.
7. Releases.
8. Colaboradores / interested parties.
9. Splits de composición.
10. Splits de master.
11. Invitaciones para completar información faltante.
12. Generación de contratos y split sheets.
13. Firma e historial documental.
14. Document Vault.
15. Metadata Readiness.
16. Registration Center.
17. Generación y validación de CWR.

### Flujo esperado al cerrar el MVP

```text
Crear artista
    ↓
Crear obra
    ↓
Crear grabación
    ↓
Agregar colaboradores
    ↓
Definir composition split
    ↓
Definir master split
    ↓
Solicitar datos faltantes
    ↓
Generar contrato
    ↓
Firmar
    ↓
Guardar documento
    ↓
Validar metadata
    ↓
Generar CWR
    ↓
Dar seguimiento al registro
```

---

## Roadmap general

### Fase 0 — Discovery y arquitectura
- modelo de dominio;
- arquitectura;
- revisión de infraestructura;
- permisos;
- flujos principales;
- backlog;
- wireframes del MVP.

### Fase 1 — SaaS Foundation
- tenancy;
- autenticación;
- membresías;
- RBAC;
- branding;
- auditoría;
- storage;
- infraestructura base.

### Fase 2 — Music Core
- artistas;
- works/compositions;
- recordings/masters;
- releases;
- contributors;
- identifiers musicales.

### Fase 3 — Rights & Splits
- composition rights;
- master rights;
- split sets;
- versionado;
- validaciones;
- invitaciones a colaboradores.

### Fase 4 — Contract Engine
- templates;
- generación de documentos;
- split sheets;
- producer agreements;
- management agreements;
- work-for-hire;
- publishing agreements;
- firma;
- Document Vault.

### Fase 5 — Publishing & Registration
- Metadata Readiness;
- Registration Center;
- CWR;
- validadores;
- exportación;
- futura integración SFTP.

> Con la Fase 5 se completa el MVP inicial.

### Fases posteriores
- Royalties & Recoupment.
- Portal del artista / EPK.
- Booking, Touring & Ticketing.
- CRM musical.
- Partnerships e inversionistas.
- Sync Licensing.
- A&R.
- Fan CRM & Marketing.
- E-commerce & Merchandising.
- Analytics.
- Copiloto de IA.
- AI Audio Intelligence.
- Valoración de catálogo.
- Cumplimiento y gobernanza.
- Distribución digital.
- API pública y expansión SaaS.

---

## Arquitectura

El proyecto comienza como **monolito modular**.

No se introducirán microservicios sin una necesidad técnica y operativa demostrada.

Dominios previstos:

```text
app/
└── Domains/
    ├── Tenancy/
    ├── Identity/
    ├── Artists/
    ├── Catalog/
    ├── Publishing/
    ├── Rights/
    ├── Documents/
    ├── Contracts/
    ├── Registration/
    ├── Royalties/
    ├── Finance/
    ├── Booking/
    ├── CRM/
    ├── Marketing/
    ├── Sync/
    ├── Commerce/
    └── AI/
```

La estructura podrá evolucionar durante la Fase 0, pero la separación por dominio debe mantenerse.

---

## Stack previsto

### Backend
- PHP 8.3+
- Laravel 12
- Laravel Sanctum / Fortify
- Redis
- PostgreSQL preferentemente
- Queues / Jobs
- Storage privado compatible con S3

### Frontend
- Vue 3
- TypeScript
- Inertia donde tenga sentido
- Vite

### Calidad y desarrollo
- Pest / PHPUnit
- Laravel Pint
- ESLint
- Prettier
- GitHub Actions
- CI/CD
- Staging separado de producción

La selección final de algunas dependencias se hará durante la fase de foundation.

---

## Principios de dominio

### Multi-tenancy

Dilo Records es un tenant, no una condición especial del código.

Toda entidad perteneciente a un cliente deberá estar aislada por tenant cuando corresponda.

Un usuario de un tenant nunca debe poder acceder a información de otro tenant sin autorización explícita de plataforma.

### Composition vs Master

Los derechos de composición y los derechos del master son conceptos diferentes.

Nunca deben mezclarse en un único porcentaje o modelo.

Ejemplo:

```text
Composition
├── Writer A: 50 %
└── Writer B: 50 %

Master
├── Label: 70 %
└── Artist: 30 %
```

### Datos reutilizables

La plataforma debe evitar pedir la misma información varias veces.

Los datos de autores, compositores, publishers y colaboradores deben poder reutilizarse entre obras.

### Versionado

Los cambios jurídicos o económicos importantes no deben destruir el historial.

Ejemplos:
- splits;
- contratos;
- derechos;
- información crítica para liquidaciones.

### Auditoría

Los cambios sensibles deben ser trazables.

---

## CWR

Ascanio incluirá un motor para generar Common Works Registration a partir de los datos estructurados de la plataforma.

El flujo esperado será:

```text
Music Domain
    ↓
CWR Mapper
    ↓
Business Validator
    ↓
CWR Generator
    ↓
Export
```

El motor CWR debe mantenerse aislado del resto de la aplicación y contar con pruebas específicas de formato, posiciones, longitudes, shares y reglas de negocio.

No debe implementarse como una concatenación informal de strings dentro de controladores.

---

## Experiencia de usuario

Los procesos complejos deben presentarse mediante flujos guiados.

Ejemplo:

```text
Paso 1
¿Qué quieres documentar?

Paso 2
Información de la obra

Paso 3
Participantes

Paso 4
Roles y porcentajes

Paso 5
Validación

Paso 6
Contrato / registro
```

Se priorizará una interfaz clara para artistas y usuarios no técnicos sin perder la profundidad requerida por publishers y administradores.

---

## Seguridad

Como mínimo:

- tenant isolation;
- autorización por policies/permissions;
- MFA para accesos sensibles;
- documentos privados;
- URLs firmadas cuando aplique;
- protección de secretos;
- rate limiting;
- logs;
- auditoría;
- backups;
- validación estricta de inputs.

Nunca se deben almacenar secretos en el repositorio.

---

## Infraestructura

La plataforma contará con:

- development;
- staging;
- production.

Los despliegues deben ser reproducibles y automatizables.

El acceso directo a producción no forma parte del flujo normal de desarrollo de Codex ni de otros agentes de IA.

---

## Estructura de documentación

```text
docs/
├── architecture.md
├── domain-model.md
├── roadmap.md
├── permissions.md
├── cwr.md
└── backlog/
```

La documentación debe actualizarse cuando una decisión de arquitectura o negocio cambie de forma relevante.

---

## Estrategia Git

Ramas principales:

```text
main       → producción
develop    → integración / staging
```

Features:

```text
feat/tenant-foundation
feat/tenant-memberships
feat/music-core-artists
feat/music-core-works
feat/composition-splits
feat/master-splits
feat/contract-engine
feat/cwr-generator
```

Se prefieren cambios pequeños, revisables y vinculados a una tarea concreta.

---

## Convención de commits

Se recomienda Conventional Commits:

```text
feat(tenancy): add tenant model
feat(rights): add composition split validation
fix(cwr): correct writer record length
test(tenancy): verify cross-tenant isolation
docs(architecture): document tenant resolution strategy
refactor(contracts): extract template renderer
```

---

## Flujo de desarrollo

```text
Issue / Task
    ↓
Plan
    ↓
Feature branch
    ↓
Implementation
    ↓
Automated tests
    ↓
Code review
    ↓
Pull request
    ↓
Staging
    ↓
Functional validation
    ↓
Merge
```

Para cambios de arquitectura o dominio sensibles se debe planificar antes de escribir código.

---

## Estado del proyecto

El repositorio se encuentra en etapa inicial.

Próximos pasos:

1. completar documentación base;
2. definir modelo de dominio;
3. definir tenancy;
4. definir RBAC;
5. inicializar Laravel + Vue + TypeScript;
6. preparar testing y CI;
7. comenzar SaaS Foundation.

---

## License

Private/commercial project. Licensing terms will be defined by Ascanio Publishing.
