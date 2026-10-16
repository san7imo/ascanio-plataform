# AGENTS.md — Ascanio Publishing Platform

Engineering instructions for Codex and other coding agents working in this repository. Read this file and [README.md](README.md) before planning or editing.

## 1. Product and current scope

Ascanio is an independent, greenfield music SaaS for organizations and professionals. It supports labels, publishers, management businesses, independent artists, producers, writers, DJs and hybrid operations.

Build industry concepts and configurable workflows. Never hardcode customer names, make all tenants labels or fork the core for a customer.

The repository now contains the Laravel 12 / Vue technical scaffold, Fortify authentication and local PostgreSQL/Redis configuration. The domain models, tenancy and business modules described in README remain targets, not existing implementation. Report only checks actually executed.

The current task boundary is architecture/documentation unless the user explicitly requests implementation. For an implementation task, work on the requested milestone only. The first implementation milestone is the Laravel/Vue scaffold and IdentityAccess foundation; not contracts, royalties or a full 360º product.

README is the architecture/domain baseline, this file is the engineering contract, and an issue/task supplies acceptance criteria. Direct user instructions take precedence. Future ADRs must explicitly state which decisions they supersede. Do not invent missing `docs/` files or claim to have read them.

## 2. Stack and structure

Target stack: PHP 8.3+, Laravel 12, PostgreSQL, Redis, Laravel queues, private local storage on the acquired Kroma server, Vue 3, TypeScript, Vite and Inertia. Production files remain on Kroma; S3 or another paid storage provider is not part of the MVP.

Use secure session/cookie authentication and Fortify for the first web application. Add Sanctum for an actual internal API consumer; do not add JWT or require the Inertia web to call itself through HTTP.

Use a modular monolith. Initial modules are IdentityAccess, Parties, Catalog, Rights, Documents, Contracts and Registration. No microservices, Kafka, distributed event bus, event sourcing or speculative platform framework.

Target `app/Modules/{Module}` with Models, Actions, Policies, Queries, Data, Events and Jobs only when needed. Keep Laravel framework integration conventional. `app/Models/User.php` may remain where the scaffold expects it. Register modular policies explicitly if autodiscovery does not resolve them.

Modules own their mutations. Cross-module reads use concrete query contracts/DTOs where useful. Avoid generic repositories for every Eloquent model and giant “MusicService” classes. Documents must not depend on Contracts; consumers own their document links.

Controllers validate, authorize, call actions and return responses. Business invariants do not live in Vue, controllers, middleware or vendor adapters.

Before installing dependencies, verify compatibility/support and pin lockfiles. Do not silently upgrade Laravel, use “latest”, switch databases or add a framework. Clarify an actual incompatibility with evidence; continue unaffected work.

## 3. Domain vocabulary and identity

- `User` is a global authentication account, not artist, writer, legal party or beneficiary.
- `Party` is minimal global identity; Person and Organization are subtypes. Global identity is not a searchable shared private directory.
- `Tenant` is an operational workspace/custody boundary, not copyright ownership.
- `Membership` joins User and Tenant with status and tenant-specific roles.
- `TenantParty` is the private local counterpart profile, optionally linked to a global Party.
- ArtistProfile represents a solo artist, group or project; it may link multiple TenantParties.
- Manager is a scoped representation relationship. Producer, writer, composer, DJ and performer are contextual credits/professional roles, not extra User subclasses or identities.
- Contributor/interested party is a Party in a resource relationship, not another duplicate people table.
- `Work` is the composition. Do not create separate Work and Composition aggregates holding the same information.
- `Recording` is a specific recording/master asset. Work–Recording is N:M.
- `Release` groups ordered recording occurrences. Recording–Release is N:M.
- Credit, authorship, legal ownership, administration, collection, economic beneficiary and application access are distinct.

Reuse identity through verified links, not through automatic cross-tenant lookup by name/email/IPI. Unknown counterparts remain provisional TenantParties until claimed. Verify the intended invitation and logged-in recipient before linking an existing account. Account control is not proof of legal identity or ownership.

Do not store banks, tax data, contracts or business notes on global Users/Parties. Private fields remain tenant-local; explicit sharing records consent, origin and snapshot. Do not propagate profile edits into historical documents.

## 4. Tenant isolation: required

Initial architecture is a shared PostgreSQL database/schema with mandatory `tenant_id` for private entities and all their pivots.

Global exceptions are explicitly listed in README: users, minimal party identities/subtypes, tenants themselves, permission/reference catalogs and platform role assignments. A tenant's own `id` is its boundary. Do not add `tenant_id` blindly to globals or omit it from child rows.

For every tenant-scoped operation:

1. Resolve an explicit authorized workspace or bounded collaboration context.
2. Fail closed if context is absent, inconsistent or revoked.
3. Derive tenant server-side; never trust client body/header IDs.
4. Scope queries and route binding before retrieving private data.
5. Validate foreign keys against the same tenant.
6. Authorize the action and resource, including field projection.
7. Scope search, lists, counts, pagination, downloads and exports too.
8. Include tenant in cache, lock, storage and job namespaces.
9. Clear worker/request context; no fallback to “first tenant”.

Use composite foreign keys `(tenant_id, parent_id)` with `UNIQUE(tenant_id, id)` on private parents. UUIDs and storage prefixes are not authorization.

Do not use `withoutGlobalScopes`, raw SQL or a privileged connection to bypass tenant boundaries. Any deliberately global administrative query must have a narrow, documented purpose and test.

RLS is not currently implemented or assumed. If explicitly adopted, test runtime connection roles, transaction-local context, pool cleanup and workers. Runtime must not own tables or have superuser/`BYPASSRLS` privileges; migration role is separate. RLS does not replace resource policies.

## 5. Access: membership, capability, resource and state

Use tenant-scoped RBAC, granular capabilities, resource scopes and Laravel policies. Roles are bundles, not business truth. No `users.role`, global business roles or scattered checks such as `hasRole('artist')`.

Each role assignment declares tenant-wide or assigned-resource scope. A capability alone does not grant access to all resources.

Representation/participation alone grants no access. A manager also needs delegated application authority. Scope delegated artist access to approved relations/resources, not every release containing a similar artist name.

External participants may have resource grants without full membership. Grants specify user, capability, typed resource/version, projection, expiry, revocation and provenance. Use checked typed FKs as specified in README, not arbitrary resource IDs from the client.

Policies require account/context validity, action capability, resource scope and applicable state/MFA conditions. Hard denials prevail. Apply the same predicates to queries and serializers; hiding UI controls is insufficient.

Default participant view is their own participation and authorized documents. Complete split disclosure requires an explicit grant. Every signer may review the complete exact document they are asked to sign, without receiving unrelated contracts, banks or catalog records.

`contracts.sign` is not an admin permission to impersonate participants. Signing requires the intended contract party/recipient and verified representative authority.

Tenant owner governs the workspace; legal ownership is elsewhere. Owners must not sign for all parties, bypass version immutability or acquire musical rights automatically.

Platform administration has no universal catalog bypass. No SuperAdmin `Gate::before` returning true for tenant business data. Support access must be explicit, bounded, expiring and audited.

Role administration cannot escalate beyond the actor's delegable capabilities. Protect ownership transfer and removal of the final active owner transactionally. Membership/grant revocation invalidates relevant cache/sessions and its dependent access paths.

## 6. Invitations and cross-organization collaboration

Tokens are random, hashed at rest, expiring, revocable and single-use for acceptance. Bind them to tenant, recipient, purpose and exact resource/version. Enforce atomic consumption and rate limits.

Support both existing accounts and new verified accounts. Never reject an existing user merely because they already participate elsewhere, and never attach all matching email participants across tenants.

The token is not a permanent login credential or sufficient signature evidence. Invite acceptance creates only the authorized membership or bounded grants.

MVP uses one custodial tenant per resource. An external organization's individual representatives collaborate in that context. A Party or organization relationship does not automatically grant access to all its members.

Local copies of the same work in different tenants remain independent, with explicit source/snapshot links. Matching identifiers do not merge rights or grant visibility.

Do not implement global shared catalogs, coediting across workspaces or bidirectional syncing without a new domain decision. Do not “solve collaboration” by removing isolation.

## 7. Rights and split invariants

`tenant_id` records custody, not legal title. Composition and master rights are separate. Authorship, copyright ownership, publishing administration, collection and revenue participation are also separate.

Every percentage has an explicit purpose, base, right type, territorial scope and effective interval. A share of a share is not a share of the whole work. Administration must reference the administered interest/mandant; do not add it as duplicate ownership.

PR/MR/SR must remain distinguishable. Never impose Dilo's writer/publisher/mechanical pools as universal rules or infer registration shares from a simple author split.

Use decimal/fixed-point arithmetic and PostgreSQL `NUMERIC(9,6)` for internal percentages with range checks. Never float or implicit normalization. Contractual full allocations total exactly 100% for their declared base/scope. Partial/incomplete claims can be drafts, not silently completed agreements.

Validate totals and activation in a transaction with aggregate locking. Test concurrent submissions; per-row CHECK constraints cannot enforce aggregate totals.

SplitSet holds scope; SplitVersion freezes payload and participants. Published/accepted/signature-pending versions are immutable. Changes create a new version. Signing v1 cannot approve v2. Creating a draft replacement cannot archive the effective version prematurely.

Effective intervals cannot overlap for equivalent rights/territory/base. Preserve conflicts and disputes instead of resolving them by the last write. Keep signed evidence and historic calculation inputs.

Separate the contractual split from authoritative rights records as explained in README. Do not keep two independently editable current percentages for the same legal interest.

Future financial modules use Money with currency, deterministic rounding, import traceability and calculation snapshots. Royalties, recoupment, payouts and general accounting are separate domains and are not part of the initial MVP.

## 8. Contracts, signature and private documents

Generate from structured data with a versioned tenant template and frozen input snapshot. Templates declare type, language and jurisdiction. Initial contract delivery covers composition/master split sheets; additional forms need explicit scope and reviewed content.

No hardcoded legal text in controllers or claims that generic templates establish legal validity. Signature provider/method is pending; do not simulate a production provider or certify a checkbox/drawing.

A contract version links exact SplitVersions, parties and signing requests. Frozen/signature-pending/signed payloads and files cannot be overwritten. Store original, signed output and evidence separately with checksums and provider IDs.

Signing completes evidence; activation separately checks contractual prerequisites. No automatic copyright transfer merely from a provider callback.

Validate webhook authenticity, event ID deduplication, tenant mapping from known provider IDs and legal state transitions. No trust in incoming `tenant_id`. Retries and reordered callbacks must not duplicate or corrupt effects.

Use Laravel's local filesystem in production and development. Private files live outside every public document root on the Kroma account, under the configured private storage root. Keep paths relative through the Storage API and tenant-namespaced. Persist storage across releases; never symlink private storage into public/public_html. Public local storage is only for deliberately published assets.

Authorize every private download independently of metadata visibility through an authenticated endpoint that rechecks tenant/resource access. A signed URL alone is not sufficient authorization. Never introduce S3, cloud adapters/credentials or paid external storage as a requirement. Unused scaffold cloud examples are not product dependencies.

Verify file type, size and malware status. Do not expose private uploads through `public`, symlinks or a public CDN. Keep retention/evidence rules explicit; soft-delete is not authority to purge signed records.

## 9. Readiness, Registration Center and CWR

Readiness is destination/process-specific with a versioned rule profile, checks and blockers. A progress percentage does not override blockers. Recompute after relevant metadata/rights changes; preserve the assessment used by an export.

Work registration must support unrecorded compositions. Do not universally require ISRC, UPC, ISWC or prior registration before initial registration. Apply documented recipient requirements.

Exported, submitted, acknowledged and accepted are distinct states. Sending/acceptance requires evidence; MVP may track it manually with audit. Export generation is not registration success.

CWR belongs to Registration, with mapper/validator/generator boundaries and deterministic immutable inputs. It concerns compositions, not master distribution or royalty statement parsing.

Select the receiver/version/profile before coding fields or record support. Use official specifications and receiver requirements; do not guess positions, lengths, encodings, extensions, ownership/collection mapping or supported cases. Unsupported data must fail explicitly.

Tests cover byte lengths, encoding, headers/group trailers/transmission trailers, counts, controlled/non-controlled parties, territories, shares, required fields and deterministic output. Use approved synthetic fixtures and an independent validator/receiver review. Never claim external acceptance based only on tests.

SFTP, automatic ACK parsing and additional receiver profiles are later iterations unless requested.

## 10. Events, jobs, integrations and audit

Keep critical invariants synchronous and transactional. Dispatch events/notifications after commit. Queue slow/retryable mail, generation, import and export operations.

Every job carries explicit tenant, requester and resource/version IDs; establish/clear context and recheck current authority for delegated actions. Do not serialize a broad permission snapshot as permanent authority.

Use adapters for external providers; no vendor SDK calls spread through domain/controller code. Add inbox/outbox only for a concrete reliability requirement, not an infrastructure framework.

Retries, acceptance, webhooks, imports and activation are idempotent. Record failures and actionable retry state; do not swallow exceptions.

Audit sensitive mutations/access with actor, tenant, action, version, UTC time and redacted changes. Runtime cannot rewrite audit history. Do not claim absolute tamper-proofness against infrastructure administrators or log secrets/complete sensitive documents.

Use synthetic data in development/staging/fixtures. Real data migrations need mapping, preview, consent, provenance and explicit scope. AI features are later; they must use the same authorized tools, without bypasses or authority over rights.

## 11. Database and security

Use migrations for schema changes; preserve executed/shared migration history. Add constraints/indexes and forward data migrations as needed. Production changes are not implied by local feature work.

Prefer UUID internal identifiers and strings for ISRC/ISWC/IPI/UPC. Optional identifiers may be null in drafts. No invented production identifiers or global uniqueness rule that leaks another tenant's private record.

Maintain CSRF, escaping, safe redirects, server-side input allowlists, session protections, rate limits, secret management and least privilege. Ignore client-provided tenant/owner/grantee fields unless explicitly validated.

Do not weaken controls to get a test passing. Do not put sensitive data into Inertia shared props, errors, analytics or Git. Prepare localization (Spanish first, English/Portuguese extensible), UTC timestamps and tenant/user timezone display.

Kroma/cPanel is the fixed production hosting and storage target under the current budget. Its software capabilities, data location and persistent-worker permissions are unverified; do not infer Redis, PostgreSQL, Docker or root access from cPanel/SSH. Inspect compatibility before deployment-dependent implementation. If a requirement cannot run there, document internal alternatives and impact before changing the stack; do not purchase/add external hosting or services implicitly.

Respect the account disk quota shared with existing sites/mail. Check current cPanel quota and app usage rather than relying only on filesystem free space. Define upload limits, reserves, log/temp retention and capacity checks before enabling uploads. Preserve signed evidence and existing projects; do not purge them to make space. Local backups must stay private, fit defined retention and be restorable. The user may download authorized backup copies without a paid storage service.

## 12. Verification and Definition of Done

Behavior tests are required for tenant access, identity claims, permissions, versioned rights, signatures and CWR. Use PostgreSQL for DB isolation/constraints, not SQLite as sole proof.

Cover relevant cases:

- Tenant A cannot read, mutate, search/count, export or download Tenant B private data.
- Same User has different memberships and changes workspace without stale permissions.
- External participant has only authorized resource/field/version access.
- Expired/replayed invitation, wrong recipient and revoked grants/memberships fail.
- Owner/role administrator cannot escalate or sign as another party.
- Decimal boundaries, partial bases, invalid totals, concurrent version creation and immutable signed history.
- Composition without recording, partial administration and different operational tenant types.
- Jobs clear context; callbacks deduplicate and handle reordered/retried delivery.
- Documents remain private; stale readiness does not authorize a changed export.
- CWR fixtures match the selected official profile; export is not acceptance.

Once scaffold exists, run actual repository commands for tests, Pint, lint, typecheck and build. Expected tools include `php artisan test` and `./vendor/bin/pint --test`; npm script names must come from `package.json`. Use the actual repository scripts.

Documentation-only changes need consistency, local-link/Markdown review and `git diff --check`; application checks are required for scaffold or code changes.

A task is done when requested scope and acceptance criteria are met, isolation and authorization are verified, invariants/migrations are correct, relevant checks are executed, docs reflect behavior and material limits are reported. Do not claim tests passed without running them.

## 13. Git and delivery

Use the existing `main` as current review base, short `feat/`, `fix/` or `docs/` branches and focused PRs. `develop` is a possible future integration branch, not an existing requirement until configured. There is no automatic production deployment currently.

Use Conventional Commits. Do not force-push shared history, overwrite concurrent changes, commit real client data or merge/deploy implicitly. Before remote changes verify base/head and scope.

Work locally/test by default. Production deployments, database access/migrations, data edits and credential rotation require a specific user instruction and an intentionally configured environment. Staging follows configured CI/CD.

After work report concrete behavior/docs changed, important files, checks actually executed, assumptions, migration impact and remaining blockers. Link the PR or final files; keep the report concise.

## 14. Reference-code reuse

Dilo Records may be consulted only as an optional technical reference:

- Repository: [san7imo/dilo-deploy](https://github.com/san7imo/dilo-deploy).
- Team-local path: `/home/san7imo/Escritorio/Proyectos/dilo-deploy`. Check availability; do not pretend it is mounted in every agent environment.

Never introduce a runtime dependency on that application, import its DB wholesale, make it the default seed tenant or use its customer workflow as the product architecture.

Before reuse, inspect the source, remove branding/customer assumptions, adapt identity/tenancy/authorization, verify framework/types/storage/jobs compatibility and add relevant behavior tests. Record actual reused pieces in the PR.

Watch particularly for global Spatie roles/`teams=false`, User–Artist coupling, private attributes on User/Collaborator, float-based shares, universal split pools and email-based account association. These are reference decisions, not Ascanio invariants. Adopting Spatie later requires tenant-aware assignment/cache tests; package choice is not a substitute for policies.

Video references inform simple guided UX, not legal authority or backend architecture.

## 15. When to clarify and when to proceed

Proceed autonomously for reversible local decisions inside documented scope, including naming, small refactors, synthetic fixtures, docs fixes and tests. State reasonable assumptions; do not ask for routine implementation approval.

Clarify only when unresolved facts materially change the task's outcome:

- A change to tenant strategy, global visibility or cross-workspace coediting.
- Ambiguous identity claim/representative authority or disclosure of another party's private data.
- Missing contractual base, right type, territory, vigencia or activation rule.
- Signature provider/method, jurisdiction/template or CWR receiver/profile required by the current integration.
- Unsupported hosting that would require changing stack or security boundaries.
- A destructive/shared-history action or production operation without authorization.
- Scope conflicting with the agreed milestone or an existing accepted decision.

Complete independent authorized work while the specific question is pending. Do not invent legal/economic defaults or treat the entire roadmap as permission to implement every module.

