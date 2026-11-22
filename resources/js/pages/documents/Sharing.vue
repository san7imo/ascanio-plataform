<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { show } from '@/routes/documents';
import { resend, revoke, store } from '@/routes/documents/sharing';
import type { Paginated, Workspace } from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    document: { id: string; title: string };
    versions: { id: string; number: number; filename: string }[];
    canShareFile: boolean;
    invitations: Paginated<{
        id: string;
        recipient_email: string;
        projection: string;
        status: string;
        expires_at: string;
        delivery_status: string;
        can_resend: boolean;
        can_revoke: boolean;
    }>;
}>();
const versionId = ref(props.versions[0]?.id ?? '');
const form = useForm({
    recipient_email: '',
    projection: 'metadata',
    days: 7,
    password: '',
    code: '',
});
const submit = () => {
    if (!versionId.value) {
        return;
    }

    form.post(
        store([props.workspace.id, props.document.id, versionId.value]).url,
        {
            onFinish: () => form.reset('password', 'code'),
            onSuccess: () => form.reset('recipient_email'),
        },
    );
};
const states: Record<string, string> = {
    pending: 'Pendiente',
    accepted: 'Aceptada',
    revoked: 'Revocada',
    expired: 'Vencida',
    sent: 'Correo enviado',
    simulated: 'Entrega de prueba',
    failed: 'Entrega fallida',
    blocked: 'Entrega bloqueada',
    authority_changed: 'La autorización de origen cambió',
};
</script>

<template>
    <Head title="Compartir documento" />
    <AppLayout>
        <section class="grid max-w-3xl min-w-0 grid-cols-1 gap-6 p-4 sm:p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold break-words">
                Compartir · {{ document.title }}
            </h1>
            <p class="text-sm">
                La invitación permite consultar una sola versión. Puedes
                autorizar también su descarga completa. El acceso vence en la
                fecha indicada y no incorpora al destinatario al equipo.
            </p>
            <form
                v-if="versions.length"
                class="grid min-w-0 grid-cols-1 gap-4 rounded-lg border p-4"
                @submit.prevent="submit"
            >
                <label class="grid min-w-0 gap-1"
                    >Versión<select
                        v-model="versionId"
                        class="w-full min-w-0 rounded border p-2"
                        required
                    >
                        <option
                            v-for="version in versions"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.number }} · {{ version.filename }}
                        </option>
                    </select></label
                >
                <label class="grid min-w-0 gap-1"
                    >Correo del destinatario<input
                        v-model="form.recipient_email"
                        type="email"
                        maxlength="254"
                        required
                        class="w-full min-w-0 rounded border p-2"
                        autocomplete="off"
                /></label>
                <label class="grid min-w-0 gap-1"
                    >Acceso<select
                        v-model="form.projection"
                        class="w-full min-w-0 rounded border p-2"
                    >
                        <option value="metadata">
                            Consultar datos de esta versión
                        </option>
                        <option v-if="canShareFile" value="file">
                            Consultar datos y descargar el archivo completo
                        </option>
                    </select></label
                >
                <label class="grid min-w-0 gap-1"
                    >Vigencia en días<input
                        v-model="form.days"
                        type="number"
                        min="1"
                        max="14"
                        required
                        class="w-full min-w-0 rounded border p-2"
                /></label>
                <p class="text-sm">
                    Confirma con tu contraseña y un código de tu autenticador.
                    Debes tener la autenticación de dos factores activada.
                </p>
                <label class="grid min-w-0 gap-1"
                    >Contraseña<input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full min-w-0 rounded border p-2"
                /></label>
                <label class="grid min-w-0 gap-1"
                    >Código del autenticador<input
                        v-model="form.code"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        required
                        autocomplete="one-time-code"
                        class="w-full min-w-0 rounded border p-2"
                /></label>
                <ul role="alert" class="text-sm text-red-600">
                    <li v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
                <Button :disabled="form.processing">Enviar invitación</Button>
            </form>
            <p v-else>
                Para compartir, necesitas una versión con análisis completado.
            </p>
            <h2 class="text-lg font-semibold">Invitaciones y accesos</h2>
            <ul class="grid gap-3">
                <li
                    v-for="invitation in invitations.data"
                    :key="invitation.id"
                    class="grid min-w-0 gap-2 rounded-lg border p-4"
                >
                    <p class="break-all">{{ invitation.recipient_email }}</p>
                    <p class="text-sm">
                        {{
                            invitation.projection === 'file'
                                ? 'Datos y archivo completo'
                                : 'Solo datos de la versión'
                        }}
                        · {{ states[invitation.status] }}
                    </p>
                    <p class="text-sm">
                        Vence {{ invitation.expires_at }} ·
                        {{ workspace.timezone }} ·
                        {{ states[invitation.delivery_status] }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="invitation.can_revoke"
                            variant="destructive"
                            @click="
                                router.delete(
                                    revoke([
                                        workspace.id,
                                        document.id,
                                        invitation.id,
                                    ]).url,
                                )
                            "
                            >Revocar acceso</Button
                        >
                        <Button
                            v-if="invitation.can_resend"
                            variant="outline"
                            @click="
                                router.post(
                                    resend([
                                        workspace.id,
                                        document.id,
                                        invitation.id,
                                    ]).url,
                                )
                            "
                            >Reenviar con enlace nuevo</Button
                        >
                    </div>
                </li>
            </ul>
            <WorkspacePagination v-bind="invitations" />
            <Link
                :href="show([workspace.id, document.id])"
                class="text-sm underline"
                >Volver al documento</Link
            >
        </section>
    </AppLayout>
</template>
