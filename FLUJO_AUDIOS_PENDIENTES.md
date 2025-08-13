# Flujo Completo de Audios Pendientes

## Descripción General
Este documento describe el flujo completo implementado para procesar audios pendientes desde Google Drive, incluyendo descarga, transcripción, análisis y guardado final.

## Flujo Paso a Paso

### 1. Detección y Listado (reuniones_v2.js)
- **Modal**: Se abre con diseño consistente mostrando audios pendientes de Google Drive
- **Datos**: Se obtienen desde `/api/pending-meetings` con campos corregidos (`audio_drive_id`, `folder_id`)
- **Estado inicial**: `status = 'pending'`

### 2. Inicio de Análisis (MeetingController::analyzePendingMeeting)
**Cuando el usuario hace clic en "Analizar":**
```javascript
// Frontend envía petición
POST /api/pending-meetings/{id}/analyze
```

**Backend ejecuta:**
- Cambia status a `'processing'`
- Descarga archivo desde Google Drive usando GoogleServiceAccount
- Guarda archivo temporalmente en `storage/app/temp/`
- Mantiene estado en session con información del proceso
- Responde con `temp_file` y `redirect_to_processing: true`

### 3. Redirección y Carga (reuniones_v2.js → audio-processing.js)
**Frontend:**
- Guarda datos en `localStorage.pendingAudioData` con info del audio pendiente
- Redirige a `/audio-processing`

**Audio-processing inicialización:**
- Detecta `localStorage.pendingAudioData`
- Solicita archivo al servidor: `GET /api/pending-meetings/audio/{tempFileName}`
- Recibe audio en base64, convierte a Blob
- Limpia datos temporales
- Inicia `startAudioProcessing()` automáticamente

### 4. Procesamiento Normal
- **Paso 1**: Procesamiento de audio (merge de segmentos si existen)
- **Paso 2**: Transcripción (envío a OpenAI)
- **Paso 3**: Polling de transcripción
- **Paso 4**: Análisis con IA seleccionada
- **Paso 5**: Resultados y preview

### 5. Guardado Especial (audio-processing.js → MeetingController::completePendingMeeting)
**Cuando usuario selecciona carpeta y nombre:**
- Si `window.pendingAudioInfo` existe, usa flujo especial
- Llama a `POST /api/pending-meetings/complete` en lugar de `/drive/save-results`

**Backend (completePendingMeeting):**
- Recupera información del proceso desde session
- Mueve y renombra archivo en Google Drive usando GoogleServiceAccount
- Crea archivo .ju con transcripción encriptada
- Guarda en TranscriptionLaravel (BD principal)
- Limpia archivo temporal y session
- Elimina registro de PendingRecording
- Actualiza status a `'success'`

### 6. Finalización
- Muestra pantalla de completado con estadísticas
- Usuario puede ver detalles o volver a reuniones
- Archivo queda disponible en la vista normal de reuniones

## Archivos Modificados

### Backend
1. **MeetingController.php**
   - `analyzePendingMeeting()`: Descarga y preparación
   - `completePendingMeeting()`: Guardado final con lógica especial
   - `getPendingAudioFile()`: Endpoint para servir archivo temporal

2. **GoogleServiceAccount.php** (ya existente)
   - `downloadFile()`: Descarga desde Google Drive
   - `moveAndRenameFile()`: Mover y renombrar archivos
   - `copyFile()`: Copia de archivos

3. **routes/web.php**
   - Nueva ruta: `GET /api/pending-meetings/audio/{tempFileName}`

### Frontend
1. **reuniones_v2.js**
   - Modal rediseñado con estilo consistente
   - `analyzePendingMeeting()` modificado para redirección
   - Paso de datos via localStorage

2. **audio-processing.js**
   - Inicialización modificada para detectar audios pendientes
   - Carga de audio desde servidor via API
   - `processDatabaseSave()` con lógica condicional para audios pendientes
   - `base64ToBlob()` mejorado con soporte para mimeType

## Endpoints API

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/pending-meetings` | Lista audios pendientes |
| POST | `/api/pending-meetings/{id}/analyze` | Inicia análisis y descarga |
| GET | `/api/pending-meetings/audio/{tempFile}` | Obtiene archivo temporal |
| POST | `/api/pending-meetings/complete` | Completa procesamiento |
| GET | `/api/pending-meetings/{id}/info` | Info de procesamiento |

## Estados del Audio Pendiente

1. **pending**: Audio disponible en Google Drive, listo para procesar
2. **processing**: Audio descargado, en proceso de transcripción/análisis
3. **success**: Completado exitosamente, movido a reuniones principales
4. **error**: Error en algún paso, requiere reintento

## Manejo de Errores

- **Error de descarga**: Revierte status a 'pending'
- **Error de transcripción**: Mantiene 'processing' para reintento
- **Error de guardado**: Mantiene archivo temporal para reintento
- **Cleanup automático**: Limpia archivos temporales al completar

## Validaciones

- Usuario autenticado con Google Drive
- Archivo pertenece al usuario actual
- Estados válidos para cada operación
- Archivos temporales con nombre válido
- Session activa para operaciones de estado

## Seguridad

- Validación de pertenencia de archivos
- Tokens CSRF en peticiones POST
- Encriptación de transcripciones
- Limpieza de archivos temporales
- Session-based state management
