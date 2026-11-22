<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { accept } from '@/routes/resource-invitations';
import { index } from '@/routes/shared-documents';

defineProps<{
    invitation: {
        workspace: string;
        filename: string;
        number: number;
        sha256: string;
        projection: string;
        expires_at: string;
        timezone: string;
    };
}>();
const form = useForm({ confirmation: false });
</script>

<template>
    <Head title="Revisar documento compartido" />
    <AuthLayout
        title="Revisar invitación"
        description="Este acceso corresponde solamente a la versión indicada."
    >
        <form
            class="grid min-w-0 gap-4"
            @submit.prevent="form.post(accept().url)"
        >
            <h2 class="font-semibold break-words">
                {{ invitation.filename }} · Versión {{ invitation.number }}
            </h2>
            <p>Compartido desde {{ invitation.workspace }}.</p>
            <p>
                {{
                    invitation.projection === 'file'
                        ? 'Podrás consultar los datos y descargar el archivo completo de esta versión.'
                        : 'Podrás consultar los datos de esta versión. El acceso no incluye descargar su archivo.'
                }}
            </p>
            <p>
                Vence {{ invitation.expires_at }} · {{ invitation.timezone }}.
            </p>
            <details class="min-w-0 text-sm">
                <summary>Checksum SHA-256</summary>
                <p class="font-mono break-all">{{ invitation.sha256 }}</p>
            </details>
            <p class="text-sm">
                Aceptar no te incorpora al equipo ni concede acceso a otros
                documentos o versiones. El acceso puede revocarse antes de su
                vencimiento.
            </p>
            <label class="flex items-start gap-2"
                ><input
                    v-model="form.confirmation"
                    type="checkbox"
                    required
                />Acepto este acceso a la versión indicada.</label
            >
            <ul role="alert" class="text-sm text-red-600">
                <li v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </li>
            </ul>
            <Button :disabled="form.processing">Aceptar invitación</Button>
            <Link :href="index()" class="text-sm underline"
                >Ir a documentos compartidos</Link
            >
        </form>
    </AuthLayout>
</template>
