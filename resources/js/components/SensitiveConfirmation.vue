<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';

defineProps<{
    id: string;
    mfaReady: boolean;
    errors: { password?: string; code?: string };
}>();
const password = defineModel<string>('password', { required: true });
const code = defineModel<string>('code', { required: true });
</script>

<template>
    <div class="grid gap-3 rounded-lg border p-4">
        <p v-if="!mfaReady" class="text-sm">
            Activa y confirma MFA en
            <Link :href="edit()" class="underline">Seguridad</Link> para
            realizar cambios de gobierno.
        </p>
        <p v-else class="text-sm">
            Confirma cada cambio con tu contraseña y un código nuevo de tu
            aplicación MFA.
        </p>
        <Label :for="id + '-password'">Tu contraseña actual</Label>
        <PasswordInput
            :id="id + '-password'"
            v-model="password"
            autocomplete="current-password"
            required
        />
        <InputError :message="errors.password" />
        <Label :for="id + '-code'">Código MFA de seis dígitos</Label>
        <Input
            :id="id + '-code'"
            v-model="code"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            pattern="[0-9]{6}"
            required
        />
        <InputError :message="errors.code" />
    </div>
</template>
