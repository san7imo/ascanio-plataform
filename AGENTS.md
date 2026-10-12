# AGENTS.md — Ascanio Publishing

This file defines the default engineering instructions for AI coding agents working in this repository.

Read this file before planning or modifying code.

---

## 1. Product identity — critical

Ascanio Publishing is a **greenfield, independent, multi-tenant music business platform**.

It is not a rebuild of Dilo Records and it must not be architected around any single company, label, artist, manager or publisher.

The product is intended to become a general operating platform for different actors in the music industry, including:

- record labels;
- music publishers / publishing companies;
- management companies;
- independent managers;
- independent artists;
- artists with their own teams or companies;
- producers;
- songwriters;
- publishing teams;
- hybrid music businesses;
- other music-industry organizations and professionals.

The platform must model industry concepts, not customer-specific workflows.

**Core rule:** customer differences should normally be handled by configuration, roles, enabled modules, templates, branding or justified feature flags — not by hardcoded customer branches.

---

## 2. Dilo Records — reference only

A previous project exists:

- local path: `/home/san7imo/Escritorio/Proyectos/dilo-deploy`
- remote repository: `https://github.com/san7imo/dilo-deploy`

Dilo may be inspected as a **reference implementation** when useful.

It may contain reusable:

- Vue components;
- Laravel patterns;
- database ideas;
- metadata models;
- artist/release/track flows;
- authentication patterns;
- document handling;
- royalty import logic;
- UI patterns;
- utilities;
- tests;
- integrations.

However:

**Dilo Records is not the product model for Ascanio.**

Never copy Dilo architecture blindly.

Before reusing code from Dilo:

1. inspect whether the code is generic;
2. remove Dilo-specific names, assumptions and branding;
3. verify compatibility with Ascanio's domain model;
4. verify tenant isolation;
5. verify authorization;
6. verify security;
7. add/update tests;
8. prefer refactoring into an Ascanio-native abstraction when needed.

Do not introduce a dependency from Ascanio to the Dilo application.

Do not import Dilo database assumptions as universal music-industry rules.

Dilo can become a future tenant or migration source, but Ascanio must remain fully independent.

---

## 3. External product references / reels

Previously analyzed reels show useful UX patterns around:

- guided split creation;
- composition vs master split selection;
- collaborator participation;
- requesting missing collaborator data;
- contract generation;
- music-industry document workflows;
- simple step-by-step UX for non-technical users.

Treat those materials as **product/UX references**, not as an architecture specification and not as a product to clone.

Ascanio should adopt useful principles while extending them into a broader platform.

---

## 4. Product goal

The platform should support a reusable end-to-end music-business workflow:

```text
Organization / Professional
        ↓
Users & Memberships
        ↓
Artists
        ↓
Works / Compositions
        ↓
Recordings / Masters
        ↓
Releases
        ↓
Contributors / Interested Parties
        ↓
Composition Rights
        ↓
Master Rights
        ↓
Splits
        ↓
Contracts / Signatures
        ↓
Document Vault
        ↓
Metadata Readiness
        ↓
Registration / CWR
        ↓
Royalties / Finance
        ↓
Booking / CRM / Marketing / Sync
        ↓
Analytics / Automation / AI
```

The first MVP focuses on the foundation through Registration/CWR.

Do not implement later roadmap modules unless the task explicitly asks for them.

---

## 5. Tenant model philosophy

Do not assume every tenant is a record label.

Expected tenant/business types may include:

```text
record_label
music_publisher
management_company
independent_manager
independent_artist
artist_company
hybrid_music_company
other
```

Do not prematurely hardcode this exact enum without validating the domain model.

Tenant type may influence:

- onboarding;
- default roles;
- visible modules;
- templates;
- terminology;
- billing plan;
- feature configuration.

Tenant type must not create separate applications or forked business logic unless a genuine domain difference requires it.

---

## 6. Cross-organization collaboration

Music workflows frequently cross organizational boundaries.

Examples:

- a writer may collaborate on works managed by multiple publishers;
- an artist may be represented by a manager but signed to a label;
- a producer may work with many labels and artists;
- a publisher may administer only part of a composition;
- one user may participate in multiple tenants with different roles.

Therefore, distinguish carefully between:

- global/reusable identity;
- tenant membership;
- tenant-owned data;
- contractual relationships;
- rights ownership;
- representation;
- participation in a work;
- access permissions.

Do not duplicate a real-world person unnecessarily just because they interact with multiple tenants.

Do not expose private tenant data merely because the same contributor appears elsewhere.

---

## 7. Planned stack

Unless an accepted architecture decision says otherwise:

### Backend
- PHP 8.3+
- Laravel 12
- PostgreSQL preferred
- Redis
- Laravel Sanctum / Fortify
- Queues / Jobs
- S3-compatible private storage

### Frontend
- Vue 3
- TypeScript
- Vite
- Inertia when appropriate

### Quality
- Pest or PHPUnit
- Laravel Pint
- ESLint
- Prettier
- GitHub Actions

Do not replace the agreed stack or introduce an additional framework without explicit approval.

---

## 8. Architecture

Use a **modular monolith**.

Do not introduce microservices, message brokers, event streaming platforms or distributed infrastructure unless explicitly requested and justified.

Target domain boundaries:

```text
Tenancy
Identity
Organizations
Artists
Catalog
Publishing
Rights
Documents
Contracts
Registration
Royalties
Finance
Booking
CRM
Marketing
Sync
Commerce
AI
```

The exact physical directory structure may evolve, but domain boundaries must remain explicit.

Avoid a large unstructured application layer.

---

## 9. Architecture decision rule

Before implementing a substantial or cross-cutting change:

1. inspect existing Ascanio code;
2. inspect relevant docs;
3. when useful, inspect Dilo only as a reference;
4. identify domain boundaries involved;
5. explain the implementation plan;
6. identify files that will change;
7. identify migrations or data compatibility implications;
8. identify security and tenant-isolation implications;
9. verify the design works for more than one tenant/business type;
10. implement only after the approach is coherent.

For small, local and obvious tasks, proceed directly.

Do not perform large opportunistic refactors unrelated to the requested task.

---

## 10. Multi-tenancy — critical rule

Multi-tenancy is a first-class architectural requirement.

Every tenant-owned entity must be explicitly tenant-scoped.

Examples may include:

- private artist management data;
- tenant-specific works/catalog records;
- recordings;
- releases;
- contracts;
- documents;
- royalty statements;
- contacts;
- tasks;
- payments;
- configuration.

Never trust a tenant identifier received from the client without authorization.

Never create code that can accidentally return records from another tenant.

Tenant isolation must be enforced server-side.

### Required tests

Any tenant-scoped feature must include tests proving that Tenant A cannot read or mutate Tenant B private data.

A feature that exposes tenant-owned data without tenant-isolation tests is not complete.

---

## 11. Ownership is not always tenancy

Do not automatically equate:

```text
tenant_id = legal owner
```

A tenant can manage, administer or access an entity without necessarily owning 100% of the underlying legal rights.

Examples:

- publishers administering shares;
- managers representing artists;
- labels controlling master rights;
- collaborators participating in composition rights.

Model legal ownership, administration and platform tenancy as separate concepts when the domain requires it.

This distinction is critical for a platform intended for multiple business types.

---

## 12. User membership model

Do not assume one user belongs to only one tenant.

The design must support a structure conceptually equivalent to:

```text
users
tenants
tenant_memberships
roles
permissions
```

A user can have different roles in different tenants.

Avoid implementing business authorization as a single global `users.role` field.

---

## 13. Authorization

Use policies, permissions or equivalent application-level authorization.

Avoid scattered role-name checks such as:

```php
if ($user->role === 'admin') {
    ...
}
```

Prefer explicit capabilities such as:

```text
catalog.view
catalog.edit
publishing.view
publishing.edit
publishing.export_cwr
contracts.view
contracts.create
contracts.sign
royalties.view
royalties.import
royalties.approve
tenant.manage_users
```

The exact permission catalog belongs in `docs/permissions.md`.

Roles are convenience bundles of permissions, not the source of business truth.

---

## 14. Music domain rules

Use precise music-industry terminology.

Important entities/concepts include:

- Organization
- Artist
- Work
- Composition
- Recording
- Master
- Release
- Contributor
- Writer
- Composer
- Publisher
- Manager
- Producer
- Interested Party
- Performing Rights Organization
- ISRC
- ISWC
- IPI / CAE
- UPC
- Split Set
- Contract
- Registration

Do not use generic names like `Item`, `Thing` or `Data` when a domain term exists.

Do not assume artist, label, publisher and manager are interchangeable roles.

---

## 15. Composition rights and master rights

Composition rights and master rights are different domains.

Never merge them into one generic ownership percentage.

```text
Composition Rights
- writers
- composers
- publishers
- publishing shares

Master Rights
- master owner
- label
- artist
- producer
- master ownership shares
```

A work/composition may be connected to one or more recordings, but the rights structures must remain distinct.

---

## 16. Splits

Splits are business-critical.

Rules:

- validate totals according to the applicable split type;
- do not silently normalize incorrect percentages;
- preserve historical versions;
- signed/accepted split versions must not be overwritten;
- changes must create a traceable new version when required;
- sensitive changes must be audited.

Do not encode split logic only in controllers or Vue components.

Business rules must live in testable backend domain/application services or value objects.

The UX may use guided wizard flows inspired by the analyzed reels, but backend rules remain authoritative.

---

## 17. Contributors / Interested Parties

Contributor identity should be reusable across works and potentially across organizations, while private tenant information remains isolated.

Avoid duplicating legal identity, IPI, PRO and public professional information for every song when a reusable party model is appropriate.

The system should support requesting missing information from a collaborator using a secure invitation flow.

Invitation links must be:

- unguessable;
- expiring where appropriate;
- limited to the intended action;
- protected from unauthorized tenant access.

Be careful not to expose data supplied in one tenant to another tenant without an explicit sharing/legal basis.

---

## 18. Contracts

Contracts must be generated from structured platform data when possible.

Expected concepts:

```text
ContractTemplate
Contract
ContractVersion
ContractParty
SignatureRequest
Document
```

Initial document types may include:

- Composition Split Sheet
- Master Split Sheet
- Producer Agreement
- Management Agreement
- Work for Hire
- Publishing Agreement

Do not hardcode legal document text into controllers.

Templates must be versionable and may vary by tenant, jurisdiction, language or business context.

A signed document must remain immutable as an historical artifact.

---

## 19. Document Vault

Documents may contain sensitive legal, financial or personal information.

Rules:

- do not store sensitive documents in a publicly accessible web directory;
- use private storage;
- authorize every download;
- use signed/temporary URLs where appropriate;
- keep document metadata and ownership explicit;
- audit sensitive access or mutation when required.

Never commit real customer documents to the repository.

---

## 20. Metadata Readiness

Readiness is a rule-driven assessment of whether a work is prepared for a target process.

Do not implement readiness as a manually entered percentage.

Readiness should be calculated from explicit checks.

```text
✓ title
✓ writers
✓ IPI
✓ shares valid
✓ split sheet signed
✗ ISWC
✗ PRO registration
```

Rules may vary by destination, jurisdiction, organization or registration workflow, so design them to be extendable.

---

## 21. Registration Center

Registration state must be explicit and auditable.

Typical states may include:

```text
pending
ready
submitted
registered
correction_required
```

Do not assume all societies, countries or destinations use identical requirements.

---

## 22. CWR — critical module

The CWR generator is a business-critical module.

It must be isolated from controllers and presentation code.

Recommended conceptual flow:

```text
Domain Models
    ↓
CWR Mapping
    ↓
Business Validation
    ↓
Record Generation
    ↓
File Generation
```

Initial records may include:

- HDR
- GRH
- NWR
- SPU
- SWR
- TRL

Additional records must be implemented according to the official specification and actual business requirements.

### CWR rules

- do not guess field positions;
- do not guess field lengths;
- do not guess encoding requirements;
- do not infer standards from examples when official specifications are available;
- preserve deterministic output;
- validate every fixed-width record;
- test edge cases;
- test unsupported characters;
- test shares;
- test required identifiers;
- test line lengths.

Never implement CWR by scattering raw string concatenation throughout the codebase.

Centralize field definitions and formatting.

---

## 23. Financial modules

Royalties, recoupment, payouts and accounting are high-risk domains.

When these modules are reached:

- use decimal/fixed-point monetary representations;
- never use floating point for money;
- preserve source import traceability;
- preserve calculation inputs;
- make recalculations reproducible;
- audit manual adjustments;
- distinguish royalty accounting from general accounting;
- do not assume one tenant type has the same settlement model as another.

Do not implement financial behavior early unless explicitly included in the task.

---

## 24. Auditability

Critical mutations should record enough information to answer:

- who changed it?
- when?
- in which tenant?
- what entity changed?
- what was the previous value?
- what is the new value?

High-priority audit areas:

- splits;
- contracts;
- rights ownership;
- bank details;
- tax information;
- payouts;
- registration status;
- permissions.

Do not log secrets or sensitive values unnecessarily.

---

## 25. Database rules

All schema changes must use migrations.

Do not manually modify production databases.

Do not edit old migrations that may already have been executed outside local development unless explicitly approved.

Use:

- foreign keys deliberately;
- indexes based on query patterns;
- unique constraints for invariants;
- database constraints when appropriate.

Application validation is not a substitute for database integrity where the database can safely enforce the invariant.

Before adding `tenant_id` to an entity, decide whether the entity is truly tenant-owned, globally reusable, or linked through a tenant-specific relationship.

---

## 26. Identifiers

Do not conflate internal IDs with music-industry identifiers.

Examples:

- internal UUID / primary key;
- ISRC;
- ISWC;
- IPI/CAE;
- UPC.

Validate identifiers according to known rules.

Do not invent identifiers for production data.

---

## 27. Controllers

Keep controllers thin.

Controllers should generally coordinate:

- request validation;
- authorization;
- calling application/domain services;
- returning responses.

Do not place complex domain rules, CWR formatting, royalty calculations or contract generation logic directly in controllers.

---

## 28. Validation

Use dedicated request validation and domain validation.

Frontend validation improves UX but is never sufficient for business-critical rules.

Backend validation is authoritative.

Return useful validation errors without leaking internal implementation details.

---

## 29. Services and actions

Prefer small, named operations for business behavior.

Examples:

```text
CreateTenant
InviteTenantMember
CreateArtist
CreateWork
AddContributor
CreateCompositionSplit
CreateMasterSplit
PublishSplitVersion
GenerateContract
CalculateReadiness
GenerateCwrFile
```

Avoid generic service classes containing dozens of unrelated methods.

---

## 30. Events and jobs

Use queued jobs for slow or retryable operations such as:

- email;
- collaborator invitations;
- document generation;
- imports;
- notifications;
- external API calls;
- large exports.

Do not make a user wait on an HTTP request for work that belongs in a queue.

Do not introduce asynchronous complexity for trivial synchronous operations.

---

## 31. External integrations

Wrap third-party services behind application interfaces/adapters.

Examples:

- electronic signature;
- email;
- storage;
- Stripe;
- music platforms;
- SFTP;
- AI providers.

Do not spread vendor-specific SDK calls across controllers and domain code.

Vendor credentials must come from environment/configuration and never be committed.

---

## 32. Security

Treat security as part of feature completeness.

Required principles:

- least privilege;
- tenant isolation;
- server-side authorization;
- CSRF protection where applicable;
- rate limiting;
- secure session handling;
- private files;
- secret management;
- input validation;
- output escaping;
- safe redirects;
- protection against mass assignment;
- no sensitive data in logs.

Do not weaken security controls to make tests or development easier.

---

## 33. Production access

AI coding agents must not assume production access.

Do not:

- connect to production databases;
- execute production migrations;
- deploy to production;
- rotate production credentials;
- modify live tenant data;

unless the user explicitly requests that specific action and the environment is intentionally configured for it.

Default target is local/test.

Staging deployment should be handled through approved CI/CD.

Production release should require explicit human approval.

---

## 34. Testing

Tests are required for business-critical behavior.

At minimum, new logic should include tests for:

### Tenant independence
- multiple tenant/business types can use the same feature;
- no Dilo-specific assumption is required.

### Tenancy
- cross-tenant reads blocked;
- cross-tenant writes blocked.

### Memberships
- same user can belong to multiple tenants;
- permissions differ by membership where applicable.

### Rights
- valid split;
- invalid split;
- version history;
- signed-version immutability.

### Contracts
- correct data mapping;
- version preservation;
- authorization.

### CWR
- exact record lengths;
- deterministic formatting;
- required fields;
- invalid characters;
- share validation.

### Financial modules
- deterministic calculations;
- rounding;
- recoupment;
- audit trail.

Prefer tests that assert behavior, not internal implementation details.

---

## 35. Definition of done

A task is not complete only because the code compiles.

Unless explicitly excluded, completion means:

1. requested scope implemented;
2. design remains generic across supported tenant types;
3. migrations included when needed;
4. authorization implemented;
5. tenant isolation verified;
6. validation implemented;
7. relevant automated tests added;
8. tests executed;
9. formatter/linter executed;
10. no secrets committed;
11. documentation updated when architecture/business rules changed;
12. remaining risks or untested assumptions reported.

---

## 36. Commands before completion

Once the project scaffold exists, run the applicable checks.

Expected commands may include:

```bash
php artisan test
./vendor/bin/pint --test
npm run lint
npm run type-check
npm run build
```

Use the actual scripts available in the repository.

Do not claim tests passed if they were not executed.

If an environment limitation prevents execution, state exactly what could not be run.

---

## 37. Code style

Follow existing Ascanio repository conventions first.

General rules:

- expressive names;
- small methods;
- explicit types where useful;
- minimal comments for obvious code;
- comments for non-obvious business reasoning;
- avoid dead code;
- avoid speculative abstractions;
- avoid premature optimization.

Code copied or adapted from Dilo must be reformatted/refactored to match Ascanio conventions.

Do not create abstractions merely to make code look sophisticated.

---

## 38. Frontend rules

Frontend code must:

- use TypeScript;
- keep API/domain types explicit;
- avoid duplicating business rules that belong to the backend;
- provide clear loading/error/empty states;
- preserve accessibility;
- work responsively;
- keep forms understandable for non-technical music-industry users.

Complex workflows should prefer guided steps over giant forms.

The same core workflow should remain usable by labels, publishers, managers and independent artists unless a documented domain reason requires a different flow.

---

## 39. UI language

User-facing terminology should be consistent with the music business.

Avoid exposing implementation terminology such as:

- database IDs;
- internal enum names;
- raw API errors;
- framework terminology.

Do not make all UI terminology label-centric.

Labels must make sense for the active business context.

---

## 40. Internationalization

Do not hardcode application-facing strings in a way that prevents localization.

The architecture should support:

- Spanish;
- English;
- Portuguese;

even if the MVP ships initially with fewer complete translations.

---

## 41. Time, locale and money

Store timestamps consistently and present them using tenant/user timezone.

Do not assume all tenants use:

- the same currency;
- the same timezone;
- the same date format;
- the same country;
- the same tax rules.

Avoid implementing tax logic until requirements are explicit.

---

## 42. Git workflow

Default branch strategy:

```text
main      production
develop   integration / staging
```

Feature branch examples:

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

Keep changes scoped.

One ticket should normally map to one focused branch / PR.

---

## 43. Commit convention

Use Conventional Commits when practical.

Examples:

```text
feat(tenancy): add tenant membership model
feat(catalog): add works domain
feat(rights): validate composition shares
fix(cwr): pad NWR record correctly
test(tenancy): prevent cross-tenant artist access
docs(domain): document recording relationships
refactor(contracts): extract template renderer
```

Avoid commits like:

```text
changes
fix stuff
update project
final
```

---

## 44. Working with issues

Treat a GitHub issue as the contract for a coding task.

Before coding, identify:

- objective;
- current behavior;
- desired behavior;
- scope;
- out-of-scope items;
- acceptance criteria;
- affected domain;
- required tests;
- whether the design accidentally assumes a specific tenant type.

If requirements are materially ambiguous, ask before making a high-impact assumption.

---

## 45. Scope discipline

Implement only the requested scope.

Do not add unrelated modules because they are present in the long-term roadmap.

Examples:

If the task is Tenant creation:
- do not also implement Stripe billing;
- do not add artist management;
- do not implement contracts.

If the task is Composition Splits:
- do not simultaneously implement royalties unless explicitly requested.

If Dilo contains an apparently useful feature:
- do not port it automatically;
- first verify it belongs to Ascanio's roadmap and generic domain.

---

## 46. Documentation

Read relevant documentation under `docs/` before substantial changes.

Expected documents:

```text
docs/architecture.md
docs/domain-model.md
docs/roadmap.md
docs/permissions.md
docs/cwr.md
docs/backlog/
```

Update documentation when the implementation changes an accepted architectural or domain decision.

Do not create documentation that contradicts the code.

---

## 47. TODOs

Do not leave vague TODOs such as:

```text
TODO: fix later
TODO: add security
TODO: handle tenants
```

If work is intentionally deferred, reference a concrete issue or document the precise reason.

---

## 48. Migrations and seed data

Seed data must be synthetic.

Never commit real:

- artist private data;
- contracts;
- banking details;
- tax data;
- customer credentials.

Create multiple tenant types in test fixtures when validating generic behavior.

Example synthetic tenants:

```text
Northstar Records       → record label
Aurora Publishing       → music publisher
Orbit Management        → management company
Independent Artist Demo → independent artist
```

Use fake artists and works.

Dilo data must not become default seed data unless explicitly sanitized and requested for a migration/test fixture.

---

## 49. Error handling

Expected domain failures should produce clear domain/application errors.

Do not swallow exceptions silently.

External integration failures should be:

- observable;
- retryable when appropriate;
- idempotent where required.

Never expose stack traces or secrets to end users.

---

## 50. Idempotency

Pay special attention to idempotency for:

- external webhooks;
- payouts;
- imports;
- contract callbacks;
- registration submissions;
- background jobs.

A retry should not duplicate financial or legal side effects.

---

## 51. Performance

Correctness and tenant isolation take priority over premature optimization.

Still:

- avoid N+1 queries;
- index tenant foreign keys and common lookup fields;
- paginate list endpoints;
- queue heavy jobs;
- stream or chunk large imports when appropriate.

Measure before adding complex caching.

---

## 52. API-first direction

The system should keep a clean application/API boundary even if Inertia is used for the first frontend.

Do not bind core business logic to page rendering.

Future clients may include:

- organization dashboards;
- artist portals;
- public EPKs;
- mobile clients;
- third-party APIs.

Do not expose a public API in the MVP unless explicitly requested.

---

## 53. AI features

AI is a later layer of the product.

Core data and deterministic business rules must work without AI.

When AI modules are introduced:

- scope data access by tenant and user permissions;
- do not allow AI tools to bypass authorization;
- identify generated/estimated outputs;
- keep human review for high-impact legal/financial outputs;
- do not present audio similarity as certified legal evidence.

---

## 54. Never do these things

Never:

- build Ascanio around Dilo Records;
- assume every tenant is a label;
- hardcode customer names in business logic;
- fork core behavior per customer without a documented architectural reason;
- copy Dilo code without reviewing and adapting it;
- equate tenant ownership with legal rights ownership automatically;
- mix composition and master ownership;
- bypass authorization to simplify code;
- expose private documents publicly;
- use floating point for money;
- overwrite signed historical records;
- guess CWR field positions;
- commit secrets;
- modify production implicitly;
- claim a test passed without running it;
- rewrite unrelated modules during a scoped task.

---

## 55. Agent response expectations

After implementing a task, provide a concise report containing:

1. what changed;
2. key files changed;
3. migrations added;
4. tests added/updated;
5. commands/tests executed and their result;
6. any assumptions made;
7. whether Dilo/reference code was reused;
8. remaining risks or follow-up work.

For planning-only requests, do not modify files unless explicitly asked.

---

## 56. Current development priority

Until the MVP foundation changes, priority order is:

```text
1. Product / Domain Foundation
2. Multi-tenancy
3. Identity & Memberships
4. RBAC
5. Organizations & Music Core
6. Contributors / Interested Parties
7. Composition Rights
8. Master Rights
9. Split Workflow
10. Contract Engine
11. Document Vault
12. Metadata Readiness
13. Registration Center
14. CWR Engine
```

Later roadmap work must not distract from these priorities.

---

## 57. Final principle

The project should optimize for:

```text
Industry-wide domain model
        ↓
Flexible organization model
        ↓
Secure tenant isolation
        ↓
Reusable music data
        ↓
Reliable operations
        ↓
Automation
        ↓
Intelligence
```

Or, in product terms:

**Platform first. Customer configuration second. No customer-specific architecture.**
