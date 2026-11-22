# Preparación operativa de Foundation

Fecha de corte: 15 de noviembre de 2026. Estado: procedimiento preparado; operación Kroma y recuperación allí no comprobadas. Complementa [O0 y O1](../implementation-plan.md), la [matriz Kroma](../hosting/kroma-compatibility.md) y la [separación PostgreSQL](postgresql-runtime-role.md). No es una orden de despliegue ni un registro de operaciones realizadas en producción.

## Puertas antes de staging o datos reales

| Puerta | Evidencia necesaria | Estado |
|---|---|---|
| Runtime Kroma | PHP web/CLI compatible, extensiones, Composer, PostgreSQL/Redis y región comprobados | PHP CLI/extensiones aportados por el titular; conexiones TCP locales rechazadas; resto pendiente O0 |
| Procesos | Supervisión/reinicio de worker o alternativa acotada permitida por el proveedor; límites y cron | Pendiente O0 |
| Permisos | Credenciales distintas de migración/runtime; runtime sin DDL ni modificación del historial | Probado solo localmente con login restringido |
| Web y storage | HTTPS, raíz pública exclusiva public/, storage privado persistente fuera de toda raíz pública, permisos y secretos fuera del repositorio | Pendiente O0 |
| Correo | SMTP/TLS autorizado, remitente/dominio y cuotas; prueba sintética de cola hasta recepción | Pendiente O0 |
| Logs y capacidad | Cuota/inodos actuales compartidos, reservas, retención y alertas; redacción de enlaces de cuenta en accesos/errores | Pendiente O0/O1 |
| Recuperación | Copias privadas de BD/configuración/claves y archivos necesarios; retención, RPO/RTO y restauración ensayada | Pendiente O0/O1 |

El dato de 5,73 GB de octubre no se presume actual ni exclusivo de Ascanio. Medir cuota de cPanel e inodos además de uso del proyecto; no inferir capacidad por df ni borrar correo/proyectos/evidencias existentes. No se compra ni configura hosting/almacenamiento externo como sustitución implícita.

## Colas y correo

Web y workers deben usar la misma versión de aplicación y APP_KEY. Redis transporta los comandos cifrados; su persistencia, autenticación/red y recuperación se validan antes de staging. No cambiar ni regenerar APP_KEY en una instalación con datos cifrados. Limitar acceso a .env, backups, sesiones y failed_jobs: cifrado del comando no convierte sus metadatos en públicos.

En desarrollo, docker compose up -d --wait inicia únicamente los servicios del repositorio. composer run dev usa el launcher local existente; no configura supervisión de producción. Los tests requieren ascanio_test, no la base de desarrollo ni otra URL. El receptor array sirve para pruebas internas; para inspeccionar mensajes usar SMTP sintético local. El transporte log de .env.example no entrega invitaciones ni correos de cuenta desde F4.

En un entorno autorizado y ya configurado, el worker convencional procesa la cola Redis compartida con queue:work. Los jobs existentes tienen timeout de 45 segundos y tres intentos con backoff; SMTP usa MAIL_TIMEOUT, 15 segundos por defecto. Verificar que retry_after sea mayor que el timeout efectivo y que el supervisor conceda tiempo para terminar el job antes de detenerlo. Documentar los binarios, rutas, usuario, número de procesos y límites reales de Kroma antes de fijar su comando/supervisión. Una alternativa por cron requiere una decisión comprobada del proveedor y protección contra solapamientos; no está configurada aquí.

Tras cambiar el código, queue:restart solicita salida después de la tarea actual; un supervisor debe volver a iniciar el proceso con la nueva release. El comando solo no acredita que exista ese supervisor. Verificar además que web/worker comparten el cache de la señal. No procesar tareas antiguas durante una migración de gobierno/acceso que requiera pausa.

Revisar fallos mediante IDs/estado acotado. Las invitaciones muestran failed, blocked o simulated; queue_unavailable permite reenviar con una confirmación sensible nueva. authority_changed exige una propuesta nueva con autoridad vigente. No reintentar todas las tareas fallidas a ciegas. Para un job de cuenta fallido, comprobar transporte y vigencia; pedir un enlace nuevo suele ser preferible a recuperar uno vencido. Un SMTP aceptado no demuestra recepción; reintentos pueden duplicar mensajes sin duplicar aceptación de membresías.

## Logs, archivos y capacidad

No registrar tokens, contraseñas, cuerpos de correo, comandos descifrados, códigos MFA ni datos completos de formularios. Los enlaces de invitación llevan token en fragmento y no en la URL HTTP. Los enlaces estándar de verificación/reset de cuenta conservan su protocolo Laravel con firma/token en URL: antes de datos reales configurar y comprobar redacción en servidor/proxy/APM y errores, y evitar capturas o analítica de esas rutas. No afirmar ausencia de secretos en todos los logs basándose solo en los tests de aplicación.

Definir rotación, acceso y retención para logs de Laravel/worker/webserver, failed_jobs y temporales dentro de la cuota compartida. No introducir un purge automático sin política y sin distinguir fallos operativos de evidencia de auditoría. Vigilar acumulación de cola/fallos, latencia SMTP, errores repetidos y espacio/inodos; asignar responsable antes de staging.

El disco local del scaffold apunta a storage/app/private; no habilita un flujo de uploads autorizado. No ejecutar storage:link para documentos privados ni usar public/public_html como raíz de estos datos. D1/O1 deben concretar ruta persistente configurada, nombres por tenant, límites, streams/temporales, análisis de malware, reserva y descarga autenticada antes de recibir archivos reales. El ejemplo S3 inactivo del scaffold no es una dependencia del producto.

## Cambio de release y recuperación

Antes de cada cambio autorizado registrar base/head, checks del commit, configuración y migraciones pendientes sin copiar secretos al registro. Confirmar backup reciente y restaurable, ventana, responsable y criterio de cancelación. Pausar escrituras y workers cuando lo exija la migración; ejecutar migraciones con la cuenta migradora, nunca dando DDL a la cuenta web. Revisar grants runtime para cada tabla nueva. F4 no añade ni requiere migraciones.

Conservar el código anterior y un backup compatible, mantener storage persistente y APP_KEY segura; no incluir claves en Git. Volver al código anterior solo si es compatible con el esquema actual. Para defectos de esquema compartido priorizar una migración correctiva hacia delante; los downgrades de Foundation con historial pueden ser rechazados para preservar auditoría/invitaciones.

El rollback total histórico del scaffold elimina users antes de sessions y no es el procedimiento de recuperación aprobado. No se reescribe su historia ni se propone migrate:fresh sobre datos compartidos. Los tests usan reconstrucción exclusivamente en ascanio_test; esto no es restauración de una instalación real.

Ensayar en un entorno aislado y explícitamente autorizado: restaurar copia de PostgreSQL, claves/configuración y archivos privados que existan; verificar versión de aplicación/esquema, login, selección A/B, revocación, historial, cola/correo sintético y coherencia de archivos/checksums. Aislar correo/colas del entorno origen para no reenviar notificaciones. Registrar tiempos reales, pérdidas posibles y resultado frente a RPO/RTO acordados. Conservar evidencias y aprobar retención/descarga privada de copias antes de afirmar que la recuperación está lista.

Este ensayo de backup/restore y la disponibilidad real de Kroma siguen pendientes. Las pruebas locales de upgrade/rollback acotado no los sustituyen. Foundation está cerrado técnicamente en [F4](../implementation/f4-foundation-readiness.md), con su cierre operativo abierto.
