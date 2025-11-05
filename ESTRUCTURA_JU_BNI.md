# ESTRUCTURA DEL ARCHIVO .JU - USUARIO BNI

## 📋 Información General
- **Usuario**: CongresoBNI@gmail.com  
- **Tipo**: Almacenamiento temporal BNI
- **Formato**: JSON sin encriptación
- **Encoding**: UTF-8
- **Tamaño**: ~194KB
- **Legible**: ✅ Directamente (sin desencriptar)

## 🏗️ Estructura Principal

El archivo .ju de BNI contiene **3 campos principales**:

```json
{
  "transcription": [...],  // Array de objetos con la transcripción completa
  "summary": "...",        // Resumen de la reunión generado por IA  
  "keyPoints": [...]       // Array de puntos clave principales
}
```

## 🎯 Campo: `transcription` (Array)

Cada elemento del array de transcripción contiene:

```json
{
  "speaker": "A",              // ID del hablante (A, B, C, D, E...)
  "time": "88:27 - 88:52",    // Tiempo en formato MM:SS
  "text": "...",              // Texto transcrito
  "avatar": "A",              // Avatar del hablante
  "start": 5307.86,           // Tiempo inicio en segundos (decimal)
  "end": 5332.09,             // Tiempo fin en segundos (decimal)
  "originalStart": 5307860,   // Tiempo inicio en milisegundos (integer)
  "originalEnd": 5332090,     // Tiempo fin en milisegundos (integer)
  "wasInMilliseconds": true   // Bandera de conversión de tiempo
}
```

## 📝 Campo: `summary` (String)

Contiene un resumen generado por IA de toda la reunión:

```json
"summary": "La reunión se centró en la discusión sobre los procesos de la clínica, incluyendo el manejo de expedientes, sistemas de información, y la necesidad de optimizar los procesos administrativos..."
```

## 🔑 Campo: `keyPoints` (Array)

Array de strings con los puntos clave más importantes:

```json
"keyPoints": [
  "La clínica ofrece servicios a empresas y particulares...",
  "Se ha identificado un aumento en la carga de trabajo...",
  "La frecuencia de exámenes médicos varía según...",
  // ... más puntos clave
]
```

## ✨ Características BNI

### 🔓 Sin Encriptación
- El archivo es **JSON puro** - legible directamente
- No requiere desencriptación previa
- Formato estándar que cualquier editor puede abrir

### 🗄️ Almacenamiento Temporal
- Se guarda en `storage/app/` local (no Google Drive)
- Archivo accesible inmediatamente
- Perfecto para descargas automáticas

### 📱 Auto-Descarga
- Se descarga automáticamente al crear
- Filename: `reunion_temp_{ID}.ju`
- Content-Type: `application/json`

## 📊 Métricas de Ejemplo

**Reunión "prueba de BNI" (ID: 15)**:
- **Segmentos de transcripción**: ~470 elementos
- **Duración**: ~105 minutos  
- **Hablantes**: 5 personas (A, B, C, D, E)
- **Puntos clave**: 10 elementos principales
- **Tamaño total**: 193,972 bytes

## 🔗 URLs de Acceso

**Descarga directa**:
```
GET /api/transcriptions-temp/{id}/download-ju
Authorization: Bearer {token}
```

**Ejemplo real**:
```
GET /api/transcriptions-temp/15/download-ju
```

## 🛠️ Diferencias vs Usuarios Regulares

| Característica | Usuario BNI | Usuario Regular |
|---------------|-------------|-----------------|
| **Encriptación** | ❌ Sin encriptar | ✅ Encriptado |
| **Almacenamiento** | 🗄️ Temporal local | ☁️ Google Drive |
| **Descarga** | 🚀 Auto-descarga | 📋 Manual |
| **Formato** | 📄 JSON puro | 🔒 Binario encriptado |
| **Acceso** | ♾️ Ilimitado | 📊 Con límites |

## 🎯 Uso Recomendado

1. **Para desarrolladores**:
   - Parsear directamente como JSON
   - Extraer transcripción, resumen y puntos clave
   - Integrar fácilmente en aplicaciones

2. **Para usuarios finales**:
   - Abrir con cualquier editor de texto
   - Buscar contenido específico
   - Importar a otras herramientas

3. **Para análisis**:
   - Procesar timestamps automáticamente  
   - Extraer estadísticas de participación
   - Generar reportes personalizados
