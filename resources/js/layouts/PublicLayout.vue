<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    currentSite,
    publicLegalNavigation,
    publicNavigation,
    publicHref,
} from '@/lib/public-site';
import { home, login, register } from '@/routes';
import '../../css/public-site.css';

defineProps<{ canRegister: boolean }>();

const menuOpen = ref(false);
const menuButton = ref<HTMLButtonElement | null>(null);
const desktopQuery = '(min-width: 1100px)';
let viewport: MediaQueryList | undefined;

function closeMenu(restoreFocus = false) {
    menuOpen.value = false;

    if (restoreFocus) {
        menuButton.value?.focus();
    }
}

function handleViewportChange() {
    closeMenu();
}

onMounted(() => {
    viewport = window.matchMedia(desktopQuery);
    viewport.addEventListener('change', handleViewportChange);
});

onBeforeUnmount(() => {
    viewport?.removeEventListener('change', handleViewportChange);
});
</script>

<template>
    <div class="public-site" @keydown.esc="closeMenu(true)">
        <a class="public-skip-link" href="#public-content"
            >Saltar al contenido</a
        >
        <header class="public-header">
            <Link
                :href="home()"
                class="public-logo"
                aria-label="Ascanio Publishing, inicio"
            >
                <img
                    src="/assets/ascanio/logo.png"
                    width="214"
                    height="85"
                    alt="Ascanio Publishing"
                    fetchpriority="high"
                />
            </Link>
            <button
                ref="menuButton"
                class="public-menu-toggle"
                type="button"
                aria-controls="public-navigation"
                :aria-expanded="menuOpen"
                :aria-label="menuOpen ? 'Cerrar menú' : 'Abrir menú'"
                @click="menuOpen = !menuOpen"
            >
                <X v-if="menuOpen" :size="26" aria-hidden="true" />
                <Menu v-else :size="26" aria-hidden="true" />
            </button>
            <nav
                id="public-navigation"
                aria-label="Navegación principal"
                :class="['public-navigation', { 'is-open': menuOpen }]"
            >
                <a
                    v-for="item in publicNavigation"
                    :key="item.path"
                    :href="publicHref(item.path)"
                    @click="closeMenu()"
                    >{{ item.label }}</a
                >
            </nav>
        </header>
        <main id="public-content" tabindex="-1">
            <slot />
        </main>
        <footer class="public-footer">
            <span>Ascanio Publishing</span>
            <nav aria-label="Enlaces legales y tienda">
                <a
                    v-for="item in publicLegalNavigation"
                    :key="item.path"
                    :href="currentSite + item.path"
                    >{{ item.label }}</a
                >
                <a :href="currentSite + '/shop/'">Tienda actual</a>
            </nav>
            <nav aria-label="Plataforma de trabajo">
                <a :href="login().url">Acceso a la plataforma</a>
                <a v-if="canRegister" :href="register().url">Crear cuenta</a>
            </nav>
        </footer>
    </div>
</template>
