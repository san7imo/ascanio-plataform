import {
    distribution,
    guide,
    media,
    publishing,
    social,
    visualIdentity,
    web,
} from '@/routes/public';

// Only delivered pages resolve locally. Products/contact/legal remain on the
// current site until PUB1.4; production domain cutover is still a PUB2 gate.
export const currentSite = 'https://ascaniopub.com';

const localPages: Record<string, string> = {
    '/distribucion-digital/': distribution().url,
    '/publishing/': publishing().url,
    '/identidad-visual/': visualIdentity().url,
    '/desarrollo-web/': web().url,
    '/medios-de-comunicacion/': media().url,
    '/social-media/': social().url,
    '/tus-primeros-pasos/': guide().url,
};

export function publicHref(path: string): string {
    return localPages[path] ?? currentSite + path;
}

export const publicNavigation = [
    { label: 'Distribución musical', path: '/distribucion-digital/' },
    { label: 'Identidad visual', path: '/identidad-visual/' },
    { label: 'Social media', path: '/social-media/' },
    { label: 'Desarrollo web', path: '/desarrollo-web/' },
    { label: 'Primeros pasos', path: '/tus-primeros-pasos/' },
    { label: 'Contacto', path: '/contacto/' },
] as const;

export const publicServices = [
    {
        label: 'Primeros pasos',
        image: 'guide.webp',
        path: '/tus-primeros-pasos/',
    },
    {
        label: 'Distribución digital',
        image: 'distribution.webp',
        path: '/distribucion-digital/',
    },
    { label: 'Publishing', image: 'publishing.webp', path: '/publishing/' },
    {
        label: 'Identidad visual',
        image: 'visual-identity.webp',
        path: '/identidad-visual/',
    },
    { label: 'Desarrollo web', image: 'web.webp', path: '/desarrollo-web/' },
    {
        label: 'Medios de comunicación',
        image: 'media.webp',
        path: '/medios-de-comunicacion/',
    },
    { label: 'Social media', image: 'social.webp', path: '/social-media/' },
] as const;

export const publicLegalNavigation = [
    { label: 'Privacidad', path: '/privacy-policy/' },
    { label: 'Aviso legal', path: '/aviso-legal/' },
    { label: 'Cookies', path: '/politica-de-cookies/' },
] as const;
