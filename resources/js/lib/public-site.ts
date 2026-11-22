// Fixed editorial destinations from the approved reference. Local service pages
// replace these links as PUB1.3–PUB1.4 are delivered. Never derive a redirect
// target from request input. Domain cutover remains a PUB2 operational gate.
export const currentSite = 'https://ascaniopub.com';

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
