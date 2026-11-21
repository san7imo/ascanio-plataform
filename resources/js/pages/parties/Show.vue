<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PartyPrivateDetails from '@/components/PartyPrivateDetails.vue';
import { Button } from '@/components/ui/button';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, index } from '@/routes/parties';
import type { PartyDetail, PartyRevision } from '@/types/party';
import type { Paginated, Workspace } from '@/types/workspace';

defineProps<{
    workspace: Workspace;
    party: PartyDetail;
    revisions: Paginated<PartyRevision> | null;
}>();
</script>

<template>
    <Head :title="party.display_name" />
    <AppLayout
        :breadcrumbs="[{ title: 'Contrapartes', href: index(workspace.id) }]"
    >
        <section class="grid max-w-3xl gap-6 p-4 sm:p-6">
            <WorkspaceNav :workspace="workspace" />
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h1 class="min-w-0 text-2xl font-semibold break-words">
                    {{ party.display_name }}
                </h1>
                <Button v-if="party.can_update" as-child
                    ><Link :href="edit([workspace.id, party.id])"
                        >Editar ficha</Link
                    ></Button
                >
            </div>
            <p class="text-sm">
                {{ party.kind === 'person' ? 'Persona' : 'Organización' }} ·
                Provisional · Versión {{ party.version }}
            </p>
            <p class="text-sm text-muted-foreground">
                Captura manual en {{ workspace.name }}. No acredita identidad
                legal, representación ni derechos musicales. Actualizada:
                {{ party.updated_at }} · {{ workspace.timezone }}.
            </p>
            <PartyPrivateDetails
                v-if="party.private"
                :details="party.private"
            />
            <p v-else class="rounded-lg border p-4 text-sm">
                Tu acceso permite consultar la ficha básica. Los datos de
                contacto, identificadores, actividades, notas e historial
                requieren autorización adicional.
            </p>
            <section
                v-if="revisions"
                class="grid gap-4 border-t pt-6"
                aria-label="Historial de la ficha"
            >
                <h2 class="text-xl font-semibold">Historial de revisiones</h2>
                <p class="text-sm text-muted-foreground">
                    Cada revisión conserva los datos de ese momento. Editar la
                    ficha actual no modifica las revisiones anteriores.
                </p>
                <details
                    v-for="revision in revisions.data"
                    :key="revision.id"
                    class="rounded-lg border p-4"
                >
                    <summary class="cursor-pointer text-sm font-medium">
                        Versión {{ revision.version }} ·
                        {{ revision.recorded_at }} · {{ workspace.timezone }}
                    </summary>
                    <div class="mt-5 grid gap-5">
                        <p class="font-medium break-words">
                            {{ revision.snapshot.display_name }} ·
                            {{
                                revision.snapshot.kind === 'person'
                                    ? 'Persona'
                                    : 'Organización'
                            }}
                        </p>
                        <PartyPrivateDetails :details="revision.snapshot" />
                    </div>
                </details>
                <WorkspacePagination v-bind="revisions" />
            </section>
            <Link :href="index(workspace.id)" class="text-sm underline"
                >Volver a contrapartes</Link
            >
        </section>
    </AppLayout>
</template>
