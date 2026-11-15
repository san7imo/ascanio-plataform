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
import { index, invitations as list } from '@/routes/workspaces';
import { store, revoke, resend } from '@/routes/workspaces/invitations';
import type { Paginated, Workspace } from '@/types/workspace';

type Assignment = { role_id: string; scope: 'tenant' | 'assigned_resources' };
type Invitation = {
    id: string;
    recipient: string;
    status: string;
    expires_at: string;
    delivery_status: string;
    failure_code: string | null;
    can_revoke: boolean;
    can_resend: boolean;
    roles: { id: string; name: string; scope: string }[];
};
const props = defineProps<{
    workspace: Workspace;
    invitations: Paginated<Invitation>;
    availableRoles: { id: string; name: string }[];
    mfaReady: boolean;
}>();
const form = useForm({
    recipient_email: '',
    assignments: [] as Assignment[],
    days: 7,
    password: '',
    code: '',
});
const selected = ref<Invitation | null>(null);
const mode = ref<'create' | 'revoke' | 'resend'>('create');
const search = ref(
    new URL(usePage().url, 'https://ascanio.invalid').searchParams.get(
        'search',
    ) ?? '',
);
const statusLabels: Record<string, string> = {
    pending: 'Pendiente',
    accepted: 'Aceptada',
    revoked: 'Revocada',
    expired: 'Vencida',
};
const deliveryLabels: Record<string, string> = {
    pending: 'Pendiente de envío',
    sent: 'Entregada al transporte de correo',
    simulated: 'Simulación local de correo',
    failed: 'Falló el envío; reintenta',
    blocked: 'Envío bloqueado',
};
const failureLabels: Record<string, string> = {
    queue_unavailable: 'No se pudo encolar el envío.',
    unsupported_transport:
        'Configura un transporte SMTP autorizado; estas invitaciones no se escriben en logs.',
    authority_changed:
        'Cambió la autoridad del invitador o los roles. Revoca y crea una nueva.',
    delivery_failed: 'Falló el transporte de correo.',
    attempts_exhausted: 'Se agotaron los reintentos automáticos.',
};
function open(
    invitation: Invitation | null,
    action: typeof mode.value = 'create',
) {
    selected.value = invitation;
    mode.value = action;
    form.reset();
    form.clearErrors();
}
function toggleRole(id: string, enabled: boolean) {
    form.assignments = form.assignments.filter((a) => a.role_id !== id);

    if (enabled) {
        form.assignments.push({ role_id: id, scope: 'tenant' });
    }
}
function submit() {
    const options = {
        onSuccess: () => open(null),
        onFinish: () => form.reset('password', 'code'),
    };

    if (mode.value === 'create') {
        form.transform((data) => data).post(
            store(props.workspace.id).url,
            options,
        );
    } else if (selected.value) {
        const parameters = {
            workspace: props.workspace.id,
            invitation: selected.value.id,
        };
        form.transform((data) => ({
            password: data.password,
            code: data.code,
        }));

        if (mode.value === 'resend') {
            form.post(resend(parameters).url, options);
        } else {
            form.delete(revoke(parameters).url, options);
        }
    }
}
</script>

<template>
    <Head :title="'Invitaciones · ' + workspace.name" />
    <AppLayout :breadcrumbs="[{ title: 'Mis espacios', href: index() }]">
        <section class="flex max-w-4xl flex-col gap-6 p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">
                Invitaciones de {{ workspace.name }}
            </h1>
            <form
                class="flex items-end gap-3"
                @submit.prevent="router.get(list(workspace.id).url, { search })"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="invitation-search">Buscar por correo</Label
                    ><Input
                        id="invitation-search"
                        v-model="search"
                        maxlength="120"
                    />
                </div>
                <Button variant="outline">Buscar</Button>
            </form>
            <p class="text-sm">
                {{ invitations.total }} invitaciones · {{ workspace.timezone }}
            </p>
            <ul class="grid gap-3">
                <li
                    v-for="invitation in invitations.data"
                    :key="invitation.id"
                    class="grid gap-2 rounded border p-4"
                >
                    <h2 class="font-medium break-all">
                        {{ invitation.recipient }}
                    </h2>
                    <p class="text-sm">
                        {{ statusLabels[invitation.status] }} · Vigente hasta
                        {{ invitation.expires_at }}
                    </p>
                    <p class="text-sm">
                        {{ deliveryLabels[invitation.delivery_status] }}
                    </p>
                    <p
                        v-if="invitation.failure_code"
                        class="text-sm text-red-600"
                    >
                        {{ failureLabels[invitation.failure_code] }}
                    </p>
                    <p
                        v-for="role in invitation.roles"
                        :key="role.id"
                        class="text-sm"
                    >
                        {{ role.name }} ·
                        {{
                            role.scope === 'tenant'
                                ? 'Todo el espacio'
                                : 'Recursos asignados'
                        }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="invitation.can_resend"
                            variant="outline"
                            @click="open(invitation, 'resend')"
                            >Reenviar</Button
                        >
                        <Button
                            v-if="invitation.can_revoke"
                            variant="destructive"
                            @click="open(invitation, 'revoke')"
                            >Revocar</Button
                        >
                    </div>
                </li>
            </ul>
            <WorkspacePagination v-bind="invitations" />
            <form
                class="grid gap-4 rounded border p-4"
                @submit.prevent="submit"
            >
                <h2 class="text-lg font-semibold">
                    {{
                        mode === 'create'
                            ? 'Invitar al equipo'
                            : mode === 'resend'
                              ? 'Reenviar invitación'
                              : 'Revocar invitación'
                    }}
                </h2>
                <template v-if="mode === 'create'">
                    <Label for="recipient-email">Correo del destinatario</Label
                    ><Input
                        id="recipient-email"
                        v-model="form.recipient_email"
                        type="email"
                        maxlength="254"
                        required
                    />
                    <Label for="invitation-days">Vigencia en días</Label
                    ><Input
                        id="invitation-days"
                        v-model="form.days"
                        type="number"
                        min="1"
                        max="14"
                        required
                    />
                    <fieldset class="grid gap-3">
                        <legend class="mb-2 font-medium">
                            Roles y alcances
                        </legend>
                        <div
                            v-for="role in availableRoles"
                            :key="role.id"
                            class="grid gap-2"
                        >
                            <label class="flex items-center gap-2"
                                ><input
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
                                />{{ role.name }}</label
                            >
                            <label
                                v-if="
                                    form.assignments.find(
                                        (a) => a.role_id === role.id,
                                    )
                                "
                                class="grid gap-2 text-sm"
                                >Alcance de {{ role.name }}
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
                                    <option value="tenant">
                                        Todo el espacio
                                    </option>
                                    <option value="assigned_resources">
                                        Recursos asignados
                                    </option>
                                </select>
                            </label>
                        </div>
                    </fieldset>
                    <p v-if="!availableRoles.length" class="text-sm">
                        Crea un rol ordinario que puedas delegar antes de
                        invitar.
                    </p>
                </template>
                <p v-else>
                    {{ selected?.recipient }}.
                    {{
                        mode === 'resend'
                            ? 'El enlace anterior dejará de funcionar. Se conserva la fecha de vencimiento.'
                            : 'El destinatario ya no podrá aceptar este enlace.'
                    }}
                </p>
                <SensitiveConfirmation
                    id="invitation-proof"
                    v-model:password="form.password"
                    v-model:code="form.code"
                    :mfa-ready="mfaReady"
                    :errors="form.errors"
                />
                <ul role="alert" class="text-sm text-red-600">
                    <li v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
                <div class="flex flex-wrap gap-3">
                    <Button :disabled="form.processing || !mfaReady">{{
                        mode === 'create'
                            ? 'Crear invitación'
                            : 'Confirmar cambio'
                    }}</Button>
                    <Button
                        v-if="selected"
                        type="button"
                        variant="outline"
                        @click="open(null)"
                        >Cancelar</Button
                    >
                </div>
            </form>
        </section>
    </AppLayout>
</template>
