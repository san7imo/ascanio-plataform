<script setup lang="ts">
import type { PublicImage } from '@/types/public-site';

defineProps<{
    title: string;
    subtitle: string;
    image: PublicImage;
    service?: boolean;
}>();
</script>

<template>
    <section
        :class="['public-hero', { 'public-service-hero': service }]"
        aria-labelledby="hero-title"
    >
        <img
            class="public-hero-art"
            :src="image.src"
            alt=""
            :width="image.width"
            :height="image.height"
            fetchpriority="high"
        />
        <div class="public-hero-content">
            <h1 id="hero-title">{{ title }}</h1>
            <p>{{ subtitle }}</p>
            <ul class="public-platforms" aria-label="Plataformas musicales">
                <li>
                    <img
                        src="/assets/ascanio/spotify.png"
                        alt="Spotify"
                        width="120"
                        height="36"
                    />
                </li>
                <li>
                    <img
                        src="/assets/ascanio/apple-music.png"
                        alt="Apple Music"
                        width="120"
                        height="30"
                    />
                </li>
                <li>
                    <img
                        src="/assets/ascanio/youtube.png"
                        alt="YouTube"
                        width="126"
                        height="29"
                    />
                </li>
            </ul>
        </div>
    </section>
</template>
