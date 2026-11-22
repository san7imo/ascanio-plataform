# PUB1 — Paquete de implementación del sitio público

Estado: en implementación. PUB1.1 y PUB1.2 integrados mediante PR #14; PUB1.3 preparado para revisión local; PUB1.4–PUB1.5 pendientes. Actualización: 21 de noviembre de 2026, America/Bogota. Las capturas fechadas 22 de noviembre UTC conservan su zona original.

Este documento concreta PUB1 del [plan](../implementation-plan.md), conforme al [ADR 0002](../adr/0002-public-institutional-site.md) y a las [50 capturas de referencia](../references/ascaniopub/README.md). El titular eligió reproducir las páginas de productos y conservar la compra actual. Este documento separa el paquete completo de su primera entrega de código; no autoriza publicación.

## Base y resultado esperado

Base integrada comprobada: main f732ecb, después de PR #12 (G1, aca2ad7) y PR #13 (referencia/plan público, f732ecb). Se resolvió el conflicto documental conservando G1 y PUB0–PUB3; CI de la combinación aprobó auditorías y PHP 8.3/8.4 antes del merge. La primera implementación partió de esa base en feat/public-institutional-site y se integró mediante [PR #14](https://github.com/san7imo/ascanio-plataform/pull/14), merge 4439aef. PUB1.3 parte de ese main en feat/public-services-guide.

El resultado local es una réplica navegable del sitio institucional en Laravel/Vue/Inertia: marca, composición, contenido y recursos de referencia en escritorio/móvil. La web pública presenta servicios; la aplicación privada sigue operando con Fortify, memberships y grants. No se cambia la estrategia de tenants, base de datos o storage para acomodar el sitio.

## Mapa de páginas y recorridos

| Grupo | Rutas de referencia | Trabajo de PUB1 |
|---|---|---|
| Portada | / | Sustituir la bienvenida genérica con layout de marca, banners, introducción, tarjetas y CTA de la referencia |
| Servicios | /distribucion-digital/, /publishing/, /identidad-visual/, /desarrollo-web/, /medios-de-comunicacion/, /social-media/ | Reproducir las seis páginas, sus imágenes, bloques y enlaces; no implementar motores de distribución, hosting o marketing |
| Guía | /tus-primeros-pasos/ | Reproducir estructura y acordeones; no convertir esos textos en validadores universales del catálogo |
| Contacto | /contacto/ | Reproducir presentación y canales públicos; la conexión/envío del formulario pertenece a PUB2 |
| Oferta comercial | /shop/ y las tres /product/ de VEVO, sincronización y Smart URL | Reproducir listado, fichas y variantes/contenido visibles; conducir al sistema comercial actual según el mapa comprobado en PUB2 |
| Legales | /privacy-policy/, /aviso-legal/, /politica-de-cookies/ | Preparar páginas desde contenido publicado identificado; señalar revisión editorial pendiente antes de publicación, sin redactar obligaciones legales inventadas |
| Recorridos existentes | /cart/, /checkout/, /my-account/, /request-quote/ | Conservar destino/operación del sistema existente; no crear carrito, cobros, cuenta comercial o CRM nuevos |
| Herencia de URLs | /home-page-4/ y nueve entradas del sitemap no auditadas editorialmente | Registrar equivalencias/destinos pendientes; no migrar entradas, crear blog ni borrar contenido por inferencia |
| Aplicación | Login/registro/verificación y portal de participantes existentes | Mantener rutas y autorización; identificar la entrada a la aplicación sin equipararla a la cuenta comercial WordPress |

La réplica abarca dieciséis páginas públicas principales: portada, seis servicios, guía, contacto, listado comercial, tres productos y tres legales. Las otras cinco rutas inspeccionadas conservan recorridos del sistema existente o equivalencias de URL. El inventario completo distingue 21 rutas visitadas de las nueve entradas de blog solo descubiertas.

## Orden de construcción

Los pasos son partes del mismo alcance PUB1, no autorización para iniciar otros módulos ni obligación de crear cinco PRs.

| Paso | Entrega concreta | Comprobación de cierre |
|---|---|---|
| PUB1.1 — Marca y estructura | Inventario de logo/imágenes/fuentes; recursos locales versionados con procedencia; layout público, navegación desktop/móvil y mapa de rutas | Sin depender de hotlinks para los recursos de la réplica; sin credenciales/tracking de plugins; navegación por teclado y menú móvil operables |
| PUB1.2 — Portada | Banners, textos, tarjetas y CTA fotográfico; tamaños y orden de bloques | Comparación con las capturas a 1440 y 390 px; reserva de altura del slider para que no tape secciones; estados de movimiento/foco accesibles |
| PUB1.3 — Servicios y guía | Seis páginas de servicios y guía con acordeones | Contenido/jerarquía conforme a la referencia, enlaces resueltos, acordeones operables y ausencia de recortes horizontales |
| PUB1.4 — Productos, contacto y legales | Listado y tres fichas; página de contacto y tres legales; entradas a servicios existentes | Ningún botón declara compra, envío o búsqueda exitosa sin ejecutar un recorrido real; las acciones dependientes quedan en revisión local hasta PUB2 o conducen explícitamente al servicio existente |
| PUB1.5 — Revisión de la entrega | Matriz de páginas, comprobaciones HTTP/privacidad y revisión visual local | Páginas anónimas sin datos privados, login/invitaciones intactos; revisión de recursos/enlaces, checks de aplicación aplicables y lista de dependencias PUB2/PUB3 |

Se conserva el diseño actual como referencia; no se introduce un rediseño libre. Las correcciones deliberadas de superposición, carga, enlaces vacíos o accesibilidad se anotan junto a la comparación. No copiar WordPress/Elementor/Slider Revolution/WooCommerce al runtime Laravel ni añadir dependencias nuevas sin necesidad y compatibilidad comprobadas.

## Frontera con PUB2 y publicación

PUB1 permite revisión local del contenido y la interfaz. No activa un formulario de contacto de producción, un buscador de dominios con respuestas inventadas, un checkout propio ni analítica de terceros. Los formularios todavía no conectados no se publican como funcionales ni devuelven éxito ficticio. Para recorridos que deban permanecer operables, usar un destino existente explícito y validado; no redirigir silenciosamente Añadir al carrito a una acción distinta.

PUB2 define el destino real de contacto, transporte/correo y manejo de fallos; el servicio permitido para búsqueda de dominio o su enlace externo; configuración de consentimiento y terceros; destinos comerciales e ingreso Fortify. En particular, antes de reemplazar la raíz del dominio debe comprobar cómo siguen resolviéndose WordPress, productos, carrito, checkout, cuenta, recursos y callbacks. Un enlace al mismo dominio puede romperse tras un cambio de raíz; no acredita continuidad por sí solo.

PUB3 valida localmente el conjunto y mantiene separada la puerta operativa. Si faltan SMTP, enrutamiento o datos de Kroma, se registra la operación pendiente y se continúa con las partes independientes y C1. No se declara PUB2/PUB3 completo por omitir su dependencia ni se exige esperar un despliegue para desarrollar el catálogo.

## Aceptación y verificación al implementar

- Las dieciséis páginas previstas y la navegación reproducen la referencia acordada. Las cinco rutas de legado/operación tienen destino o decisión explícita, no una réplica de pedidos/cuentas privadas.
- Una visita anónima no obtiene datos de tenants, contactos privados, documentos, grants, estadísticas o archivos; el usuario autenticado conserva solo sus permisos. Se comprueba también el acceso directo a URLs privadas.
- La entrada y los recorridos de invitación/verificación de la aplicación funcionan; ni la web pública ni el formulario de contacto vinculan identidades por email.
- Escritorio 1440 px y móvil 390 px, menú, teclado/foco, acordeones y banners tienen revisión visual; no se trasladan como objetivo los fallos observados de la web de origen.
- No hay solicitudes de envío, compras o búsquedas externas durante pruebas sin instrucción específica; los tests usan datos/transporte sintéticos. Los recursos de marca obtenidos del sitio no son archivos de clientes del módulo Documents.
- Se ejecutan los scripts reales de pruebas PostgreSQL, Pint, lint, formato, tipos, build y comprobaciones Composer aplicables a la entrega. Se reportan resultados del commit, migraciones si fueran necesarias y dependencias operativas restantes.
- La documentación distingue implementado localmente, integrado en main y publicado. El cierre técnico de PUB1 no autoriza cambios de DNS, vhosts, document roots, WordPress o servicios en Kroma.

## Fuera del paquete

Carrito/pagos/pedidos nuevos, migración de clientes comerciales, SSO WordPress, CMS/blog, EPK/webs por tenant, nuevas funciones musicales, cambios de stack, datos privados reales y despliegue. Las capacidades publicitadas en el sitio no amplían el MVP backend por sí mismas.

## Primera entrega: PUB1.1 y PUB1.2

Integrada mediante PR #14 en main 4439aef, con CI PHP 8.3/8.4 aprobado; sin publicar. Reemplaza `/`
con la portada de referencia: logo, seis enlaces de navegación, banner musical,
introducción, siete tarjetas enlazadas, tarjeta estática de merchandising y CTA
fotográfico. El layout tiene menú móvil por botón, Enter/Tab/Escape, foco visible
y enlace para saltar al contenido. Los enlaces a Fortify hacen una navegación
completa para cargar la plantilla privada; la cuenta comercial sigue separada.

Se versionan 21 archivos originales, aproximadamente 750 KB, incluidas cuatro
fuentes WOFF2 y sus dos licencias. El [manifiesto](../references/ascaniopub/public-assets.json)
registra procedencia y hashes. No se requieren hotlinks de imágenes/fuentes ni
runtime WordPress. La portada excluye las props compartidas de cuenta y sidebar,
incluso con sesión; no consulta ni incorpora recursos de tenants.

Decisiones explícitas frente a la referencia:

- Se reserva la altura del banner para evitar que tape la introducción/tarjetas.
- Se conserva estático el titular del Mundo: no hay carrusel ni animación que
  requiera una versión alternativa para movimiento reducido.
- Se eleva el texto móvil a 16 px y el contraste de navegación, y se incorpora
  un botón visible de menú y áreas táctiles de al menos 44 px.
- Merchandising conserva su imagen sin enlace: el origen no tiene un destino
  comprobado; no se inventa una compra o servicio.
- Un pie discreto añade entrada Fortify, tienda y enlaces legales existentes.
- No se copia el banner de aceptación de terceros: esta portada no carga
  analítica, reCAPTCHA ni plugins de pago. El inventario/consentimiento final
  sigue pendiente en PUB2. Las cookies técnicas de Laravel siguen su configuración.

En esa primera entrega solo `/` tenía réplica local. Navegación, siete tarjetas, CTA,
tienda y legales conservan enlaces fijos a las páginas actuales de ascaniopub.com.
No se implementan redirecciones dinámicas ni se afirma continuidad después de
un cambio de raíz del dominio. El mapa está en resources/js/lib/public-site.ts;
PUB1.3 reemplaza esos siete destinos al entregar las seis páginas de servicios y la guía,
y PUB1.4 hará lo mismo con contacto, productos y legales. No se envían formularios,
se añade al carrito ni se inicia compra en las comprobaciones.

La secuencia acordada continuó con **PUB1.3: seis servicios y guía**, descrita abajo. PUB1 completo, PUB2/PUB3, C1 y la publicación siguen pendientes. No hay
migraciones ni cambios de Kroma/DNS/WordPress y las cargas reales siguen apagadas.

Comprobaciones ejecutadas de esta entrega:

- composer run ci:check con PHP 8.4: **183 pruebas / 2166 aserciones** sobre
  PostgreSQL, Pint, Wayfinder, lint, formato, tipos y tooling aprobados;
  Composer/npm sin avisos de vulnerabilidades.
- npm run build con PHP_BINARY=php8.4: aprobado después del ajuste visual final.
- Chrome local: escritorio 1440×1000, móvil 390×844 y anchos adicionales 320/768;
  sin imágenes faltantes, elementos recortados ni peticiones externas durante
  la carga pública. Enter abre los seis enlaces, Tab enfoca el primero y Escape
  cierra/restaura foco. No se ejercitaron compras ni envíos externos.
- [Captura desktop](../references/ascaniopub/pub1-local/home-desktop.jpg),
  [móvil](../references/ascaniopub/pub1-local/home-mobile.jpg),
  [menú abierto](../references/ascaniopub/pub1-local/menu-mobile.jpg),
  [mediciones](../references/ascaniopub/pub1-local/review.json) y
  [manifiesto](../references/ascaniopub/pub1-local/manifest.json).

Estos resultados no sustituyen PUB1.5/PUB3 sobre las dieciséis páginas ni la
revisión editorial y operativa antes de publicar. PHP 8.3 se comprueba en CI del
PR; no se declara probado localmente en esta entrega.

## Segunda entrega: PUB1.3 — servicios y guía

Preparada y comprobada localmente en feat/public-services-guide, basada en main
4439aef. Todavía no integrada ni publicada. Las seis páginas de servicios y la
guía dejan de salir al sitio de origen: la navegación y las siete tarjetas de
portada usan rutas locales generadas por Wayfinder. Contacto, tienda, fichas de
productos, legales y los destinos de Kroma siguen en los servicios existentes.

| Página local | Contenido entregado | Interacción / destino |
|---|---|---|
| /distribucion-digital | Banner, introducción, cuatro tarjetas y mosaico de plataformas | Tres fichas en la tienda actual; tarjeta de plataformas con consulta explícita |
| /publishing | Banner e introducción publicados | Solo presentación editorial; no genera registros o derechos |
| /identidad-visual | Banner, logos, diseño gráfico, paquetes y portadas | Galerías manuales con teclado/tacto y controles anterior/siguiente; sin autoplay |
| /desarrollo-web | Banner, introducción, aliado y tres planes publicados | Dominio y planes conducen explícitamente a Kroma Hosting; no consulta disponibilidad ni contrata automáticamente |
| /medios-de-comunicacion | Banner y párrafo publicados | Presentación editorial, sin una integración de prensa nueva |
| /social-media | Banner, introducción, dos propuestas y bloque de estrategia | Contacto existente; no hay campañas o envíos nuevos |
| /tus-primeros-pasos | Banner, introducción y 16 apartados íntegros | Acordeones nativos exclusivos con Enter/Espacio; listas informativas de géneros/idiomas |

Los 30 recursos públicos añadidos pesan aproximadamente 1,73 MB y conservan sus
bytes originales. El [manifiesto de recursos y contenido](../references/ascaniopub/pub13-assets.json)
identifica cada URL, dimensiones y SHA-256, además de los hashes de las copias
HTML de origen usadas para extraer contenido. Esas copias brutas permanecen
fuera de Git: no se incorporan plugins, estilos ejecutables, tracking o claves.
Los logos y ejemplos con marcas de terceros son portfolio ya publicado en la
referencia, no tenants, fixtures de contrapartes ni código de Dilo reutilizado.

La guía se conserva como JSON editorial versionado. Se retiraron atributos de
WordPress, estilos inline y el enlace vacío «Aquí» sin destino, manteniendo su
texto. Solo admite etiquetas de formato/lista/tabla y los dos selectores
informativos con nombres accesibles; los únicos enlaces de su cuerpo apuntan a
la política antifraude publicada. La prueba verifica la allowlist de etiquetas
y atributos de todo el HTML entregado. No recibe HTML de solicitudes, cuentas
o tenants, y no aporta reglas universales al catálogo.

Diferencias deliberadas y pendientes:

- Se reserva altura real en todos los banners para mostrar el contenido oculto
  por superposición en la referencia. Se ajustan el recorte de imagen y ancho
  del texto móvil, manteniendo cuerpo de 16 px y foco visible.
- Las galerías empiezan en un orden estable, sin autoplay; respetan movimiento
  reducido y permiten ver todas las muestras originales. La fase capturada de
  un carrusel del sitio antiguo puede mostrar otra muestra inicial.
- La tarjeta de plataformas de distribución conserva su imagen (que incluye
  «Comprar»), pero el origen tiene href vacío. Se añade «Consultar este servicio»
  con destino de contacto existente; no se inventa un checkout para ella. PUB2
  debe definir ese recorrido antes de cualquier publicación.
- La búsqueda de dominios usa un enlace explícito al proveedor en lugar de un
  campo que devolvería disponibilidad simulada. Los planes conservan texto y
  precios de referencia, con aviso de confirmar condiciones vigentes en Kroma;
  no se probó ni ejecutó una compra.
- Precios, promesas publicitarias, portfolio y contenido de guía siguen pendientes
  de revisión editorial del titular antes de publicación. Replicarlos no prueba
  la capacidad del SaaS, una relación comercial vigente o validez jurídica.

Verificación ejecutada:

- composer run ci:check con PHP 8.4, PostgreSQL/Redis: **192 pruebas / 2466
  aserciones**, Pint, lint, formato, tipos, tooling y auditorías aprobados.
- Después de los ajustes visuales finales: build, lint, formato y tipos repetidos
  y aprobados. No hubo cambios de dependencias o migraciones.
- Chrome: 28 combinaciones de siete páginas y anchos 1440/390/320/768; todas
  responden 200, sin imágenes faltantes, recortes horizontales fuera de galerías
  intencionalmente desplazables, errores de JS o peticiones externas al cargar.
- Se probaron menú Enter/Tab/Escape, galería siguiente/anterior por teclado con
  movimiento reducido y los 16 apartados de la guía, incluidos Enter/Espacio.
  La portada conserva sus siete enlaces de servicios locales.
- [Manifiesto de capturas](../references/ascaniopub/pub13-local/manifest.json),
  [mediciones e interacciones](../references/ascaniopub/pub13-local/review.json),
  [distribución móvil](../references/ascaniopub/pub13-local/distribution-mobile.jpg),
  [identidad desktop](../references/ascaniopub/pub13-local/visual-identity-desktop.jpg)
  y [guía cerrada](../references/ascaniopub/pub13-local/guide-mobile-collapsed.jpg).

La siguiente entrega es **PUB1.4: contacto, listado comercial, tres productos y
tres legales**. PUB1.5/PUB2/PUB3 y la publicación siguen pendientes. No se alteran
Kroma, WordPress, DNS o compras existentes; DOCUMENT_UPLOADS_ENABLED sigue false.
