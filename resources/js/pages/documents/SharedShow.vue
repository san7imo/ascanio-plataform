<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { download, index } from '@/routes/shared-documents';
import type { SharedDocument } from '@/types/shared-document';

defineProps<{ document: SharedDocument }>();
const states: Record<string, string> = {
    clean: 'Análisis completado',
    quarantined: 'En cuarentena',
    infected: 'Archivo rechazado',
    scan_failed: 'Análisis fallido',
    blocked: 'Archivo bloqueado',
};
</script>

<template>
    <Head title="Documento compartido" />
    <AppLayout>
        <section class="grid max-w-3xl min-w-0 gap-4 p-4 sm:p-6">
            <h1 class="text-2xl font-semibold break-words">
                {{ document.filename }} · Versión {{ document.number }}
            </h1>
            <p>Compartido desde {{ document.workspace }}.</p>
            <p class="text-sm">
                {{ document.mime_type }} ·
                {{ document.size_bytes.toLocaleString('es') }} bytes ·
                {{ states[document.scan_status] }}
            </p>
            <p class="text-sm">
                Vence {{ document.expires_at }} · {{ document.timezone }}.
            </p>
            <details class="min-w-0 text-sm">
                <summary>Checksum SHA-256</summary>
                <p class="mt-2 font-mono break-all">{{ document.sha256 }}</p>
            </details>
            <a
                v-if="document.can_download"
                :href="download(document.id).url"
                class="w-fit underline"
                >Descargar esta versión</a
            >
            <p v-else class="text-sm">
                Tu acceso actual o el estado del archivo no permiten descargar
                esta versión.
            </p>
            <Link :href="index()" class="w-fit text-sm underline"
                >Volver a documentos compartidos</Link
            >
        </section>
    </AppLayout>
</template>
