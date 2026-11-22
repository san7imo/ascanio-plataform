<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/documents';
import { store as upload } from '@/routes/documents/versions';
import type { DocumentDetail } from '@/types/document';

const props = defineProps<{ workspaceId: string; document?: DocumentDetail }>();
const reload = () =>
    router.visit(window.location.href, { preserveState: false });
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm({
    title: '',
    description: '',
    file: null as File | null,
    upload_key: crypto.randomUUID(),
    expected_version: props.document?.version_count ?? null,
});
function selectFile(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.clearErrors('file');
}
function submit() {
    const url = props.document
        ? upload([props.workspaceId, props.document.id]).url
        : store(props.workspaceId).url;
    form.post(url, {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            form.upload_key = crypto.randomUUID();
            form.expected_version = props.document?.version_count ?? null;

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <form class="grid gap-4 rounded-lg border p-4" @submit.prevent="submit">
        <h2 class="text-lg font-semibold">
            {{ document ? 'Añadir versión' : 'Crear documento con archivo' }}
        </h2>
        <p class="text-sm text-muted-foreground">
            PDF o texto. La versión queda en cuarentena hasta completar su
            análisis. Subir otra versión conserva los archivos anteriores.
        </p>
        <div
            v-if="form.errors.expected_version"
            class="grid gap-2"
            role="alert"
        >
            <InputError :message="form.errors.expected_version" />
            <p class="text-sm">
                Revisa las versiones actuales antes de volver a elegir el
                archivo.
            </p>
            <Button
                type="button"
                variant="secondary"
                class="w-fit"
                @click="reload"
                >Recargar documento</Button
            >
        </div>
        <div v-if="!document" class="grid gap-2">
            <Label for="document-title">Título</Label
            ><Input
                id="document-title"
                v-model="form.title"
                required
                maxlength="160"
            /><InputError :message="form.errors.title" /><Label
                for="document-description"
                >Descripción (opcional)</Label
            ><textarea
                id="document-description"
                v-model="form.description"
                maxlength="1000"
                rows="3"
                class="rounded-md border bg-background p-3"
            /><InputError :message="form.errors.description" />
        </div>
        <div class="grid gap-2">
            <Label for="document-file">Archivo de esta versión</Label>
            <input
                id="document-file"
                ref="fileInput"
                type="file"
                accept=".pdf,.txt"
                class="sr-only"
                aria-describedby="selected-document-file"
                @change="selectFile"
            />
            <div
                class="flex min-w-0 flex-wrap items-center gap-3 rounded-md border p-3"
            >
                <Button
                    type="button"
                    variant="secondary"
                    aria-controls="document-file"
                    @click="fileInput?.click()"
                    >Elegir archivo</Button
                >
                <span
                    id="selected-document-file"
                    class="min-w-0 text-sm break-all"
                    aria-live="polite"
                    >{{
                        form.file?.name ?? 'Ningún archivo seleccionado'
                    }}</span
                >
            </div>
            <InputError :message="form.errors.file" />
        </div>
        <InputError :message="form.errors.upload_key" />
        <progress
            v-if="form.progress"
            :value="form.progress.percentage"
            max="100"
            class="w-full"
        >
            {{ form.progress.percentage }}%
        </progress>
        <Button :disabled="form.processing" class="w-fit">{{
            document ? 'Subir nueva versión' : 'Crear y subir archivo'
        }}</Button>
    </form>
</template>
