<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { claim } from '@/routes/team-invitations';

const props = defineProps<{ invitationId: string }>();
const form = useForm({ token: '' });
const started = ref(false);
onMounted(() => {
    const token = new URLSearchParams(window.location.hash.slice(1)).get(
        'token',
    );
    window.history.replaceState(
        window.history.state,
        '',
        window.location.pathname,
    );

    if (!token) {
        return;
    }

    started.value = true;
    form.token = token;
    form.post(claim(props.invitationId).url, {
        onFinish: () => form.reset('token'),
    });
});
</script>

<template>
    <Head title="Abrir invitación" />
    <AuthLayout
        title="Invitación al equipo"
        description="Comprobaremos el enlace antes de pedirte iniciar sesión o crear una cuenta."
    >
        <p v-if="form.processing" role="status">Comprobando invitación…</p>
        <p v-else-if="Object.keys(form.errors).length || !started" role="alert">
            El enlace no está disponible. Abre de nuevo la invitación original o
            solicita una nueva.
        </p>
    </AuthLayout>
</template>
