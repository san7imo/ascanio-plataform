<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('status', 20)->default('declared');
            $table->string('provenance', 40);
            $table->timestampsTz();
            $table->unique(['id', 'kind']);
        });

        foreach (['persons' => 'person', 'organizations' => 'organization'] as $name => $kind) {
            Schema::create($name, function (Blueprint $table) use ($kind) {
                $table->uuid('party_id')->primary();
                $table->string('kind', 20)->default($kind);
                $table->foreign(['party_id', 'kind'])->references(['id', 'kind'])->on('parties')->restrictOnDelete();
            });
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_kind CHECK (kind = '{$kind}')");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active');
            $table->foreignUuid('person_id')->nullable()->unique()->references('party_id')->on('persons')->restrictOnDelete();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('operator_party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('creation_key_hash', 64);
            $table->string('creation_payload_hash', 64);
            $table->string('name', 120);
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->string('locale', 5);
            $table->string('timezone', 64);
            $table->timestampsTz();
            $table->unique(['created_by', 'creation_key_hash']);
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->boolean('is_owner')->default(false);
            $table->timestampTz('accepted_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'status', 'tenant_id']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('role_id');
            $table->foreignUuid('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->primary(['tenant_id', 'role_id', 'permission_id']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->restrictOnDelete();
        });

        Schema::create('membership_roles', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('membership_id');
            $table->uuid('role_id');
            $table->string('access_scope', 30);
            $table->primary(['tenant_id', 'membership_id', 'role_id']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->restrictOnDelete();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('resource_type', 30);
            $table->uuid('resource_id');
            $table->jsonb('changes');
            $table->timestampTz('occurred_at');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'occurred_at']);
        });

        // DDL only: these checks enforce global identity and workspace invariants;
        // they do not grant administrative access or bypass tenant query scopes.
        DB::unprepared(<<<'SQL'
            ALTER TABLE parties ADD CONSTRAINT parties_kind CHECK (kind IN ('person', 'organization'));
            ALTER TABLE parties ADD CONSTRAINT parties_status CHECK (status IN ('declared', 'suspended'));
            ALTER TABLE users ADD CONSTRAINT users_status CHECK (status IN ('active', 'suspended', 'revoked'));
            ALTER TABLE tenants ADD CONSTRAINT tenants_status CHECK (status IN ('active', 'suspended'));
            ALTER TABLE tenants ADD CONSTRAINT tenants_locale CHECK (locale IN ('es', 'en', 'pt'));
            ALTER TABLE tenant_memberships ADD CONSTRAINT membership_status CHECK (status IN ('active', 'suspended', 'revoked'));
            ALTER TABLE tenant_memberships ADD CONSTRAINT membership_revocation CHECK ((status = 'revoked') = (revoked_at IS NOT NULL));
            ALTER TABLE membership_roles ADD CONSTRAINT membership_scope CHECK (access_scope IN ('tenant', 'assigned_resources'));

            CREATE OR REPLACE FUNCTION check_party_subtype() RETURNS trigger LANGUAGE plpgsql SET search_path = public, pg_temp AS $$
            DECLARE party_key uuid; party_kind text;
            BEGIN
                IF TG_TABLE_NAME = 'parties' THEN
                    party_key := COALESCE(NEW.id, OLD.id);
                ELSE
                    party_key := COALESCE(NEW.party_id, OLD.party_id);
                END IF;
                SELECT kind INTO party_kind FROM parties WHERE id = party_key;
                IF FOUND AND NOT (
                    (party_kind = 'person' AND EXISTS (SELECT 1 FROM persons WHERE party_id = party_key)) OR
                    (party_kind = 'organization' AND EXISTS (SELECT 1 FROM organizations WHERE party_id = party_key))
                ) THEN
                    RAISE EXCEPTION 'Party requires its matching subtype' USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$;
            CREATE CONSTRAINT TRIGGER party_subtype AFTER INSERT OR UPDATE ON parties
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_party_subtype();
            CREATE CONSTRAINT TRIGGER person_subtype AFTER DELETE OR UPDATE ON persons
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_party_subtype();
            CREATE CONSTRAINT TRIGGER organization_subtype AFTER DELETE OR UPDATE ON organizations
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_party_subtype();
            CREATE OR REPLACE FUNCTION reject_subtype_relink() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.party_id <> OLD.party_id THEN
                    RAISE EXCEPTION 'Subtype identity cannot be reassigned' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER person_identity BEFORE UPDATE ON persons FOR EACH ROW EXECUTE FUNCTION reject_subtype_relink();
            CREATE TRIGGER organization_identity BEFORE UPDATE ON organizations FOR EACH ROW EXECUTE FUNCTION reject_subtype_relink();

            CREATE OR REPLACE FUNCTION lock_workspace_ownership() RETURNS trigger LANGUAGE plpgsql SET search_path = public, pg_temp AS $$
            BEGIN
                IF TG_TABLE_NAME = 'users' THEN
                    PERFORM t.id FROM tenants t JOIN tenant_memberships m ON m.tenant_id = t.id
                        WHERE m.user_id = OLD.id AND m.is_owner ORDER BY t.id FOR UPDATE OF t;
                ELSE
                    PERFORM id FROM tenants WHERE id IN (OLD.tenant_id, NEW.tenant_id) ORDER BY id FOR UPDATE;
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER lock_membership_ownership BEFORE UPDATE OR DELETE ON tenant_memberships
                FOR EACH ROW EXECUTE FUNCTION lock_workspace_ownership();
            CREATE TRIGGER lock_account_ownership BEFORE UPDATE OF status ON users
                FOR EACH ROW EXECUTE FUNCTION lock_workspace_ownership();

            CREATE OR REPLACE FUNCTION assert_workspace_owner(workspace_key uuid) RETURNS void LANGUAGE plpgsql SET search_path = public, pg_temp AS $$
            BEGIN
                PERFORM id FROM tenants WHERE id = workspace_key FOR UPDATE;
                IF FOUND AND NOT EXISTS (
                    SELECT 1 FROM tenant_memberships m JOIN users u ON u.id = m.user_id
                    WHERE m.tenant_id = workspace_key AND m.is_owner AND m.status = 'active' AND u.status = 'active'
                ) THEN
                    RAISE EXCEPTION 'Workspace requires an active owner' USING ERRCODE = '23514';
                END IF;
            END;
            $$;
            CREATE OR REPLACE FUNCTION check_workspace_ownership() RETURNS trigger LANGUAGE plpgsql SET search_path = public, pg_temp AS $$
            DECLARE workspace_key uuid;
            BEGIN
                IF TG_TABLE_NAME = 'users' THEN
                    FOR workspace_key IN SELECT tenant_id FROM tenant_memberships WHERE user_id = NEW.id AND is_owner ORDER BY tenant_id LOOP
                        PERFORM assert_workspace_owner(workspace_key);
                    END LOOP;
                ELSIF TG_TABLE_NAME = 'tenants' THEN
                    PERFORM assert_workspace_owner(NEW.id);
                ELSE
                    IF TG_OP <> 'INSERT' THEN PERFORM assert_workspace_owner(OLD.tenant_id); END IF;
                    IF TG_OP <> 'DELETE' THEN PERFORM assert_workspace_owner(NEW.tenant_id); END IF;
                END IF;
                RETURN NULL;
            END;
            $$;
            CREATE CONSTRAINT TRIGGER workspace_owner AFTER INSERT OR UPDATE ON tenants
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_workspace_ownership();
            CREATE CONSTRAINT TRIGGER membership_owner AFTER INSERT OR UPDATE OR DELETE ON tenant_memberships
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_workspace_ownership();
            CREATE CONSTRAINT TRIGGER account_owner AFTER UPDATE ON users
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_workspace_ownership();

            CREATE OR REPLACE FUNCTION reject_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Audit history is append-only' USING ERRCODE = '23514'; END;
            $$;
            CREATE TRIGGER audit_append_only BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_events
                FOR EACH STATEMENT EXECUTE FUNCTION reject_audit_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS account_owner ON users; DROP TRIGGER IF EXISTS lock_account_ownership ON users;');
        foreach (['audit_events', 'membership_roles', 'role_permissions', 'roles', 'permissions', 'tenant_memberships', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn(['person_id', 'status']);
        });
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('parties');
        DB::unprepared('DROP FUNCTION check_party_subtype(), reject_subtype_relink(), check_workspace_ownership(), lock_workspace_ownership(), assert_workspace_owner(uuid), reject_audit_mutation();');
    }
};
