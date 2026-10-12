# Ascanio Publishing Platform

Plataforma SaaS B2B/B2C multi-tenant para la gestión integral del negocio musical.

> **Estado actual:** inicio de desarrollo / greenfield  
> **Producto:** independiente y agnóstico a cualquier sello, editorial, manager o artista específico  
> **Arquitectura objetivo:** monolito modular, API-first y multi-tenant desde el día 1

---

## Visión

Ascanio Publishing es una plataforma nueva e independiente diseñada para convertirse en un sistema operativo de gestión musical.

No se desarrolla para adaptarse a Dilo Records ni a una organización específica. La intención es que distintos actores de la industria puedan adoptar Ascanio como su plataforma central de operación, incluyendo:

- sellos discográficos;
- editoriales / music publishers;
- empresas de management;
- managers independientes;
- artistas independientes;
- artistas con equipo propio;
- productores;
- compositores;
- equipos de publishing;
- otras organizaciones y profesionales de la industria musical.

Cada organización o profesional podrá operar dentro de su propio espacio, configuración, permisos, branding y datos, utilizando el mismo núcleo de producto.

La plataforma debe modelar conceptos universales de la industria musical y permitir configuraciones por tenant cuando existan diferencias operativas.

---

## Principio de producto

Ascanio debe organizar el ciclo de vida de la información musical de forma que un mismo dato pueda reutilizarse en todos los procesos posteriores.

```text
Identidad / Organización
        ↓
Artistas
        ↓
Obras / Composiciones
        ↓
Grabaciones / Masters
        ↓
Releases
        ↓
Colaboradores / Interested Parties
        ↓
Derechos y Splits
        ↓
Contratos y Firmas
        ↓
Metadata Readiness
        ↓
Registros / CWR
        ↓
Royalties / Finanzas
        ↓
Booking / CRM / Marketing / Sync
        ↓
Analytics / Automatización / IA
```

El objetivo es evitar duplicación de información y transformar procesos complejos de la industria en flujos guiados.

---

## Tipos de tenant previstos

El modelo no debe asumir que todos los tenants son sellos.

Como mínimo debe poder representar escenarios como:

```text
Record Label
Music Publisher
Management Company
Independent Manager
Independent Artist
Artist Team / Company
Hybrid Music Company
```

El tipo de tenant podrá influir en onboarding, módulos visibles, configuración y permisos, pero no debe crear aplicaciones separadas ni bifurcaciones de código por cliente.

---

## Usuarios y colaboración

Un usuario puede participar en más de una organización y tener roles diferentes en cada una.

Ejemplos:

```text
Usuario A
├── Editorial X → Publisher Admin
├── Sello Y → Composer / Collaborator
└── Proyecto propio → Independent Artist
```

La arquitectura debe soportar relaciones entre organizaciones, artistas, autores, publishers, managers y colaboradores sin asumir que una persona pertenece exclusivamente a un tenant.

---

## Objetivo del MVP

El MVP debe demostrar que una organización o profesional de la industria puede gestionar de extremo a extremo el núcleo legal, editorial y de metadata de una obra musical.

### Alcance funcional inicial

1. Multi-tenancy.
2. Onboarding y configuración de organización.
3. Usuarios, memberships, roles y permisos.
4. Branding configurable.
5. Artistas.
6. Obras y composiciones.
7. Grabaciones / masters.
8. Releases.
9. Colaboradores / interested parties.
10. Splits de composición.
11. Splits de master.
12. Invitaciones para completar información faltante.
13. Generación de contratos y split sheets.
14. Firma e historial documental.
15. Document Vault.
16. Metadata Readiness.
17. Registration Center.
18. Generación y validación de CWR.

### Flujo esperado al cerrar el MVP

```text
Crear organización
    ↓
Configurar equipo y permisos
    ↓
Crear / vincular artista
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
Solicitar información faltante
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

Este flujo debe funcionar independientemente de si quien lo utiliza es un sello, una editorial, un manager o un artista independiente.

---

## Referencias funcionales y producto previo

### Dilo Records

El proyecto previo de Dilo Records existe como referencia técnica y funcional:

- repositorio: `san7imo/dilo-deploy`;
- ruta local de referencia: `/home/san7imo/Escritorio/Proyectos/dilo-deploy`.

Puede consultarse para:

- identificar componentes reutilizables;
- revisar soluciones ya implementadas;
- rescatar lógica genérica útil;
- reutilizar patrones, componentes o código cuando sea apropiado;
- entender aprendizajes obtenidos durante el desarrollo anterior.

**No debe utilizarse como arquitectura objetivo ni como modelo de negocio de Ascanio.**

Todo código reutilizado debe revisarse, desacoplarse de Dilo, adaptarse a los dominios de Ascanio y cumplir las reglas de multi-tenancy, seguridad, testing y calidad del nuevo proyecto.

### Referencias de UX

Los reels analizados previamente sirven como referencia para flujos guiados de:

- splits;
- contratos musicales;
- incorporación de colaboradores;
- solicitud de datos faltantes;
- generación de documentos;
- experiencia simple para usuarios no técnicos.

La intención es adoptar los principios de UX útiles, no copiar una aplicación externa ni limitar Ascanio a esos flujos.

---

## Roadmap general

### Fase 0 — Discovery y arquitectura
- modelo de dominio universal;
- arquitectura;
- tipos de tenant;
- modelo de memberships;
- revisión de infraestructura;
- permisos;
- flujos principales;
- backlog;
- wireframes del MVP;
- evaluación de código reutilizable de proyectos previos.

### Fase 1 — SaaS Foundation
- tenancy;
- onboarding;
- autenticación;
- memberships;
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
- organizations;
- industry identifiers.

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
    ├── Organizations/
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

### Independencia del producto

Ningún cliente, tenant o proyecto previo define el modelo central de Ascanio.

Las reglas base deben representar conceptos reales de la industria musical.

Las diferencias entre clientes deben resolverse preferentemente mediante:

- configuración;
- permisos;
- módulos habilitados;
- plantillas;
- branding;
- feature flags cuando estén justificadas.

No mediante forks ni condiciones hardcodeadas por cliente.

### Multi-tenancy

Toda entidad perteneciente a una organización deberá estar aislada por tenant cuando corresponda.

Un usuario de un tenant nunca debe poder acceder a información privada de otro tenant sin autorización explícita y un caso de negocio válido.

### Relaciones entre actores

No toda entidad musical pertenece conceptualmente a una única organización.

El diseño debe distinguir entre:

- identidad global / reusable party;
- acceso y ownership dentro de un tenant;
- relaciones contractuales;
- participación en obras;
- representación / management;
- publishing;
- ownership de master.

Esto evita duplicar personas y organizaciones innecesariamente y permite colaboración entre actores de distintos tenants.

### Composition vs Master

Los derechos de composición y los derechos del master son conceptos diferentes.

Nunca deben mezclarse en un único porcentaje o modelo.

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

Los datos de autores, compositores, publishers, artistas y colaboradores deben poder reutilizarse según permisos y contexto.

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

Ascanio debe ser utilizable tanto por equipos profesionales como por artistas independientes.

Los procesos complejos deben presentarse mediante flujos guiados.

```text
Paso 1
¿Qué quieres hacer?

Paso 2
Información de la obra / grabación

Paso 3
Participantes

Paso 4
Roles y porcentajes

Paso 5
Validación

Paso 6
Contrato / registro
```

Cuando un usuario requiera mayor profundidad, la plataforma podrá ofrecer vistas o campos avanzados sin convertir la experiencia inicial en un formulario técnico masivo.

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

La infraestructura inicial podrá utilizar el servidor administrado por Kroma, sujeto a validación técnica.

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
2. definir modelo de dominio universal;
3. definir tipos de tenant y memberships;
4. definir tenancy;
5. definir RBAC;
6. inicializar Laravel + Vue + TypeScript;
7. preparar testing y CI;
8. comenzar SaaS Foundation.

---

## License

Private/commercial project. Licensing terms will be defined by Ascanio Publishing.
