<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, store } from '@/routes/workspaces';

const props = defineProps<{ creationToken: string }>();
const form = useForm({
    name: '',
    locale: 'es',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    creation_token: props.creationToken,
});
const breadcrumbs = [{ title: 'Mis espacios', href: index() }];
</script>

<template>
    <Head title="Crear espacio" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex max-w-xl flex-col gap-6 p-6">
            <h1 class="text-2xl font-semibold">Crea tu espacio de trabajo</h1>
            <p class="text-muted-foreground">
                Organiza tu actividad musical con un espacio privado. Tu cuenta
                será su primer propietario y operador declarado.
            </p>
            <form class="grid gap-5" @submit.prevent="form.post(store().url)">
                <div class="grid gap-2">
                    <Label for="name">Nombre del espacio</Label
                    ><Input
                        id="name"
                        v-model="form.name"
                        required
                        maxlength="120"
                        autocomplete="organization"
                    /><InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="locale">Idioma del espacio</Label
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
                        placeholder="America/Bogota"
                    />
                    <p class="text-sm text-muted-foreground">
                        Usa una zona como America/Bogota o Europe/Madrid. Las
                        fechas se guardan en UTC.
                    </p>
                    <InputError :message="form.errors.timezone" />
                </div>
                <InputError :message="form.errors.creation_token" />
                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">Crear espacio</Button
                    ><Link :href="index()" class="text-sm underline"
                        >Volver</Link
                    >
                </div>
            </form>
        </section>
    </AppLayout>
</template>
