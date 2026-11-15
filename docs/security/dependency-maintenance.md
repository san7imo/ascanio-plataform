# Mantenimiento de dependencias — P0

Fecha de revisión: 14 de noviembre de 2026 (America/Bogota).
Alcance: dependencias y controles de CI; sin nuevas funciones de negocio ni migraciones.

## Evidencia inicial

Los lockfiles de `b1629d1` producen 11 avisos Composer en 6 paquetes (Laravel y componentes Symfony) y 12 dependencias npm afectadas: 11 moderadas y una alta. El número npm incluye propagación de avisos a paquetes que dependen de otro afectado; no son 12 fallos independientes de la aplicación.

Se revisaron los requisitos de las versiones candidatas y los avisos del proveedor antes de instalar. No se encontraron llamadas de aplicación a Concurrency::run, normalizadores Symfony o configuración private_ranges en app/bootstrap/routes/config. Esto limita la evidencia de exposición actual, pero no justifica conservar versiones afectadas. En frontend, follow-redirects y qs llegan a través de Axios/Inertia; minimatch llega mediante herramientas de desarrollo.

Fuentes primarias:

- [Laravel: concurrencia](https://github.com/laravel/framework/security/advisories/GHSA-63hm-g96r-2m2q) y [Laravel 12.71.0](https://github.com/laravel/framework/releases/tag/v12.71.0).
- [Symfony: Serializer](https://github.com/symfony/symfony/security/advisories/GHSA-pxj9-jh84-vhm5); el resultado de Composer identifica además HttpFoundation, HttpKernel, Mime y Routing.
- [follow-redirects](https://github.com/follow-redirects/follow-redirects/security/advisories/GHSA-mrwp-f3q8-3g9p), [qs](https://github.com/ljharb/qs/security/advisories/GHSA-6pq8-jhm2-2vcw) y [minimatch](https://github.com/isaacs/minimatch/security/advisories/GHSA-g3hv-75wg-m249).
- [Vue language tools 3](https://github.com/vuejs/language-tools/releases/tag/v3.0.0), [minimatch 11.0.1](https://github.com/isaacs/minimatch/releases/tag/v11.0.1) y metadatos de las versiones concretas en npm/Packagist.

## Compatibilidad y decisión previa a la actualización

Laravel permanece en 12 y los componentes Symfony en 7.4, compatibles con PHP 8.3/8.4. Se eleva el mínimo directo de Laravel a 12.71. Los parches quedan fijados en composer.lock, sin añadir Symfony como dependencia directa innecesaria. El solver se ejecuta con actualización dirigida y cambios mínimos; una prueba preliminar descartó Mime 7.4.23 porque solo estaba publicada 7.4.22 para ese componente.

vue-tsc 2.2.12 es la última versión publicada de la rama 2 y fija @vue/language-core 2.2.12, que requiere minimatch 9 afectado según npm audit. Se migra explícitamente la herramienta de desarrollo a vue-tsc 3.3.14: requiere TypeScript >=5.0, satisfecho por el TypeScript instalado, y su language-core ya usa picomatch. La aplicación usa Vue 3 y no depende de Vue 2/vue-class-component retirados por esa generación. La migración se valida con typecheck y build, sin relajar tsconfig ni omitir errores. No cambia Vue, Inertia ni el framework backend de generación mayor.

La versión publicada typescript-eslint 8.72.2 todavía requiere minimatch ^10.2.2; 8.72.3 no está publicada. Se aplica un override exacto de minimatch 11.0.1 únicamente bajo @typescript-eslint/typescript-estree. Es un cambio fuera del rango transitivo de upstream, acotado y explícito: el código inspeccionado consume minimatch(filePath, pattern, { dot: true }), cuya API se conserva. Node 22.17.0 satisface sus engines. Su compatibilidad requiere comprobar el recorrido real de projectService con allowDefaultProject además del lint normal.

No se sobreescribe minimatch globalmente ni se fuerza un downgrade de eslint-plugin-vue sugerido por npm audit. El override no silencia el aviso: instala la versión corregida. Desarrollo es responsable de revisarlo en cada actualización de typescript-eslint y de retirarlo cuando la dependencia upstream resuelva una versión corregida y la auditoría/compatibilidad sigan pasando.

## Política de auditoría

CI audita ambos lockfiles en un job independiente, en PRs/push a main, semanalmente los lunes a las 12:00 UTC y bajo ejecución manual. Composer incluye dependencias de desarrollo y paquetes abandonados; npm incluye dependencias de desarrollo y no eleva el umbral mínimo de severidad. No necesita descargar/ejecutar los paquetes para auditar los lockfiles.

Los comandos son `composer audit --locked --abandoned=fail` y `npm run audit:check`. Un aviso, paquete abandonado o error de consulta devuelve fallo y bloquea la aprobación de este job. No hay continue-on-error, ignores ni excepciones activas. Configurar la protección de rama en GitHub para exigir el job es una operación distinta: el workflow por sí solo no garantiza esa protección.

Una futura excepción requiere revisión explícita en PR, identificador del aviso, versión/cadena afectada, exposición, medida compensatoria, responsable y fecha de caducidad; cualquier mecanismo de excepción debe validar esa caducidad. No introducir excepciones para conseguir un check verde ni asumir que la ausencia de avisos demuestra seguridad completa.

## Verificación

Versiones resultantes relevantes:

| Dependencia | Anterior | Lockfile P0 |
|---|---|---|
| laravel/framework | 12.69.3 | 12.71.0 |
| symfony/http-foundation, http-kernel, routing, serializer | 7.4.20 | 7.4.23 |
| symfony/mime | 7.4.19 | 7.4.22 |
| follow-redirects | 1.16.1 | 1.16.3 |
| qs | 6.16.0 | 6.17.1 |
| vue-tsc / @vue/language-core | 2.2.12 | 3.3.14 |
| minimatch bajo typescript-estree | 10.2.6 | 11.0.1 |

Resultados locales ejecutados con PHP 8.4.18, Node 22.17.0 y npm 10.9.2:

- Suite PostgreSQL/Redis: 43 pruebas y 153 aserciones, aprobadas; Pint aprobado.
- Lint, formato, TypeScript y build aprobados, sin cambios en reglas de validación ni código de aplicación.
- `npm run test:tooling`: una prueba de projectService/allowDefaultProject aprobada, sin diagnósticos semánticos; queda en CI para comprobar el override fuera del recorrido del lint habitual.
- Composer validate --strict y check-platform-reqs aprobados.
- `composer run audit:check`: cero avisos Composer y cero vulnerabilidades npm; incluye desarrollo y no usa excepciones.
- Instalación real de Composer y npm ci en una copia temporal limpia, sin vendor/node_modules, completadas desde los lockfiles. No se copiaron .env ni datos privados.
- Control negativo sin instalar paquetes: copias de los lockfiles originales en otro directorio temporal hicieron fallar ambos comandos de auditoría con exit 1 (11 avisos Composer y 12 dependencias npm). El check no pasa por omitir la instalación o por consultar un árbol vacío.

GitHub Actions comprueba además PHP 8.3/8.4 y el job dependency-audit sobre el head del PR. Los resultados locales no sustituyen ese estado remoto. No hay migraciones, datos de cliente, cambios de hosting ni despliegues en esta entrega.

## Seguimientos de soporte

La instalación avisa que ESLint 9.39.5 llegó a EOL y que lucide-vue-next fue deprecado a favor de @lucide/vue. Las [fechas oficiales de soporte de ESLint](https://eslint.org/version-support/) y la [guía de Lucide Vue](https://lucide.dev/guide/vue) requieren planificar migraciones con revisión de compatibilidad de plugins y de imports/iconos. No se declara que esas herramientas tengan soporte activo por pasar audit; tampoco se ocultan sus warnings.

Se mantienen en esta entrega de parches para evitar mezclar la migración del lint y los componentes visuales con la resolución de los avisos identificados. Desarrollo debe abrir un mantenimiento acotado antes de ampliar las herramientas dependientes; estos seguimientos no son excepciones a vulnerabilidades ni autorizan una compra de soporte comercial. El override de minimatch se revisa en la próxima actualización de typescript-eslint.
