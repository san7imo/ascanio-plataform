<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { show } from '@/routes/shared-documents';
import type { SharedDocument } from '@/types/shared-document';
import type { Paginated } from '@/types/workspace';

defineProps<{ documents: Paginated<SharedDocument> }>();
</script>

<template>
    <Head title="Documentos compartidos" />
    <AppLayout>
        <section class="grid max-w-3xl min-w-0 gap-5 p-4 sm:p-6">
            <h1 class="text-2xl font-semibold">
                Documentos compartidos contigo
            </h1>
            <p class="text-sm">
                Cada acceso corresponde a una versión y tiene una vigencia
                propia.
            </p>
            <p v-if="!documents.total">
                No tienes documentos compartidos disponibles.
            </p>
            <ul class="grid gap-3">
                <li
                    v-for="document in documents.data"
                    :key="document.id"
                    class="grid min-w-0 gap-2 rounded-lg border p-4"
                >
                    <Link
                        :href="show(document.id)"
                        class="break-words underline"
                        >{{ document.filename }} · Versión
                        {{ document.number }}</Link
                    >
                    <p class="text-sm">
                        {{ document.workspace }} · Vence
                        {{ document.expires_at }} · {{ document.timezone }}
                    </p>
                </li>
            </ul>
            <WorkspacePagination v-bind="documents" />
        </section>
    </AppLayout>
</template>
