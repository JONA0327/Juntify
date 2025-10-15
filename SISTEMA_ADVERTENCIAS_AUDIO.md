# 🔊 Sistema de Advertencias de Tiempo con Audio - Juntify

## 📋 Resumen de la Implementación

Se ha implementado un sistema completo de advertencias de tiempo límite para las grabaciones con notificaciones sonoras y visuales mejoradas.

## 🎯 Características Implementadas

### ✅ 1. Sistema de Audio de Notificaciones
- **Ubicación**: `/public/audio/notifications/`
- **Archivos requeridos**: 
  - `time-warning.mp3` - Sonido de advertencia (5 min antes del límite)
  - `time-limit-reached.mp3` - Sonido cuando se alcanza el límite
  - `meeting-start.mp3` - Sonido al iniciar reunión (futuro)
  - `meeting-end.mp3` - Sonido al finalizar reunión (futuro)

### ✅ 2. Sistema de Fallback con Web Audio API
Si los archivos MP3 no están disponibles, el sistema genera beeps automáticamente:
- **Advertencia**: Beep doble de 800Hz (2 beeps cortos)
- **Límite alcanzado**: Beep triple de 600Hz (3 beeps rápidos)

### ✅ 3. Advertencias Visuales Mejoradas
- Notificaciones con animación de pulso roja
- Timer cambia a color rojo cuando se acerca al límite
- Notificaciones permanecen 8 segundos (vs 5 segundos normales)
- Estilo visual más llamativo con gradientes y sombras

### ✅ 4. Logging y Debug Mejorado
- Logging cada 30 segundos del progreso del timer
- Información detallada de límites y umbrales
- Verificación automática de archivos de audio
- Función de debug: `debugForceTimeWarning()` en consola

### ✅ 5. Aplicación Universal
- Funciona tanto en "Audio Recorder" como en "Meeting Recorder"
- Misma lógica para ambos modos de grabación
- Límites dinámicos según el plan del usuario

## 🔧 Herramientas Incluidas

### 1. Generador de Audio (`/audio/notifications/generator.html`)
Página web interactiva para generar los archivos de audio necesarios:
- Controles para frecuencia y duración
- Previsualización de sonidos
- Descarga automática de archivos WAV/MP3
- Generación de todos los archivos con un clic

### 2. Script de Consola (`/audio/notifications/generate-beeps.js`)
Script JavaScript para generar archivos desde la consola del navegador.

### 3. Botón de Prueba (Desarrollo)
En entornos de desarrollo (localhost/laragon), aparece un botón flotante para probar:
- ⚠️ Sonido de advertencia
- 🛑 Sonido de límite alcanzado

## 🚀 Cómo Usar

### Para Generar Archivos de Audio:
1. Abrir `/audio/notifications/generator.html` en el navegador
2. Ajustar frecuencia y duración si es necesario
3. Hacer clic en "📦 Generar Todos los Audios"
4. Convertir los archivos WAV a MP3 si es necesario
5. Colocar los archivos en `/public/audio/notifications/`

### Para Probar el Sistema:
1. Ir a `/new-meeting`
2. En desarrollo: usar el botón flotante de prueba
3. En producción: usar la consola: `debugForceTimeWarning()`
4. Iniciar una grabación y esperar a que se active la advertencia

## 🐛 Debugging

### Si la advertencia no aparece:
1. Verificar en consola los logs cada 30 segundos
2. Comprobar que `MAX_DURATION_MS` y `WARN_BEFORE_MINUTES` están correctos
3. Usar `debugForceTimeWarning()` para probar manualmente
4. Verificar que `limitWarningShown` se resetea correctamente

### Variables clave a monitorear:
```javascript
// En consola del navegador
console.log('MAX_DURATION_MS:', MAX_DURATION_MS);
console.log('WARN_BEFORE_MINUTES:', WARN_BEFORE_MINUTES);
console.log('limitWarningShown:', limitWarningShown);
console.log('PLAN_LIMITS:', PLAN_LIMITS);
```

## 📊 Configuración por Planes

| Plan | Duración Máx. | Advertencia | Reuniones/Mes |
|------|---------------|-------------|---------------|
| **FREE** | 30 min | 5 min antes | 5 |
| **BASIC** | 60 min | 5 min antes | 25 |
| **NEGOCIOS** | 120 min | 5 min antes | 35 |
| **ENTERPRISE** | 120 min | 5 min antes | 50 |
| **FOUNDER/DEV** | 120 min | 5 min antes | ∞ |

## 🔄 Flujo de Funcionamiento

1. **Inicialización**: Se cargan los límites del plan del usuario
2. **Timer activo**: Se ejecuta cada 100ms verificando el tiempo transcurrido
3. **Umbral de advertencia**: Al llegar a (tiempo_máximo - 5_minutos):
   - Se reproduce el sonido de advertencia
   - Se muestra notificación visual mejorada
   - Se marca `limitWarningShown = true`
4. **Límite alcanzado**: Al llegar al tiempo máximo:
   - Se reproduce sonido de límite alcanzado
   - Se detiene automáticamente la grabación

## ⚠️ Notas Importantes

- Los archivos de audio deben estar en formato MP3 para máxima compatibilidad
- El sistema de fallback Web Audio API funciona en todos los navegadores modernos
- Las advertencias solo se muestran una vez por sesión de grabación
- El timer se resetea al iniciar una nueva grabación

## 🎵 Personalización de Sonidos

Para personalizar los sonidos:
1. Editar frecuencias en `/audio/notifications/generator.html`
2. O reemplazar los archivos MP3 con sonidos personalizados
3. Mantener duración entre 1-3 segundos para mejor UX
