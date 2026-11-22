<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { currentSite, publicHref } from '@/lib/public-site';
import { home } from '@/routes';
import type { PublicProductContent } from '@/types/public-site';

const props = defineProps<{
    products: PublicProductContent[];
    canRegister: boolean;
}>();
const order = ref('reference');
const products = computed(() => {
    const copy = [...props.products];

    if (order.value === 'reference') {
        return copy;
    }

    return copy.sort(
        (a, b) =>
            a.title.localeCompare(b.title, 'es') *
            (order.value === 'name-desc' ? -1 : 1),
    );
});
</script>

<template>
    <Head title="Shop"
        ><meta
            name="description"
            content="Distribución VEVO, sincronización y Smart URL. Consulta las fichas y continúa la compra en la tienda actual."
    /></Head>
    <PublicLayout :can-register="canRegister">
        <section class="public-shop" aria-labelledby="shop-title">
            <header class="public-shop-heading">
                <h1 id="shop-title">Shop</h1>
                <nav aria-label="Ruta de navegación">
                    <a :href="home().url">Home</a> / Shop
                </nav>
            </header>
            <div class="public-shop-content">
                <div class="public-shop-toolbar">
                    <p>Mostrando los {{ products.length }} resultados</p>
                    <label class="public-visually-hidden" for="product-order"
                        >Orden de productos</label
                    >
                    <select id="product-order" v-model="order">
                        <option value="reference">Orden predeterminado</option>
                        <option value="name-asc">Nombre: A a Z</option>
                        <option value="name-desc">Nombre: Z a A</option>
                    </select>
                </div>
                <div class="public-shop-products">
                    <article
                        v-for="product in products"
                        :key="product.key"
                        class="public-shop-card"
                    >
                        <a :href="publicHref(product.path)"
                            ><img
                                :src="product.image.src"
                                :alt="product.image.alt"
                                :width="product.image.width"
                                :height="product.image.height"
                                loading="lazy"
                            />
                            <h2>{{ product.title }}</h2></a
                        >
                        <p class="public-shop-category">
                            {{ product.category }}
                        </p>
                        <p v-if="product.price" class="public-shop-price">
                            {{ product.price }}
                        </p>
                        <a
                            class="public-shop-product-link"
                            :href="publicHref(product.path)"
                            >Ver producto</a
                        >
                    </article>
                </div>
                <nav
                    class="public-commerce-links"
                    aria-label="Servicios de la tienda actual"
                >
                    <a :href="currentSite + '/shop/'"
                        >Comprar en la tienda actual</a
                    >
                    <a :href="currentSite + '/cart/'">Carrito actual</a>
                    <a :href="currentSite + '/my-account/'">Cuenta comercial</a>
                    <a :href="currentSite + '/request-quote/'"
                        >Solicitar presupuesto</a
                    >
                </nav>
            </div>
        </section>
    </PublicLayout>
</template>
