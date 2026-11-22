# O1 — Preparación operativa de archivos privados

Corte: 21 de noviembre de 2026. Estado: controles técnicos D1 implementados; valores/adaptadores del entorno y habilitación real pendientes. Complementa la [matriz Kroma](../hosting/kroma-compatibility.md), [Foundation operativo](foundation-readiness.md), [grants PostgreSQL](postgresql-runtime-role.md) y [D1](../implementation/d1-private-documents.md). No autoriza conexiones, instalaciones, pruebas con datos reales o despliegues.

El titular confirmó que Kroma dispone de capacidad y almacenamiento. Falta medir/configurar lo necesario para habilitar archivos, sin pedir credenciales ni presumir el margen antiguo de 5,73 GB.

| Evidencia / decisión | Necesaria antes de habilitar | Estado |
|---|---|---|
| Cuota e inodos actuales de cuenta, consumo compartido y método de medición | CapacityProbe autorizado que devuelva disponibles de cuenta y timestamp fresco; no solo df | Pendiente de valores/integración |
| Ruta privada persistente y todos los document roots | DOCUMENT_PRIVATE_ROOT absoluto, fuera de todas las raíces públicas; permisos, open_basedir y persistencia comprobados | Pendiente de ruta/prueba autorizada |
| Presupuestos de archivos gestionados | Máximo por archivo/tenant/app, reserva de cuenta e inodos, crecimiento y responsables | Defaults cero; cifras reales pendientes |
| Temporales y límites HTTP/PHP/worker | Presupuesto para upload temporal, copia verificada/escáner, concurrencia, tiempos y límites efectivos | Pendiente O0/O1 |
| Escáner de Kroma | Método/adaptador real, disponibilidad, versión/firmas, límites de stream/tamaño/timeout y tratamiento de fallo | No seleccionado ni integrado |
| Retención y reconciliación | Originales/cuarentena/infectados, fallos, logs/temporales, huérfanos tras commit incierto; sin purgar evidencia automáticamente | Pendiente de política |
| Cifrado, claves y copias | Protección de documentos/disco/copias admitida, custodios de claves y restauración coherente DB/archivos | Pendiente de decisión/ensayo |

Los originales se almacenan mediante disco local privado, sin cifrado por archivo implementado en D1. Hash y permisos no sustituyen cifrado/backup. No se asume cifrado de disco del proveedor ni se declara recuperación por haber ejecutado migrate:fresh en ascanio_test. No contratar otro servicio ni cambiar el stack implícitamente.

## Configuración y activación futura

DOCUMENT_UPLOADS_ENABLED=false es el valor de entrega. Los cinco límites/reservas de .env.example son cero y deben concretarse con la cuenta, no copiar las cifras de fixtures. Los adaptadores de capacidad y malware por defecto rechazan la operación, incluso si se activa solo el flag. No usar las clases de tests en web/workers operativos: su guard exige testing/ascanio_test y sus resultados son sintéticos.

O1 debe aportar un probe real de cuota/inodos y un escáner disponible dentro de Kroma, con sus permisos y costes admitidos, antes de implementar el adaptador. Debe comprobar su comportamiento sobre archivos soportados, muestras sintéticas admitidas por operación y errores/timeouts. Un resultado distinto de clean no libera nada. La interfaz de scanner admite un stream verificado; el adaptador es responsable de limitar/supervisar su consumo según los tiempos acordados. No se ha instalado ClamAV ni otro software en Kroma o en este equipo para simular su disponibilidad allí.

Inventariar todos los web roots de la cuenta en documents.public_roots, además del public/ propio comprobado automáticamente. La raíz privada debe ser exclusiva y persistir entre releases; no apuntar al HOME completo, al proyecto entero, a public/public_html o a un alias público. Preservar los sitios/correos existentes. No ejecutar storage:link sobre datos privados. Rehacer cachés de configuración/rutas en el entorno autorizado y comprobar que los discos privados no tengan rutas de entrega firmada sin autenticación; local y documents llevan serve=false.

Web y workers deben compartir release, APP_KEY, conexión/cola y raíz privada. La admisión requiere PostgreSQL con advisory locks transaccionales y READ COMMITTED; el test runtime comprueba su ejecución sin elevar el login web. La suma global de bytes es solo control de presupuesto del módulo, no una API de estadísticas multi-tenant ni un bypass de autorización. El probe de cuenta debe incluir el consumo real ajeno al módulo; las reservas cubren otras cargas y temporales. Medir bajo límites reales antes de decidir concurrencia/tiempos.

En una tarea y entorno autorizados, ensayar primero con fixtures: flag cerrado y adaptadores ausentes deniegan; tipo/tamaño incorrectos no persisten; fallo de escritura/DB conserva integridad y registra conciliación; scan_failed nunca descarga; un usuario con metadata no recibe archivo; revocación y checksum alterado bloquean; dos cargas concurrentes no exceden presupuesto ni sobrescriben versiones; reinicio/reintento no cambia otra generación. Verificar HTTPS, sesión/CSRF, attachment/nosniff/no-store y acceso directo inexistente a las rutas privadas.

## Fallos, retención y recuperación

El estado scan_failed permite reintento explícito autorizado. Revisar primero scanner/cola/transporte y presupuesto; un estado blocked o infected no se reabre por botón de retry. La historia original y los hashes permanecen. Un file_missing o integrity_mismatch se investiga como incidencia, sin sustituir bytes bajo el mismo ID/version.

QueueDocumentScan registra queue_unavailable si no pudo despachar tras el commit; no desaparece la versión. Un retry usa generación/solicitante nuevos y anula jobs previos. No reintentar todos los fallos a ciegas. No registrar cuerpos, streams, nombres/rutas privadas completas ni excepciones crudas del proveedor; diagnosticar mediante tenant/version/generación y códigos.

El upload posee su commit. Si un fallo deja incierto el registro/limpieza, el aviso Document upload cleanup pending identifica IDs para reconciliación. Antes de eliminar nada, comprobar la base, ruta derivada, backup y custodia; este aviso no autoriza purgar archivos. El presupuesto gestionado cuenta versiones registradas y no debe confundirse con todo el consumo físico. El probe/monitorización detecta también huérfanos, logs y temporales dentro de la cuota real.

Definir retención/alertas para fallos, logs, temporales y cuarentenas con el titular; preservar originales/evidencias requeridos. No hay purga, borrado de versiones o release de presupuesto por cambiar estado. Restaurar DB, archivos y claves en una copia aislada con correo/cola separados; verificar hashes, versiones, permisos A/B, jobs pendientes y fuente de autoridad. Registrar RPO/RTO y resultado, sin sobreescribir registros actuales ni perder otros proyectos de la cuenta.

Esta entrega prepara el núcleo y sus pruebas, sin cerrar la preparación operativa. O1 se cierra con evidencias reales y decisiones registradas; la confirmación general de almacenamiento no selecciona límites, escáner, cifrado o retención por sí sola.
