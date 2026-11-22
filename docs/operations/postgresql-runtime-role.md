# PostgreSQL: separación entre migración y runtime

Alcance: procedimiento de referencia para una futura configuración autorizada en una base exclusiva de Ascanio. No se ejecutó en Kroma ni se confirmó allí disponibilidad de roles, PostgreSQL o permisos DDL. F2, P1, D1 y G1 lo verifican con un LOGIN efímero en ascanio_test; el usuario propietario del entorno local/CI no pasa a ser una cuenta de producción.

La cuenta migradora ejecuta DDL y es dueña de tablas/funciones, o tiene la facultad de un rol propietario separado. La cuenta runtime de web/workers no posee objetos, no es miembro de ese rol, no puede crear roles/bases ni tiene superuser/BYPASSRLS. NOINHERIT por sí solo no basta: también hay que comprobar que no pueda hacer SET ROLE al propietario. Esta configuración no incorpora RLS ni reemplaza las policies de aplicación.

## Aplicación futura

Primero confirmar O0 con el proveedor, el nombre de la base exclusiva, propietario real, privilegios de PUBLIC y cuentas existentes. Si la base/schema se comparte con otra aplicación, evaluar el impacto antes de adoptar este ejemplo. No retirar permisos de proyectos ajenos ni ejecutar estas instrucciones en producción sin tarea y entorno autorizados.

Los nombres ascanio/ascanio_runtime son ejemplos que el operador adapta al entorno comprobado. La credencial se provisiona por un canal seguro fuera del SQL versionado, sin compartirla ni imprimirla. Aplicar las migraciones con la cuenta migradora y después conceder únicamente las operaciones existentes:

~~~sql
-- Solo un administrador autorizado de la base dedicada.
CREATE ROLE ascanio_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE
    NOINHERIT NOBYPASSRLS;
GRANT CONNECT ON DATABASE ascanio TO ascanio_runtime;
GRANT USAGE ON SCHEMA public TO ascanio_runtime;

-- Comprobar impacto en esa base exclusiva antes de retirar CREATE público.
REVOKE CREATE ON SCHEMA public FROM PUBLIC;

GRANT SELECT, INSERT, UPDATE, DELETE ON
    users, password_reset_tokens, sessions, cache, cache_locks,
    jobs, job_batches, failed_jobs,
    parties, persons, organizations, tenants, tenant_memberships,
    roles, role_permissions, membership_roles,
    team_invitations, team_invitation_roles,
    tenant_parties, party_identifiers, tenant_party_business_types, documents
TO ascanio_runtime;
GRANT USAGE, SELECT ON SEQUENCE jobs_id_seq, failed_jobs_id_seq
TO ascanio_runtime;

-- Catálogo preparado por migraciones. Runtime no lo modifica.
GRANT SELECT ON permissions, business_types TO ascanio_runtime;

-- Revisiones privadas: insertar y consultar; nunca reescribir ni eliminar.
REVOKE ALL ON tenant_party_revisions FROM PUBLIC;
REVOKE ALL ON tenant_party_revisions FROM ascanio_runtime;
GRANT SELECT, INSERT ON tenant_party_revisions TO ascanio_runtime;

-- Payload de versión inmutable; solo se actualiza el workflow de escaneo.
REVOKE ALL ON document_versions FROM PUBLIC;
REVOKE ALL ON document_versions FROM ascanio_runtime;
GRANT SELECT, INSERT ON document_versions TO ascanio_runtime;
GRANT UPDATE
    (scan_status, scan_generation, scan_requested_by, source_membership_id,
     source_access_version, failure_code, scanned_at, updated_at)
ON document_versions TO ascanio_runtime;

-- G1: objetivo/procedencia inmutables; solo workflow y revocación.
REVOKE ALL ON resource_invitations, resource_grants FROM PUBLIC;
REVOKE ALL ON resource_invitations, resource_grants FROM ascanio_runtime;
GRANT SELECT, INSERT ON resource_invitations, resource_grants TO ascanio_runtime;
GRANT UPDATE
    (status, accepted_by_id, accepted_at, token_hash, delivery_version,
     delivery_status, delivery_attempts, last_attempt_at, delivered_at,
     failure_code, updated_at)
ON resource_invitations TO ascanio_runtime;
GRANT UPDATE (revoked_at, updated_at) ON resource_grants TO ascanio_runtime;

-- Auditar grants anteriores, incluidos roles heredados y PUBLIC.
REVOKE ALL ON audit_events FROM PUBLIC;
REVOKE ALL ON audit_events FROM ascanio_runtime;
GRANT SELECT ON audit_events TO ascanio_runtime;
GRANT INSERT
    (id, tenant_id, actor_id, action, resource_type, resource_id, changes, occurred_at)
ON audit_events TO ascanio_runtime;
~~~

INSERT es por columnas: actor_type usa el default user y runtime no puede atribuir un evento al actor migration. No conceder UPDATE, DELETE, TRUNCATE, ownership, privilegios para desactivar triggers ni capacidad de cambiar session_replication_role. El código normal RecordAudit no envía actor_type; las migraciones que registran su procedencia usan la cuenta migradora.

No conceder ALL ni aplicar defaults que otorguen DML a todas las tablas futuras. Revisar cada ampliación de esquema al introducir módulos/colas. El ejemplo cubre las tablas actuales; migraciones y su secuencia no requieren escrituras runtime. La aplicación no debe ejecutar artisan migrate automáticamente con la cuenta web. Los respaldos/restauraciones usan su procedimiento autorizado, no elevan privilegios del runtime.

## Verificación

Abrir una conexión nueva autenticada como runtime, no una sesión del administrador con SET ROLE. Comprobar current_user y session_user, atributos en pg_roles, ownership de objetos, has_schema_privilege('CREATE'), relaciones de membresía con el migrador y permisos de tabla/columna sobre audit_events. Probar con datos sintéticos una creación de workspace y una mutación de roles que inserten auditoría, además de crear/editar una contraparte con revisiones inmutables. D1 amplía la prueba a carga/escaneo sintéticos y descarga autenticada de una versión, además de ejecución de advisory locks transaccionales sin elevar el login.

Las denegaciones esperadas deben ocurrir por permisos, además del trigger append-only. En el test F2 se exige SQLSTATE 42501 para UPDATE, DELETE, TRUNCATE, ALTER TABLE DISABLE TRIGGER, DROP TABLE/FUNCTION, SET ROLE al migrador, session_replication_role e INSERT de actor_type=migration. P1 añade denegaciones de edición/borrado/truncate de revisiones, desactivación de su trigger y edición del catálogo business_types. D1 comprueba también la denegación de UPDATE sobre checksum, DELETE/TRUNCATE y desactivación de guards en document_versions. G1 verifica compartir/aceptar/descargar/revocar con ese login y deniega reescribir destinatario/proyección, borrar historial, truncar grants o desactivar su guard. Mantener el catálogo y el historial original tras los intentos. Solo ejecutar pruebas destructivas de denegación en una base sintética preparada; no usar producción para probar si DROP falla.

GovernanceRuntimeRoleTest provisiona un LOGIN aleatorio mediante una conexión migradora separada, conecta con sus propias credenciales, opera y comprueba esas denegaciones. Los roles PostgreSQL existen a nivel de servidor. El login de montaje necesita CREATEROLE en el servidor de pruebas; los grants de aplicación del LOGIN efímero se limitan a ascanio_test. No se omite el test si falta ese privilegio. El LOGIN no posee objetos; al terminar se retiran únicamente sus grants y se elimina. La reconstrucción migrate:fresh está limitada por el guard a ascanio_test.

Configurar después web/workers con runtime, guardar la credencial fuera de Git, conservar la migradora fuera del proceso de aplicación y comprobar reinicios/pools. Registrar evidencia fechada en O0 al validar Kroma. Hasta entonces, la prueba local es evidencia técnica y la separación operativa continúa pendiente.
