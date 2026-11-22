<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DocumentUploadForm from '@/components/DocumentUploadForm.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { download, index, retry } from '@/routes/documents';
import { index as sharing } from '@/routes/documents/sharing';
import type { DocumentDetail, DocumentVersion } from '@/types/document';
import type { Paginated, Workspace } from '@/types/workspace';

defineProps<{
    workspace: Workspace;
    document: DocumentDetail;
    versions: Paginated<DocumentVersion>;
    canUpload: boolean;
    canShare: boolean;
    uploadsEnabled: boolean;
    errors: { scan?: string };
}>();
const states = {
    quarantined: 'En cuarentena',
    clean: 'Análisis completado',
    infected: 'Archivo rechazado',
    scan_failed: 'Análisis fallido; se puede reintentar',
    blocked: 'Versión bloqueada; requiere revisión',
};
</script>

<template>
    <Head :title="document.title" />
    <AppLayout
        :breadcrumbs="[{ title: 'Documentos', href: index(workspace.id) }]"
    >
        <section class="grid max-w-4xl min-w-0 gap-6 p-4 sm:p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold break-words">
                {{ document.title }}
            </h1>
            <p
                v-if="document.description"
                class="text-sm break-words whitespace-pre-wrap"
            >
                {{ document.description }}
            </p>
            <p class="text-sm text-muted-foreground">
                Las versiones anteriores conservan su archivo original. Cada
                descarga vuelve a comprobar tus permisos y la integridad de esa
                versión.
            </p>
            <Link
                v-if="canShare"
                :href="sharing([workspace.id, document.id])"
                class="w-fit text-sm underline"
                >Compartir una versión</Link
            >
            <DocumentUploadForm
                v-if="canUpload && uploadsEnabled"
                :key="`${workspace.id}:${document.id}`"
                :workspace-id="workspace.id"
                :document="document"
            />
            <p
                v-else-if="!uploadsEnabled"
                class="rounded-lg border p-4 text-sm"
            >
                Las nuevas cargas todavía no están habilitadas.
            </p>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Versiones</h2>
                <Button
                    variant="outline"
                    @click="router.reload({ only: ['versions'] })"
                    >Actualizar estado</Button
                >
            </div>
            <InputError :message="errors.scan" />
            <ol class="grid gap-4">
                <li
                    v-for="version in versions.data"
                    :key="version.id"
                    class="grid min-w-0 gap-3 rounded-lg border p-4"
                >
                    <h3 class="font-medium break-words">
                        Versión {{ version.number }} · {{ version.filename }}
                    </h3>
                    <p class="text-sm">{{ states[version.scan_status] }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ version.mime_type }} ·
                        {{ version.size_bytes.toLocaleString('es') }} bytes ·
                        {{ version.created_at }} · {{ workspace.timezone }}
                    </p>
                    <details class="min-w-0 text-sm">
                        <summary class="cursor-pointer">
                            Checksum SHA-256
                        </summary>
                        <p class="mt-2 font-mono break-all">
                            {{ version.sha256 }}
                        </p>
                    </details>
                    <a
                        v-if="version.can_download"
                        :href="
                            download([workspace.id, document.id, version.id])
                                .url
                        "
                        class="w-fit text-sm underline"
                        >Descargar versión {{ version.number }}</a
                    >
                    <p v-else class="text-sm text-muted-foreground">
                        Esta versión no está disponible para descarga con tu
                        acceso actual.
                    </p>
                    <Button
                        v-if="version.can_retry"
                        class="w-fit"
                        variant="secondary"
                        @click="
                            router.post(
                                retry([workspace.id, document.id, version.id])
                                    .url,
                            )
                        "
                        >Reintentar análisis de versión
                        {{ version.number }}</Button
                    >
                </li>
            </ol>
            <WorkspacePagination v-bind="versions" /><Link
                :href="index(workspace.id)"
                class="text-sm underline"
                >Volver a documentos</Link
            >
        </section>
    </AppLayout>
</template>
