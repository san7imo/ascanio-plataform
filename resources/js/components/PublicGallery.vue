<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { PublicGalleryContent } from '@/types/public-site';

defineProps<{ gallery: PublicGalleryContent }>();

const track = ref<HTMLDivElement | null>(null);
const atStart = ref(true);
const atEnd = ref(true);
let observer: ResizeObserver | undefined;

function updatePosition() {
    if (!track.value) {
        return;
    }

    atStart.value = track.value.scrollLeft <= 2;
    atEnd.value =
        track.value.scrollLeft + track.value.clientWidth >=
        track.value.scrollWidth - 2;
}

function move(direction: number) {
    const viewport = track.value;

    if (!viewport) {
        return;
    }

    viewport.scrollBy({
        left: direction * viewport.clientWidth,
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto'
            : 'smooth',
    });
}

onMounted(() => {
    observer = new ResizeObserver(updatePosition);

    if (track.value) {
        observer.observe(track.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div :class="['public-gallery', 'public-gallery-' + gallery.kind]">
        <div
            ref="track"
            class="public-gallery-track"
            tabindex="0"
            role="region"
            :aria-label="'Galería: ' + gallery.title"
            @scroll.passive="updatePosition"
        >
            <img
                v-for="image in gallery.images"
                :key="image.src"
                :src="image.src"
                :alt="image.alt"
                :width="image.width"
                :height="image.height"
                loading="lazy"
                decoding="async"
            />
        </div>
        <div
            v-if="!atStart || !atEnd"
            class="public-gallery-controls"
            :aria-label="'Controles: ' + gallery.title"
            role="group"
        >
            <button
                type="button"
                :disabled="atStart"
                :aria-label="'Anterior: ' + gallery.title"
                @click="move(-1)"
            >
                Anterior
            </button>
            <button
                type="button"
                :disabled="atEnd"
                :aria-label="'Siguiente: ' + gallery.title"
                @click="move(1)"
            >
                Siguiente
            </button>
        </div>
    </div>
</template>
