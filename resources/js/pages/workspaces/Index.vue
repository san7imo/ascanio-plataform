<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, index, select, show } from '@/routes/workspaces';
import type { Paginated } from '@/types/workspace';

defineProps<{ workspaces: Paginated<{ id: string; name: string }> }>();
const search = ref(
    new URL(usePage().url, 'https://ascanio.invalid').searchParams.get(
        'search',
    ) ?? '',
);
const breadcrumbs = [{ title: 'Mis espacios', href: index() }];
</script>

<template>
    <Head title="Mis espacios" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex max-w-3xl flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Mis espacios de trabajo</h1>
                <Button as-child
                    ><Link :href="create()">Crear espacio</Link></Button
                >
            </div>
            <p class="text-muted-foreground">
                Selecciona el espacio en el que quieres trabajar. Cada uno
                mantiene su información y permisos.
            </p>
            <form
                class="flex items-end gap-3"
                @submit.prevent="router.get(index().url, { search })"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="workspace-search">Buscar mis espacios</Label
                    ><Input
                        id="workspace-search"
                        v-model="search"
                        maxlength="120"
                    />
                </div>
                <Button type="submit" variant="outline">Buscar</Button>
            </form>
            <p class="text-sm">{{ workspaces.total }} espacios disponibles</p>
            <p v-if="!workspaces.data.length">
                No hay espacios disponibles con esta búsqueda. Puedes crear uno
                para tu operación.
            </p>
            <ul class="grid gap-3">
                <li
                    v-for="workspace in workspaces.data"
                    :key="workspace.id"
                    class="flex items-center justify-between gap-4 rounded-lg border p-4"
                >
                    <Link
                        :href="show(workspace.id)"
                        class="font-medium underline"
                        >{{ workspace.name }}</Link
                    >
                    <Button
                        variant="outline"
                        @click="router.post(select(workspace.id).url)"
                        >Seleccionar</Button
                    >
                </li>
            </ul>
            <WorkspacePagination v-bind="workspaces" />
        </section>
    </AppLayout>
</template>
