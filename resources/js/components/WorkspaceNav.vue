<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index, members, roles, show } from '@/routes/workspaces';
import type { Workspace } from '@/types/workspace';

defineProps<{ workspace: Workspace }>();
</script>

<template>
    <nav class="flex flex-wrap gap-4 text-sm" aria-label="Espacio de trabajo">
        <Link :href="index()" class="underline">Cambiar de espacio</Link>
        <Link :href="show(workspace.id)" class="underline"
            >Resumen y ajustes</Link
        >
        <Link
            v-if="workspace.can_view_members"
            :href="members(workspace.id)"
            class="underline"
            >Membresías</Link
        >
        <Link
            v-if="workspace.can_manage_roles"
            :href="roles(workspace.id)"
            class="underline"
            >Roles y capacidades</Link
        >
    </nav>
</template>
