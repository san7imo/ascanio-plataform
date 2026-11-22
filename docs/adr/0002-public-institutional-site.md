# ADR 0002 — Sitio público institucional y portal de participantes

Fecha: 22 de noviembre de 2026 UTC.
Estado: adoptado para planificación por instrucción directa del titular; réplica pendiente de implementación.

## Contexto

El titular pidió incorporar al proyecto una réplica del sitio público actual [ascaniopub.com](https://ascaniopub.com/), visitarlo, tomar imágenes de referencia y actualizar el plan. La decisión anterior aplazaba las webs públicas y no contemplaba esta fachada institucional. G1, preparado en [PR #12](https://github.com/san7imo/ascanio-plataform/pull/12), resuelve acceso privado de destinatarios a versiones exactas; ese recorrido tiene otra finalidad.

El [inventario y las capturas](../references/ascaniopub/README.md) conservan la evidencia del sitio real. La web presenta servicios, contenido informativo, contacto, páginas comerciales y enlaces a otros sistemas; anunciar distribución, hosting o asesoría no demuestra que esas operaciones estén implementadas en el SaaS.

## Decisión y sustitución explícita

Este ADR sustituye exclusivamente:

1. La exclusión/aplazamiento del sitio público **institucional de Ascanio** en README §2 y §12. EPK, webs públicas de tenants/artistas, dominios por cliente y un constructor de sitios siguen fuera del MVP.
2. La secuencia de la siguiente interfaz pública en el plan §12: se incorpora PUB0–PUB3 antes de abrir el catálogo C1 como próximo frente de interfaz. Revisar/integrar G1 sigue siendo una entrega independiente; una puerta de correo/hosting pendiente no impide trabajar el contenido y layout local.

Se mantienen ADR 0001, Laravel/Vue/Inertia/Fortify, PostgreSQL/Redis, el monolito modular, Kroma como hosting, las fronteras de tenancy y la exigencia de autorización para datos privados. WordPress y sus plugins sirven de referencia visual/funcional. La compra actual conserva su sistema existente; no se introduce un CMS nuevo ni se arrastra su runtime o credenciales al core Laravel. La convivencia de rutas/dominios dentro de Kroma se verifica antes de sustituir el sitio en producción.

Hay dos superficies con vocabulario explícito:

| Superficie | Visitante y finalidad | Acceso |
|---|---|---|
| Sitio público institucional | Presentar Ascanio, sus servicios y medios de contacto; reproducir la web de referencia | Anónimo; únicamente contenido editorial deliberadamente público |
| Portal de participantes | Consultar documentos/participaciones concretos y realizar acciones autorizadas | Cuenta verificada y membership/grant vigente; conservan las policies y proyecciones de G1 y módulos posteriores |

La identidad visual de Ascanio es la referencia de la fachada pública. Los accesos a la aplicación deben quedar identificados y usar Fortify; la réplica no hace anónimos los recursos compartidos ni convierte una relación profesional en autorización. El branding institucional se mantiene en layout/configuración pública, sin hardcodear un cliente en modelos, seeds o reglas de negocio multi-tenant.

## Alcance y límites

La réplica conserva marca, composición, páginas, imágenes, jerarquía, contenidos y comportamiento público observados, con versiones de escritorio/móvil y estados interactivos de referencia. No es un rediseño libre ni una landing genérica. Los defectos de carga, enlaces vacíos, contenido de plantilla y rutas heredadas se registran y se resuelven explícitamente; no se inventa que toda URL del sitemap forma parte del contenido vigente de marca.

El sitio incluye productos y flujos comerciales. El titular eligió expresamente **replicar las páginas de productos y conservar la compra actual**. La réplica presenta catálogo/fichas y conduce al sistema comercial existente; no implementa carrito, checkout, cobros, pedidos o cuentas comerciales nuevos, no migra cuentas/pedidos de WordPress ni contrata proveedores. Los destinos se inventarían y el corte de dominio requiere un mapa operativo que conserve producto, carrito, checkout, cuenta comercial, recursos y callbacks que el sistema existente necesite. No se da por resuelto el enrutamiento de compra solo por conservar un href que dejaría de funcionar al reemplazar WordPress.

Contacto, buscador de dominios, consentimiento y accesos se especifican como comportamientos, con endpoint, tratamiento de datos y estado de error reales. No se considera funcional un formulario que solo muestra éxito, un resultado de dominio inventado o un envío de correo no confirmado. La inspección no envía formularios, crea cuentas ni ejecuta compras. Ningún tracker, clave o política de WordPress se hereda automáticamente.

## Consecuencias y cierre

- PUB0 entrega inventario visual, rutas, dependencias, incidencias y capturas fechadas; no acredita la réplica.
- PUB1 implementará layout, navegación, portada y páginas públicas con recursos versionados y permisos de uso revisados dentro del alcance del titular.
- PUB2 conectará los recorridos y proveedores que se hayan definido, respetando privacidad, validación, rate limits y el límite entre contenido público y datos privados.
- PUB3 comprobará paridad visual/funcional, accesibilidad, SEO, enlaces y la futura transición desde el sitio existente. Publicar/cambiar DNS o document roots requiere una tarea de producción específica y entorno autorizado.
- La matriz de cierre incluirá visitantes anónimos, usuarios autenticados y participantes limitados; ningún listado, conteo, archivo o dato de tenant pasa a ser público por compartir componentes de marca.
- Las pruebas de aplicación se ejecutarán al implementar; esta entrega documental solo afirma las inspecciones y comprobaciones realmente realizadas.
