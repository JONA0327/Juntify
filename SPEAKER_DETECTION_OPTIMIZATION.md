# Optimización de Detección de Hablantes - AssemblyAI

## Problema Identificado

El sistema estaba configurado con parámetros muy sensibles que causaban la detección de múltiples hablantes falsos cuando solo había una persona hablando:

### Configuración Problemática Anterior:
- `speakers_expected: 4` - **Forzaba la detección de 4 hablantes**
- `speech_threshold: 0.1` - **Extremadamente sensible** a cambios de tono/ruido
- Causaba que pausas, cambios de tono o ruido de fondo se interpretaran como nuevos hablantes

## Solución Implementada

### 1. Detección Automática de Hablantes
- **Eliminado** el parámetro `speakers_expected` 
- Permite que AssemblyAI detecte automáticamente el número real de hablantes
- No fuerza un número específico de personas

### 2. Umbral de Sensibilidad Balanceado
- `speech_threshold: 0.4` para MP3/MP4 (anteriormente 0.1)
- `speech_threshold: 0.5` para WebM (menos sensible para formato complejo)
- Evita falsos positivos por cambios menores de voz

### 3. Configuración Optimizada por Formato

#### Para MP3/MP4 (Formatos Recomendados):
```php
'speaker_labels' => true,           // Detección activa
'format_text' => false,             // Mejor para speaker detection
'speed_boost' => false,             // Máxima calidad
'speech_threshold' => 0.4,          // Balanceado
'dual_channel' => false,            // Análisis mono para mejor detección
// speakers_expected REMOVIDO para detección automática
```

#### Para WebM (Archivos Largos):
```php
'speaker_labels' => true,           // Detección automática
'speech_threshold' => 0.5,          // Menos sensible
'format_text' => false,             // Procesamiento mínimo
'speed_boost' => false,             // Sin aceleración
// speakers_expected REMOVIDO para detección automática
```

## Archivos Modificados

### 1. TranscriptionController.php
- **Función:** `getOptimizedConfigForFormat()`
- **Cambio:** Configuración automática sin forzar número de hablantes
- **Ubicación:** Líneas 19-60

### 2. ProcessChunkedTranscription.php (Job)
- **Función:** `uploadToAssemblyAI()`
- **Cambio:** Configuración balanceada para archivos grandes
- **Ubicación:** Líneas 157-195

## Resultados Esperados

### Antes (Problemático):
```
Hablante A: "Prueba de audio."
Hablante B: "Para ver si"  
Hablante C: "lo está procesado"
Hablante D: "porque no me quiso grabar."
```

### Después (Optimizado):
```
Hablante A: "Prueba de audio. Para ver si lo está procesado porque no me quiso grabar."
```

## Beneficios

1. **Detección Natural:** AssemblyAI usa sus algoritmos avanzados sin restricciones artificiales
2. **Menos Falsos Positivos:** Threshold balanceado evita fragmentación innecesaria
3. **Mejor UX:** Transcripciones más naturales y fáciles de leer
4. **Compatibilidad:** Funciona igual para archivos pequeños y grandes
5. **Mantenimiento:** Configuración más simple y robusta

## Pruebas Recomendadas

1. **Audio de una persona:** Debería detectar 1 hablante
2. **Audio con pausas largas:** No debería crear hablantes falsos
3. **Audio con cambios de tono:** Debería mantener el mismo hablante
4. **Audio de múltiples personas:** Debería detectar correctamente cada hablante real

## Logs de Verificación

Los logs ahora muestran:
```
Applied MP3 AUTO-DETECTION config for natural speaker detection
speakers_expected: AUTO (not forced)
speech_threshold: 0.4
```

En lugar de:
```
Applied MP3 ULTRA SENSITIVE config for multiple speakers  
speakers_expected: 4
speech_threshold: 0.1
```

## Configuración Técnica

### Variables Clave de AssemblyAI:
- `speaker_labels: true` - Habilita detección de hablantes
- `speech_threshold: 0.4` - Umbral balanceado (0.0-1.0)
- `speakers_expected: REMOVIDO` - Permite detección automática
- `dual_channel: false` - Análisis mono para mejor detección
- `format_text: false` - Mejor calidad de speaker detection

### Compatibilidad:
- ✅ MP3 (Recomendado)
- ✅ MP4/M4A (Recomendado) 
- ✅ WebM (Soporte limitado)
- ❌ Otros formatos (Rechazados)

---

**Fecha de Implementación:** 8 de Septiembre, 2025  
**Versión:** 2.0 - Detección Automática Inteligente
