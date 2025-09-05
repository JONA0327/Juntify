# Implementación de Subida por Chunks para Audios Largos

## Problema Resuelto

El timeout de 120 segundos en AssemblyAI para audios de 1-2 horas ha sido resuelto implementando un sistema de subida por chunks inteligente.

## 🔧 Solución Implementada

### 1. Sistema de Detección Automática
- **Audios pequeños (<10MB)**: Subida estándar con `timeout: 0` (sin límite)
- **Audios grandes (>10MB)**: Subida automática por chunks de 8MB

### 2. Arquitectura de Chunks

#### Frontend (JavaScript)
- **Chunk size**: 8MB por fragmento
- **Concurrencia**: Máximo 3 chunks subiendo simultáneamente
- **Reintentos**: 3 intentos por chunk con backoff exponencial
- **Timeout por chunk**: 3 minutos (suficiente para 8MB)
- **Timeout general**: Sin límite (`timeout: 0`)

#### Backend (PHP)
- **Endpoints nuevos**:
  - `POST /transcription/chunked/init` - Inicializar subida
  - `POST /transcription/chunked/upload` - Subir chunk individual
  - `POST /transcription/chunked/finalize` - Finalizar y procesar

### 3. Configuración Optimizada

#### Timeouts por Operación
```javascript
// Inicialización: 30 segundos
{ timeout: 30000 }

// Upload de chunk: 3 minutos
{ timeout: 180000 }

// Finalización: 1 minuto
{ timeout: 60000 }

// AssemblyAI upload: 5 minutos (configurable)
{ timeout: 300000 }
```

#### PHP Time Limits
```php
// Upload de chunk: 5 minutos
set_time_limit(300);

// Procesamiento final: 15 minutos
set_time_limit(900);

// Subida estándar: 10 minutos
set_time_limit(600);
```

## 🚀 Flujo de Trabajo

### Para Audios Pequeños (<10MB)
1. Detección automática del tamaño
2. Subida directa con `timeout: 0`
3. Procesamiento normal en AssemblyAI

### Para Audios Grandes (>10MB)
1. **Inicialización**: Crear sesión de upload con ID único
2. **División**: Dividir archivo en chunks de 8MB
3. **Subida paralela**: Subir máximo 3 chunks simultáneamente
4. **Verificación**: Confirmar que todos los chunks llegaron
5. **Combinación**: Unir chunks en archivo final
6. **Procesamiento**: Enviar a AssemblyAI con configuración optimizada
7. **Limpieza**: Eliminar archivos temporales

## 📊 Ventajas del Sistema

### 1. Robustez
- **Reintentos automáticos**: Si un chunk falla, se reintenta hasta 3 veces
- **Backoff exponencial**: Delay incremental entre reintentos (2s, 4s, 8s)
- **Recuperación**: Subida puede continuar aunque algunos chunks fallen inicialmente

### 2. Rendimiento
- **Paralelización**: 3 chunks suben simultáneamente
- **Sin bloqueo**: La interfaz sigue respondiendo durante la subida
- **Progreso granular**: Indicador de progreso por chunk y total

### 3. Escalabilidad
- **Archivos grandes**: Soporte para audios de hasta 2 horas sin problemas
- **Memoria eficiente**: Procesamiento por chunks evita cargar todo en memoria
- **Configuración flexible**: Tamaños de chunk y timeouts ajustables

## 🔍 Debugging Integrado

### Logs Detallados
```javascript
🔍 [startTranscription] Audio size: 45.67 MB
📤 [startTranscription] Using chunked upload for large audio
🔧 [startChunkedTranscription] Initializing chunked upload
✅ [startChunkedTranscription] Upload initialized with 6 chunks
✅ [uploadChunk] Chunk 1/6 uploaded successfully
📤 [startTranscription] Using standard upload for small audio
```

### Monitoreo de Progreso
- Progreso por chunk individual
- Progreso total de la subida
- Estados de reintentos
- Tiempo estimado restante

## ⚙️ Configuración

### Variables de Entorno Recomendadas
```env
# AssemblyAI timeouts optimizados
ASSEMBLYAI_TIMEOUT=300
ASSEMBLYAI_CONNECT_TIMEOUT=60

# PHP memory y execution time
memory_limit=512M
max_execution_time=900
upload_max_filesize=200M
post_max_size=200M
```

### Configuración de Chunks (Modificable)
```javascript
const CHUNK_SIZE = 8 * 1024 * 1024; // 8MB
const MAX_RETRIES = 3;
const CONCURRENT_UPLOADS = 3;
const RETRY_DELAY = 2000; // 2 segundos
```

## 🎯 Resultados Esperados

### Para Audios de 1-2 Horas
- ✅ **Sin timeouts**: Sistema maneja archivos grandes sin límite de tiempo
- ✅ **Subida confiable**: Reintentos automáticos para chunks fallidos
- ✅ **Progreso claro**: Usuario ve progreso detallado de la subida
- ✅ **Recuperación**: Si falla un chunk, solo se reintenta ese fragmento

### Compatibilidad
- ✅ **Audios pequeños**: Siguen usando subida rápida tradicional
- ✅ **Sin cambios UI**: La interfaz se ve igual para el usuario
- ✅ **Fallback**: Si chunks fallan, se guarda copia de seguridad del audio

## 📝 Archivos Modificados

1. **resources/js/audio-processing.js**
   - Nuevas funciones: `startChunkedTranscription()`, `startStandardTranscription()`
   - Lógica de detección automática de tamaño
   - Sistema de reintentos y concurrencia

2. **app/Http/Controllers/TranscriptionController.php**
   - Métodos: `initChunkedUpload()`, `uploadChunk()`, `finalizeChunkedUpload()`
   - Procesamiento optimizado para archivos grandes
   - Limpieza automática de archivos temporales

3. **routes/web.php**
   - Nuevas rutas para endpoints de chunks
   - Middleware de autenticación aplicado

## 🧪 Cómo Probar

1. **Grabar audio largo** (>10MB)
2. **Observar logs en consola**: Verás "Using chunked upload for large audio"
3. **Monitorear progreso**: Indicador mostrará progreso por chunks
4. **Verificar resultado**: Transcripción debe procesar sin timeouts

La implementación está lista y debe resolver completamente los problemas de timeout para audios largos.
