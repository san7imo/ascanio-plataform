<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, index, show } from '@/routes/parties';
import type { PartySummary } from '@/types/party';
import type { Paginated, Workspace } from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    parties: Paginated<PartySummary>;
    search: string;
    canCreate: boolean;
}>();
const term = ref(props.search);
watch(
    () => props.search,
    (value) => (term.value = value),
);
watch(
    () => props.workspace.id,
    () => (term.value = props.search),
);
const search = () =>
    router.get(
        index(props.workspace.id).url,
        { search: term.value },
        { preserveState: true, replace: true },
    );
</script>

<template>
    <Head :title="`Contrapartes · ${workspace.name}`" />
    <AppLayout
        :breadcrumbs="[{ title: 'Contrapartes', href: index(workspace.id) }]"
    >
        <section class="grid max-w-4xl gap-6 p-4 sm:p-6">
            <WorkspaceNav :workspace="workspace" />
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Contrapartes</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Fichas locales de {{ workspace.name }}
                    </p>
                </div>
                <Button v-if="canCreate" as-child
                    ><Link :href="create(workspace.id)"
                        >Crear ficha</Link
                    ></Button
                >
            </div>
            <p class="text-sm text-muted-foreground">
                Registra personas y organizaciones sin exigirles una cuenta. Las
                fichas son provisionales; sus actividades e identificadores no
                acreditan identidad, representación ni derechos.
            </p>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="search"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="search">Buscar por nombre visible</Label
                    ><Input id="search" v-model="term" maxlength="120" />
                </div>
                <Button variant="secondary">Buscar</Button>
            </form>
            <p class="text-sm">{{ parties.total }} fichas en este espacio</p>
            <ul v-if="parties.data.length" class="grid gap-3">
                <li
                    v-for="party in parties.data"
                    :key="party.id"
                    class="grid gap-1 rounded-lg border p-4"
                >
                    <Link
                        :href="show([workspace.id, party.id])"
                        class="font-medium break-words underline"
                        >{{ party.display_name }}</Link
                    >
                    <p class="text-sm text-muted-foreground">
                        {{
                            party.kind === 'person' ? 'Persona' : 'Organización'
                        }}
                        · Provisional · Versión {{ party.version }}
                    </p>
                </li>
            </ul>
            <p v-else class="rounded-lg border border-dashed p-6 text-sm">
                No hay fichas que coincidan con esta búsqueda.
            </p>
            <WorkspacePagination v-bind="parties" />
        </section>
    </AppLayout>
</template>
