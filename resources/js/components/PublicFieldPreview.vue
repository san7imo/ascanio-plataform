<script setup lang="ts">
import type { PublicPreviewField } from '@/types/public-site';

defineProps<{
    fields: PublicPreviewField[];
    scope: string;
    descriptionId: string;
}>();
</script>

<template>
    <fieldset
        class="public-field-preview"
        disabled
        :aria-describedby="descriptionId"
    >
        <legend class="public-visually-hidden">
            Campos que se completan en el sitio actual
        </legend>
        <div
            v-for="field in fields"
            :key="field.key"
            class="public-preview-field"
        >
            <label :for="scope + '-' + field.key">{{ field.label }}</label>
            <select
                v-if="field.type === 'select'"
                :id="scope + '-' + field.key"
            >
                <option>{{ field.placeholder }}</option>
                <option v-for="option in field.options" :key="option">
                    {{ option }}
                </option>
            </select>
            <textarea
                v-else-if="field.type === 'textarea'"
                :id="scope + '-' + field.key"
                :placeholder="field.placeholder"
                rows="4"
            />
            <input
                v-else
                :id="scope + '-' + field.key"
                :type="field.type"
                :placeholder="field.placeholder"
            />
            <p v-if="field.options.length" class="public-field-help">
                Opciones en tienda: {{ field.options.join(' · ') }}
            </p>
            <p v-if="field.description" class="public-field-help">
                {{ field.description }}
            </p>
        </div>
    </fieldset>
</template>
