<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import SensitiveConfirmation from '@/components/SensitiveConfirmation.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, members as membersRoute } from '@/routes/workspaces';
import { roles, status } from '@/routes/workspaces/members';
import { transfer } from '@/routes/workspaces/ownership';
import type { Paginated, Workspace } from '@/types/workspace';

type Assignment = { role_id: string; scope: 'tenant' | 'assigned_resources' };
type Member = {
    id: string;
    name: string;
    status: string;
    is_owner: boolean;
    can_assign: boolean;
    can_change_status: boolean;
    accepted_at: string;
    assignments?: (Assignment & { name: string })[];
    can_receive_ownership?: boolean;
};
const props = defineProps<{
    workspace: Workspace;
    members: Paginated<Member>;
    availableRoles: { id: string; name: string }[];
    mfaReady: boolean;
}>();
const member = ref<Member | null>(null);
const mode = ref<'roles' | 'status' | 'transfer'>('status');
const form = useForm({
    password: '',
    code: '',
    status: '',
    assignments: [] as Assignment[],
    membership_id: '',
    confirmation: false,
});
const search = ref(
    new URL(usePage().url, 'https://ascanio.invalid').searchParams.get(
        'search',
    ) ?? '',
);
const statuses: Record<string, string> = {
    active: 'Activa',
    suspended: 'Suspendida',
    revoked: 'Revocada',
};
const breadcrumbs = [{ title: 'Mis espacios', href: index() }];
function open(target: Member, action: typeof mode.value, nextStatus = '') {
    member.value = target;
    mode.value = action;
    form.reset();
    form.clearErrors();
    form.status = nextStatus;
    form.membership_id = target.id;
    form.assignments =
        target.assignments?.map((a) => ({
            role_id: a.role_id,
            scope: a.scope,
        })) ?? [];
}
function toggleRole(roleId: string, enabled: boolean) {
    form.assignments = form.assignments.filter((a) => a.role_id !== roleId);

    if (enabled) {
        form.assignments.push({ role_id: roleId, scope: 'tenant' });
    }
}
function submit() {
    if (!member.value) {
        return;
    }

    const options = {
        onSuccess: () => {
            member.value = null;
        },
        onFinish: () => form.reset('password', 'code'),
    };
    const params = {
        workspace: props.workspace.id,
        membership: member.value.id,
    };

    if (mode.value === 'roles') {
        form.transform((data) => ({
            password: data.password,
            code: data.code,
            assignments: data.assignments,
        })).put(roles(params).url, options);
    } else if (mode.value === 'status') {
        form.transform((data) => ({
            password: data.password,
            code: data.code,
            status: data.status,
        })).patch(status(params).url, options);
    } else {
        form.transform((data) => ({
            password: data.password,
            code: data.code,
            membership_id: data.membership_id,
            confirmation: data.confirmation,
        })).post(transfer(props.workspace.id).url, options);
    }
}
</script>

<template>
    <Head :title="'Membresías · ' + workspace.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex max-w-4xl flex-col gap-6 p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">
                Membresías de {{ workspace.name }}
            </h1>
            <form
                class="flex items-end gap-3"
                @submit.prevent="
                    router.get(membersRoute(workspace.id).url, { search })
                "
            >
                <div class="grid flex-1 gap-2">
                    <Label for="member-search">Buscar por nombre</Label
                    ><Input
                        id="member-search"
                        v-model="search"
                        maxlength="120"
                    />
                </div>
                <Button variant="outline">Buscar</Button>
            </form>
            <p class="text-sm">
                {{ members.total }} membresías · Horario de
                {{ workspace.timezone }}
            </p>
            <p
                v-if="workspace.can_transfer_ownership"
                class="text-sm text-muted-foreground"
            >
                Puedes transferir el gobierno a otro miembro activo con correo
                verificado y MFA confirmado.
            </p>
            <ul class="grid gap-3">
                <li
                    v-for="item in members.data"
                    :key="item.id"
                    class="grid gap-3 rounded-lg border p-4"
                >
                    <div>
                        <p class="font-medium">{{ item.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ item.is_owner ? 'Propietario · ' : ''
                            }}{{ statuses[item.status] }} ·
                            {{ item.accepted_at }}
                        </p>
                        <p
                            v-for="assignment in item.assignments"
                            :key="assignment.role_id"
                            class="text-sm"
                        >
                            {{ assignment.name }} ·
                            {{
                                assignment.scope === 'tenant'
                                    ? 'Todo el espacio'
                                    : 'Recursos asignados'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="item.can_assign"
                            variant="outline"
                            @click="open(item, 'roles')"
                            >Editar roles</Button
                        >
                        <template v-if="item.can_change_status">
                            <Button
                                v-if="item.status !== 'active'"
                                variant="outline"
                                @click="open(item, 'status', 'active')"
                                >Reactivar</Button
                            >
                            <Button
                                v-if="item.status === 'active'"
                                variant="outline"
                                @click="open(item, 'status', 'suspended')"
                                >Suspender</Button
                            >
                            <Button
                                v-if="item.status !== 'revoked'"
                                variant="destructive"
                                @click="open(item, 'status', 'revoked')"
                                >Revocar</Button
                            >
                        </template>
                        <Button
                            v-if="item.can_receive_ownership"
                            variant="outline"
                            @click="open(item, 'transfer')"
                            >Transferir gobierno</Button
                        >
                    </div>
                </li>
            </ul>
            <WorkspacePagination v-bind="members" />
            <form
                v-if="member"
                class="grid gap-4 rounded-lg border p-5"
                @submit.prevent="submit"
            >
                <h2 class="text-lg font-semibold">
                    {{
                        mode === 'roles'
                            ? 'Roles'
                            : mode === 'transfer'
                              ? 'Transferir gobierno'
                              : 'Cambiar estado'
                    }}
                    · {{ member.name }}
                </h2>
                <template v-if="mode === 'roles'">
                    <p class="text-sm">
                        Los recursos asignados solo dan acceso cuando existe una
                        asignación autorizada. Quitar todos los roles retira el
                        acceso a este espacio.
                    </p>
                    <div
                        v-for="role in availableRoles"
                        :key="role.id"
                        class="grid gap-2"
                    >
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="
                                    form.assignments.some(
                                        (a) => a.role_id === role.id,
                                    )
                                "
                                @change="
                                    toggleRole(
                                        role.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />{{ role.name }}
                        </label>
                        <label
                            v-if="
                                form.assignments.find(
                                    (a) => a.role_id === role.id,
                                )
                            "
                            class="grid gap-1 text-sm"
                        >
                            Alcance de {{ role.name }}
                            <select
                                class="rounded border p-2"
                                :value="
                                    form.assignments.find(
                                        (a) => a.role_id === role.id,
                                    )?.scope
                                "
                                @change="
                                    form.assignments.find(
                                        (a) => a.role_id === role.id,
                                    )!.scope = (
                                        $event.target as HTMLSelectElement
                                    ).value as Assignment['scope']
                                "
                            >
                                <option value="tenant">Todo el espacio</option>
                                <option value="assigned_resources">
                                    Recursos asignados
                                </option>
                            </select>
                        </label>
                    </div>
                    <p v-if="!availableRoles.length" class="text-sm">
                        No hay roles que puedas asignar con tu autoridad
                        delegable.
                    </p>
                </template>
                <p v-else-if="mode === 'status'">
                    La membresía quedará
                    {{ statuses[form.status]?.toLowerCase() }}. El cambio se
                    aplica también a sus otras sesiones en este espacio.
                </p>
                <template v-else>
                    <p>
                        {{ member.name }} recibirá el gobierno de este espacio y
                        tú dejarás de ser propietario. Conservarás los roles
                        independientes que tengas asignados.
                    </p>
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="form.confirmation"
                            type="checkbox"
                            required
                        />Confirmo la transferencia a esta persona.</label
                    >
                </template>
                <SensitiveConfirmation
                    id="member-proof"
                    v-model:password="form.password"
                    v-model:code="form.code"
                    :mfa-ready="mfaReady"
                    :errors="form.errors"
                />
                <ul
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="text-sm text-red-600"
                >
                    <li v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
                <div class="flex gap-3">
                    <Button :disabled="form.processing || !mfaReady"
                        >Confirmar cambio</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        @click="
                            member = null;
                            form.reset();
                        "
                        >Cancelar</Button
                    >
                </div>
            </form>
        </section>
    </AppLayout>
</template>
