<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}>();
</script>

<template>
    <nav class="flex items-center gap-4 text-sm" aria-label="Paginación">
        <Link v-if="prev_page_url" :href="prev_page_url" class="underline"
            >Anterior</Link
        >
        <span>Página {{ current_page }} de {{ last_page }}</span>
        <Link v-if="next_page_url" :href="next_page_url" class="underline"
            >Siguiente</Link
        >
    </nav>
</template>
