<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { accept } from '@/routes/team-invitations';
import { index } from '@/routes/workspaces';

type Role = {
    id: string;
    name: string;
    scope: string;
    capabilities: { label: string; delegable: boolean }[];
};
defineProps<{
    invitation: {
        available: boolean;
        workspace?: string;
        expires_at?: string;
        timezone?: string;
        roles?: Role[];
    };
}>();
const form = useForm({ confirmation: false });
</script>

<template>
    <Head title="Revisar invitación" />
    <AuthLayout
        title="Revisar invitación"
        description="Aceptar te incorpora al equipo con los roles y alcances indicados."
    >
        <form
            v-if="invitation.available"
            class="grid gap-4"
            @submit.prevent="form.post(accept().url)"
        >
            <h2 class="text-lg font-semibold">{{ invitation.workspace }}</h2>
            <p class="text-sm">
                Vigente hasta {{ invitation.expires_at }} ·
                {{ invitation.timezone }}
            </p>
            <ul class="grid gap-3">
                <li
                    v-for="role in invitation.roles"
                    :key="role.id"
                    class="rounded border p-3"
                >
                    <p class="font-medium">
                        {{ role.name }} ·
                        {{
                            role.scope === 'tenant'
                                ? 'Todo el espacio'
                                : 'Recursos asignados'
                        }}
                    </p>
                    <ul class="mt-2 text-sm">
                        <li
                            v-for="capability in role.capabilities"
                            :key="capability.label"
                        >
                            {{ capability.label
                            }}{{
                                capability.delegable ? ' · Puede delegarse' : ''
                            }}
                        </li>
                    </ul>
                </li>
            </ul>
            <p class="text-sm">
                Los roles que ya tengas se conservan. Los recursos asignados
                solo dan acceso cuando existe una asignación autorizada.
            </p>
            <label class="flex items-start gap-2"
                ><input
                    v-model="form.confirmation"
                    type="checkbox"
                    required
                />Confirmo que quiero incorporarme con estos permisos.</label
            >
            <ul role="alert" class="text-sm text-red-600">
                <li v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </li>
            </ul>
            <Button :disabled="form.processing">Aceptar invitación</Button>
        </form>
        <p v-else role="alert">
            Esta invitación no está disponible para tu cuenta. Comprueba que
            usas el correo verificado del destinatario o solicita una nueva
            invitación.
        </p>
        <Link :href="index()" class="text-sm underline"
            >Volver a mis espacios</Link
        >
    </AuthLayout>
</template>
