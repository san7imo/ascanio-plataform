# AGENTS.md — Ascanio Publishing

This file defines the default engineering instructions for AI coding agents working in this repository.

Read this file before planning or modifying code.

---

## 1. Product context

Ascanio Publishing is a greenfield B2B multi-tenant SaaS platform for music publishing, labels, managers and artists.

Dilo Records is the first real tenant used to validate the product.

**Dilo Records is not the application.**

Never hardcode Dilo-specific behavior into the platform unless the task explicitly requests a tenant-specific customization that belongs in configuration or branding.

The product is intended to support multiple tenants in the future.

---

## 2. Product goal

The first commercial MVP must support this end-to-end workflow:

```text
Artist
  ↓
Work / Composition
  ↓
Recording / Master
  ↓
Contributors
  ↓
Composition Splits
  ↓
Master Splits
  ↓
Contracts
  ↓
Signatures
  ↓
Document Vault
  ↓
Metadata Readiness
  ↓
CWR
  ↓
Registration Tracking
```

Do not implement later roadmap modules unless the task explicitly asks for them.

---

## 3. Planned stack

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

## 4. Architecture

Use a **modular monolith**.

Do not introduce microservices, message brokers, event streaming platforms or distributed infrastructure unless explicitly requested and justified.

Target domain boundaries:

```text
Tenancy
Identity
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

## 5. Architecture decision rule

Before implementing a substantial or cross-cutting change:

1. inspect existing code;
2. inspect relevant docs;
3. identify domain boundaries involved;
4. explain the implementation plan;
5. identify files that will change;
6. identify migrations or data compatibility implications;
7. identify security and tenant-isolation implications;
8. implement only after the approach is coherent.

For small, local and obvious tasks, proceed directly.

Do not perform large opportunistic refactors unrelated to the requested task.

---

## 6. Multi-tenancy — critical rule

Multi-tenancy is a first-class architectural requirement.

Every tenant-owned entity must be explicitly tenant-scoped.

Examples may include:

- artists;
- works;
- recordings;
- releases;
- contracts;
- documents;
- royalty statements;
- contacts;
- tasks;
- payments.

Never trust a tenant identifier received from the client without authorization.

Never create code that can accidentally return records from another tenant.

Tenant isolation must be enforced server-side.

### Required tests

Any tenant-scoped feature must include tests proving that Tenant A cannot read or mutate Tenant B data.

A feature that exposes tenant-owned data without tenant-isolation tests is not complete.

---

## 7. User membership model

Do not assume one user belongs to only one tenant.

The design must support a structure conceptually equivalent to:

```text
users
tenants
tenant_memberships
roles
permissions
```

A user can potentially have different roles in different tenants.

Avoid implementing business authorization as a single global `users.role` field.

---

## 8. Authorization

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

---

## 9. Music domain rules

Use precise music-industry terminology.

Important entities/concepts include:

- Artist
- Work
- Composition
- Recording
- Master
- Release
- Contributor
- Writer
- Publisher
- Interested Party
- ISRC
- ISWC
- IPI / CAE
- UPC
- Split Set
- Contract
- Registration

Do not use generic names like `Item`, `Thing` or `Data` when a domain term exists.

---

## 10. Composition rights and master rights

Composition rights and master rights are different domains.

Never merge them into one generic ownership percentage.

Example:

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

## 11. Splits

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

---

## 12. Contributors / Interested Parties

Contributor identity should be reusable across works.

Avoid duplicating legal identity, IPI, PRO and contact information for every song.

The system should support requesting missing information from a collaborator using a secure invitation flow.

Invitation links must be:

- unguessable;
- expiring where appropriate;
- limited to the intended action;
- protected from cross-tenant access.

---

## 13. Contracts

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

Templates must be versionable.

A signed document must remain immutable as an historical artifact.

---

## 14. Document Vault

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

## 15. Metadata Readiness

Readiness is a rule-driven assessment of whether a work is prepared for a target process.

Do not implement readiness as a manually entered percentage.

Readiness should be calculated from explicit checks.

Example:

```text
✓ title
✓ writers
✓ IPI
✓ shares valid
✓ split sheet signed
✗ ISWC
✗ PRO registration
```

Rules may vary by destination or registration workflow, so design them to be extendable.

---

## 16. Registration Center

Registration state must be explicit and auditable.

Typical states may include:

```text
pending
ready
submitted
registered
correction_required
```

Do not assume all societies or destinations use identical requirements.

---

## 17. CWR — critical module

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

## 18. Financial modules

Royalties, recoupment, payouts and accounting are high-risk domains.

When these modules are reached:

- use decimal/fixed-point monetary representations;
- never use floating point for money;
- preserve source import traceability;
- preserve calculation inputs;
- make recalculations reproducible;
- audit manual adjustments;
- distinguish royalty accounting from general accounting.

Do not implement financial behavior early unless explicitly included in the task.

---

## 19. Auditability

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

## 20. Database rules

All schema changes must use migrations.

Do not manually modify production databases.

Do not edit old migrations that may already have been executed outside local development unless explicitly approved.

Use:

- foreign keys deliberately;
- indexes based on query patterns;
- unique constraints for invariants;
- database constraints when appropriate.

Application validation is not a substitute for database integrity where the database can safely enforce the invariant.

---

## 21. Identifiers

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

## 22. Controllers

Keep controllers thin.

Controllers should generally coordinate:

- request validation;
- authorization;
- calling application/domain services;
- returning responses.

Do not place complex domain rules, CWR formatting, royalty calculations or contract generation logic directly in controllers.

---

## 23. Validation

Use dedicated request validation and domain validation.

Frontend validation improves UX but is never sufficient for business-critical rules.

Backend validation is authoritative.

Return useful validation errors without leaking internal implementation details.

---

## 24. Services and actions

Prefer small, named operations for business behavior.

Examples:

```text
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

## 25. Events and jobs

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

## 26. External integrations

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

## 27. Security

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

## 28. Production access

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

## 29. Testing

Tests are required for business-critical behavior.

At minimum, new logic should include tests for:

### Tenancy
- cross-tenant reads blocked;
- cross-tenant writes blocked.

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

## 30. Definition of done

A task is not complete only because the code compiles.

Unless explicitly excluded, completion means:

1. requested scope implemented;
2. migrations included when needed;
3. authorization implemented;
4. tenant isolation verified;
5. validation implemented;
6. relevant automated tests added;
7. tests executed;
8. formatter/linter executed;
9. no secrets committed;
10. documentation updated when architecture/business rules changed;
11. remaining risks or untested assumptions reported.

---

## 31. Commands before completion

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

## 32. Code style

Follow existing repository conventions first.

General rules:

- expressive names;
- small methods;
- explicit types where useful;
- minimal comments for obvious code;
- comments for non-obvious business reasoning;
- avoid dead code;
- avoid speculative abstractions;
- avoid premature optimization.

Do not create abstractions merely to make code look sophisticated.

---

## 33. Frontend rules

Frontend code must:

- use TypeScript;
- keep API/domain types explicit;
- avoid duplicating business rules that belong to the backend;
- provide clear loading/error/empty states;
- preserve accessibility;
- work responsively;
- keep forms understandable for non-technical music-industry users.

Complex workflows should prefer guided steps over giant forms.

---

## 34. UI language

User-facing terminology should be consistent with the music business.

Avoid exposing implementation terminology such as:

- database IDs;
- internal enum names;
- raw API errors;
- framework terminology.

Labels must be understandable to artists, publishers and label staff.

---

## 35. Internationalization

Do not hardcode application-facing strings in a way that prevents localization.

The architecture should support:

- Spanish;
- English;
- Portuguese;

even if the MVP ships initially with fewer complete translations.

---

## 36. Time, locale and money

Store timestamps consistently and present them using tenant/user timezone.

Do not assume all tenants use:

- the same currency;
- the same timezone;
- the same date format;
- the same country;
- the same tax rules.

Avoid implementing tax logic until requirements are explicit.

---

## 37. Git workflow

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

## 38. Commit convention

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

## 39. Working with issues

Treat a GitHub issue as the contract for a coding task.

Before coding, identify:

- objective;
- current behavior;
- desired behavior;
- scope;
- out-of-scope items;
- acceptance criteria;
- affected domain;
- required tests.

If requirements are materially ambiguous, ask before making a high-impact assumption.

---

## 40. Scope discipline

Implement only the requested scope.

Do not add unrelated modules because they are present in the long-term roadmap.

Examples:

If the task is Tenant creation:
- do not also implement Stripe billing;
- do not add artist management;
- do not implement contracts.

If the task is Composition Splits:
- do not simultaneously implement royalties unless explicitly requested.

---

## 41. Documentation

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

## 42. TODOs

Do not leave vague TODOs such as:

```text
TODO: fix later
TODO: add security
TODO: handle tenants
```

If work is intentionally deferred, reference a concrete issue or document the precise reason.

---

## 43. Migrations and seed data

Seed data must be synthetic.

Never commit real:

- artist private data;
- contracts;
- banking details;
- tax data;
- customer credentials.

Create at least two tenants in test fixtures when validating tenant isolation.

Suggested test tenants:

```text
Dilo Records Demo
Demo Label
```

Use fake artists and works.

---

## 44. Error handling

Expected domain failures should produce clear domain/application errors.

Do not swallow exceptions silently.

External integration failures should be:

- observable;
- retryable when appropriate;
- idempotent where required.

Never expose stack traces or secrets to end users.

---

## 45. Idempotency

Pay special attention to idempotency for:

- external webhooks;
- payouts;
- imports;
- contract callbacks;
- registration submissions;
- background jobs.

A retry should not duplicate financial or legal side effects.

---

## 46. Performance

Correctness and tenant isolation take priority over premature optimization.

Still:

- avoid N+1 queries;
- index tenant foreign keys and common lookup fields;
- paginate list endpoints;
- queue heavy jobs;
- stream or chunk large imports when appropriate.

Measure before adding complex caching.

---

## 47. API-first direction

The system should keep a clean application/API boundary even if Inertia is used for the first frontend.

Do not bind core business logic to page rendering.

Future clients may include:

- artist portals;
- public EPKs;
- mobile clients;
- third-party APIs.

Do not expose a public API in the MVP unless explicitly requested.

---

## 48. AI features

AI is a later layer of the product.

Core data and deterministic business rules must work without AI.

When AI modules are introduced:

- scope data access by tenant and user permissions;
- do not allow AI tools to bypass authorization;
- identify generated/estimated outputs;
- keep human review for high-impact legal/financial outputs;
- do not present audio similarity as certified legal evidence.

---

## 49. Never do these things

Never:

- hardcode Dilo Records as the only tenant;
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

## 50. Agent response expectations

After implementing a task, provide a concise report containing:

1. what changed;
2. key files changed;
3. migrations added;
4. tests added/updated;
5. commands/tests executed and their result;
6. any assumptions made;
7. remaining risks or follow-up work.

For planning-only requests, do not modify files unless explicitly asked.

---

## 51. Current development priority

Until the MVP foundation changes, priority order is:

```text
1. Project Foundation
2. Multi-tenancy
3. Identity & RBAC
4. Music Core
5. Contributors
6. Composition Rights
7. Master Rights
8. Split Workflow
9. Contract Engine
10. Document Vault
11. Metadata Readiness
12. Registration Center
13. CWR Engine
```

Later roadmap work must not distract from these priorities.

---

## 52. Final principle

The project should optimize for:

```text
Correct domain model
        ↓
Secure tenant isolation
        ↓
Reliable operations
        ↓
Automation
        ↓
Intelligence
```

Or, in product terms:

**Data → Operations → Intelligence.**
