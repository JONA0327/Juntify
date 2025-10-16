# 🚫 Restricciones de Google Drive por Plan

Este documento resume el comportamiento actualizado para los planes **Free** y **Basic** en relación con la conexión a Google Drive, así como el almacenamiento temporal que entra en vigor cuando el acceso está bloqueado.

## Planes con acceso permitido

Los roles/planes que **sí** pueden utilizar Drive de forma directa son:

- Business / Buisness (variantes de escritura admitidas)
- Enterprise / Enterprice
- Developer
- Superadmin

Cuando alguno de estos roles conecta Drive:

1. Los archivos se suben primero a Drive.
2. Los enlaces resultantes se guardan en `transcriptions_laravel`.
3. En la interfaz se mantienen los botones de exportación a Drive.

## Bloqueo para Free y Basic

Para usuarios Free o Basic (incluyendo degradaciones desde planes superiores):

- El botón de conexión a Drive muestra un modal informativo y se bloquea la acción.
- Las reuniones nuevas guardan el audio directamente en la base temporal `transcriptions_temp`.
- El archivo `.ju` permanece, pero los audios se eliminan una vez cumplido el periodo de retención.
- Las reuniones antiguas creadas cuando tenían plan superior siguen visibles, pero ya no pueden enviarse nuevamente a Drive.

## Retención temporal

- Plan **Free**: los audios se mantienen durante 7 días antes de eliminarse automáticamente.
- Plan **Basic**: los audios se mantienen durante 15 días antes de eliminarse.

La interfaz de reuniones muestra una **etiqueta con cuenta regresiva** y advertencias en los modales para informar cuánto tiempo resta antes de que el audio sea purgado.

## Flujos con Drive desconectado

- Si un rol con acceso permitido (Business/Enterprise/Developer/Superadmin) todavía no conectó Drive, las reuniones se almacenan temporalmente.
- Una vez que conecten su Drive, la interfaz ofrece la opción de mover los audios junto con el `.ju` a `transcriptions_laravel`.

## Notas pendientes

- Documentación pública / FAQs debe actualizarse con este flujo.
- Falta automatizar la migración de reuniones temporales a Drive cuando un usuario premium reconecta su cuenta después de degradarse y volver a subir de plan.
