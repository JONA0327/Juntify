# Fix para Audios WebM - Problema de Truncamiento a 10 minutos

## Problema Identificado
Los audios WebM de larga duración (ej: 1:09 horas) se estaban transcribiendo solo los primeros 10 minutos, mientras que los MP3 se transcribían completamente.

## Causa Raíz
1. **Timeout insuficiente**: El timeout estaba configurado en 600 segundos (10 minutos) para archivos WebM
2. **Configuración AssemblyAI incompleta**: Faltaban parámetros específicos para archivos largos
3. **PHP timeout insuficiente**: El script PHP se cortaba antes de completar la transcripción

## Soluciones Implementadas

### 1. Incremento de Timeouts
- **PHP Script**: De 900s (15 min) a 7200s (2 horas) para WebM
- **HTTP Timeout**: De 600s (10 min) a 3600s (1 hora) para WebM
- **AssemblyAI Config**: Timeout específico para archivos largos

### 2. Configuración AssemblyAI Optimizada para WebM
```php
$webmConfig = [
    'boost_param'       => 'default',
    'filter_profanity'  => false,
    'dual_channel'      => false,
    'speed_boost'       => false,      // CRÍTICO: evita truncamiento
    'audio_start_from'  => null,      // Sin recorte de inicio
    'audio_end_at'      => null,      // Sin recorte de final
    'webhook_url'       => null,      // Procesamiento síncrono
    'auto_highlights'   => false,     // Reduce procesamiento
    'word_boost'        => [],
    'redact_pii'        => false,
];
```

### 3. Detección Mejorada de WebM
- Detección por MIME type y extensión de archivo
- Logging específico para archivos WebM detectados
- Aplicación consistente en upload directo y chunked

### 4. Logging Detallado
- Información de timeouts aplicados
- Configuraciones específicas para WebM
- Tracking del procesamiento de archivos largos

## Archivos Modificados

### TranscriptionController.php
- `getWebMOptimizedConfig()`: Configuración específica para WebM
- Timeouts incrementados: 2 horas PHP, 1 hora HTTP
- Logging detallado del procesamiento

### ProcessChunkedTranscription.php
- Detección de WebM en archivos chunked
- Aplicación de configuraciones optimizadas
- Timeouts consistentes con el controlador

## Testing
Para probar el fix:
1. Subir un archivo WebM de más de 10 minutos
2. Verificar en logs la detección de WebM
3. Confirmar que se aplican las optimizaciones
4. Esperar transcripción completa (puede tomar tiempo proporcional al audio)

## Puntos Críticos
- `speed_boost: false` es esencial para evitar truncamiento
- `audio_end_at: null` asegura que no se corte el final
- Timeouts de 1 hora para HTTP permiten archivos de hasta ~2 horas
- Procesamiento síncrono con `webhook_url: null`

## Monitoreo
Los logs mostrarán:
- "WebM file detected - applying long audio optimizations"
- "Applied WebM optimizations" con configuraciones específicas
- Timeouts utilizados en cada operación
