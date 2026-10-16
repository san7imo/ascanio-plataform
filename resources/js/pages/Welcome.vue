<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
withDefaults(defineProps<{ canRegister: boolean }>(), { canRegister: true });
</script>

<template>
    <Head title="Bienvenido" />
    <main
        class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-8 text-foreground"
    >
        <h1 class="text-3xl font-semibold">Ascanio Publishing Platform</h1>
        <p>Tu cuenta para organizar la operación musical.</p>
        <Link v-if="$page.props.auth.user" :href="dashboard()" class="underline"
            >Ir al inicio</Link
        >
        <nav v-else class="flex gap-6">
            <Link :href="login()" class="underline">Iniciar sesión</Link>
            <Link v-if="canRegister" :href="register()" class="underline"
                >Crear cuenta</Link
            >
        </nav>
    </main>
</template>
