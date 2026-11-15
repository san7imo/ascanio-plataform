<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, update } from '@/routes/workspaces';
import type { Workspace } from '@/types/workspace';

const props = defineProps<{ workspace: Workspace }>();
const form = useForm({
    name: props.workspace.name,
    locale: props.workspace.locale,
    timezone: props.workspace.timezone,
});
watch(
    () => props.workspace,
    (workspace) => {
        form.name = workspace.name;
        form.locale = workspace.locale;
        form.timezone = workspace.timezone;
        form.clearErrors();
    },
);
const breadcrumbs = [{ title: 'Mis espacios', href: index() }];
</script>

<template>
    <Head :title="workspace.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex max-w-xl flex-col gap-6 p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">{{ workspace.name }}</h1>
            <p class="text-muted-foreground">
                Estás trabajando en este espacio. Su propiedad se refiere al
                gobierno del workspace y no acredita derechos musicales.
            </p>
            <form
                v-if="workspace.can_update"
                class="grid gap-5"
                @submit.prevent="form.patch(update(workspace.id).url)"
            >
                <div class="grid gap-2">
                    <Label for="name">Nombre del espacio</Label
                    ><Input
                        id="name"
                        v-model="form.name"
                        required
                        maxlength="120"
                    /><InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="locale">Idioma</Label
                    ><select
                        id="locale"
                        v-model="form.locale"
                        class="rounded-md border bg-background p-2"
                    >
                        <option value="es">Español</option>
                        <option value="en">Inglés</option>
                        <option value="pt">Portugués</option></select
                    ><InputError :message="form.errors.locale" />
                </div>
                <div class="grid gap-2">
                    <Label for="timezone">Zona horaria</Label
                    ><Input
                        id="timezone"
                        v-model="form.timezone"
                        required
                    /><InputError :message="form.errors.timezone" />
                </div>
                <div class="flex items-center gap-3">
                    <Button :disabled="form.processing">Guardar ajustes</Button
                    ><span
                        v-if="form.recentlySuccessful"
                        role="status"
                        class="text-sm"
                        >Ajustes guardados.</span
                    >
                </div>
            </form>
            <dl v-else class="grid gap-2 text-sm">
                <dt>Idioma</dt>
                <dd>{{ workspace.locale }}</dd>
                <dt>Zona horaria</dt>
                <dd>{{ workspace.timezone }}</dd>
            </dl>
        </section>
    </AppLayout>
</template>
