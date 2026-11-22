<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DocumentUploadForm from '@/components/DocumentUploadForm.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, show } from '@/routes/documents';
import type { DocumentSummary } from '@/types/document';
import type { Paginated, Workspace } from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    documents: Paginated<DocumentSummary>;
    search: string;
    uploadsEnabled: boolean;
    canUpload: boolean;
}>();
const search = ref(props.search);
watch(
    () => props.workspace.id,
    () => (search.value = props.search),
);
</script>

<template>
    <Head :title="`Documentos · ${workspace.name}`" />
    <AppLayout
        :breadcrumbs="[{ title: 'Documentos', href: index(workspace.id) }]"
    >
        <section class="grid max-w-4xl min-w-0 gap-6 p-4 sm:p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">
                Documentos de {{ workspace.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Cada archivo tiene su propia versión e historial. El acceso a
                sus datos no concede permiso para descargarlo.
            </p>
            <p
                v-if="!uploadsEnabled"
                class="rounded-lg border p-4 text-sm"
                role="status"
            >
                Las cargas todavía no están habilitadas. Estamos preparando el
                almacenamiento privado y la revisión de archivos.
            </p>
            <DocumentUploadForm
                v-else-if="canUpload"
                :key="workspace.id"
                :workspace-id="workspace.id"
            />
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="
                    router.get(
                        index(workspace.id).url,
                        { search },
                        { preserveState: true, replace: true },
                    )
                "
            >
                <div class="grid flex-1 gap-2">
                    <Label for="document-search">Buscar por título</Label
                    ><Input
                        id="document-search"
                        v-model="search"
                        maxlength="120"
                    />
                </div>
                <Button variant="secondary">Buscar</Button>
            </form>
            <p class="text-sm">
                {{ documents.total }} documentos en este espacio
            </p>
            <ul v-if="documents.data.length" class="grid gap-3">
                <li
                    v-for="document in documents.data"
                    :key="document.id"
                    class="grid min-w-0 gap-2 rounded-lg border p-4"
                >
                    <Link
                        :href="show([workspace.id, document.id])"
                        class="font-medium break-words underline"
                        >{{ document.title }}</Link
                    >
                    <p class="text-sm text-muted-foreground">
                        {{ document.version_count }} versiones
                    </p>
                </li>
            </ul>
            <p v-else class="rounded-lg border border-dashed p-6 text-sm">
                No hay documentos que coincidan con esta búsqueda.
            </p>
            <WorkspacePagination v-bind="documents" />
        </section>
    </AppLayout>
</template>
