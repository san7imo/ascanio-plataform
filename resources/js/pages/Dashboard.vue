<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as workspaces } from '@/routes/workspaces';
import type { BreadcrumbItem } from '@/types';
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: dashboard() }];
</script>

<template>
    <Head title="Inicio" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex flex-col gap-4 p-6">
            <h1 class="text-2xl font-semibold">Bienvenido a Ascanio</h1>
            <p>
                Tu cuenta está lista. Puedes gestionar tu perfil y la seguridad
                de acceso desde los ajustes.
            </p>
            <Link :href="workspaces()" class="font-medium underline"
                >Crear o seleccionar un espacio de trabajo</Link
            >
        </section>
    </AppLayout>
</template>
