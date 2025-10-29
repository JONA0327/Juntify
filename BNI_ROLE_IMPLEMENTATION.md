# Implementación del Rol BNI

## Descripción
Se ha implementado un nuevo rol llamado **BNI** que tiene un comportamiento especial para el almacenamiento de reuniones y archivos de transcripción.

## Comportamiento del Rol BNI

### 🗂️ Almacenamiento
- **Usuarios BNI**: Los audios y transcripciones se guardan en la tabla `transcriptions_temp` (almacenamiento temporal local)
- **Otros roles**: Mantienen el comportamiento actual (Google Drive)

### 🔐 Encriptación
- **Usuarios BNI**: Los archivos `.ju` se guardan **SIN ENCRIPTACIÓN** (JSON plano)
- **Otros roles**: Mantienen la encriptación actual con `Laravel Crypt`

## Archivos Modificados

### 1. `app/Http/Controllers/DriveController.php`

#### Método `saveResults()` - Línea ~1150
```php
// Usuarios con rol BNI SIEMPRE usan almacenamiento temporal (transcriptions_temp)
if ($user->roles === 'BNI') {
    Log::info('BNI user: Forcing temporary storage instead of Drive', ['user_id' => $user->id]);
    return $this->storeTemporaryResult(/* ... */);
}
```

#### Método `storeTemporaryResult()` - Línea ~1920
```php
// Para usuarios con rol BNI, no encriptar el archivo .ju
if ($user->roles === 'BNI') {
    $encrypted = json_encode($payload);
    Log::info('BNI user: .ju file saved without encryption', ['user_id' => $user->id]);
} else {
    $encrypted = Crypt::encryptString(json_encode($payload));
}
```

#### Método normal de Drive - Línea ~1460
```php
// Para usuarios con rol BNI, no encriptar el archivo .ju
if ($user->roles === 'BNI') {
    $encrypted = json_encode($payload);
    Log::info('BNI user: Drive .ju file saved without encryption', ['user_id' => $user->id]);
} else {
    $encrypted = Crypt::encryptString(json_encode($payload));
}
```

## Compatibilidad

### ✅ Lectura de Archivos
El sistema ya tenía soporte para archivos sin encriptar en `app/Traits/MeetingContentParsing.php`:

```php
// 1) Si el contenido ya es JSON válido (sin encriptar)
$json_data = json_decode($content, true);
if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
    Log::info('decryptJuFile: Content is already valid JSON (unencrypted)');
    return [
        'data' => $this->extractMeetingDataFromJson($json_data),
        'raw' => $json_data,
        'needs_encryption' => true,
    ];
}
```

### ✅ Backwards Compatibility
- Los archivos encriptados existentes siguen funcionando normalmente
- Los usuarios con otros roles no se ven afectados
- El sistema detecta automáticamente si un archivo está encriptado o no

## Flujo de Funcionamiento

### Para Usuarios BNI:
1. **Grabación/Upload** → Audio se procesa normalmente
2. **Guardado** → Forzado a `transcriptions_temp` (no Drive)
3. **Archivo .ju** → Guardado sin encriptación (JSON plano)
4. **Lectura** → Sistema detecta JSON plano y lo procesa directamente

### Para Otros Roles:
1. **Comportamiento normal** → Sin cambios
2. **Google Drive** → Mantiene integración actual
3. **Encriptación** → Mantiene encriptación con `Laravel Crypt`

## Logs y Debug

### Logs Agregados:
```php
Log::info('BNI user: Forcing temporary storage instead of Drive', ['user_id' => $user->id]);
Log::info('BNI user: .ju file saved without encryption', ['user_id' => $user->id]);
Log::info('BNI user: Drive .ju file saved without encryption', ['user_id' => $user->id]);
```

## Usuario de Prueba

Se ha creado un usuario de prueba:
- **Email**: `bni.test@juntify.com`
- **Password**: `password`
- **Rol**: `BNI`
- **ID**: `2d8488d8-58bc-46bf-9d39-43d633a16b80`

## Scripts de Verificación

### `test_bni_role.php`
- Crea usuario BNI de prueba
- Verifica la lógica de encriptación
- Confirma que la tabla `transcriptions_temp` existe

### `verify_bni_implementation.php`
- Lista todos los usuarios BNI
- Verifica archivos temporales existentes
- Confirma comportamiento por rol

## Pruebas

### Para probar la implementación:
1. Login con usuario BNI: `bni.test@juntify.com` / `password`
2. Grabar o subir audio en una reunión
3. Verificar que:
   - El audio se guarda en `storage/app/temp_audio/[user_id]/`
   - El .ju se guarda en `storage/app/temp_transcriptions/[user_id]/`
   - El archivo .ju contiene JSON plano (no encriptado)
   - Los datos aparecen en la tabla `transcriptions_temp`

### Comandos de verificación:
```bash
php test_bni_role.php
php verify_bni_implementation.php
```

## Consideraciones de Seguridad

⚠️ **IMPORTANTE**: Los usuarios BNI tienen archivos sin encriptar. Asegurar que:
- Solo usuarios autorizados tengan rol BNI
- Los archivos temporales tengan permisos adecuados
- Se implemente limpieza automática si es necesario

## Estructura de Datos

### Archivo .ju para BNI (sin encriptar):
```json
{
  "segments": [
    {
      "speaker": "Participante 1",
      "text": "Texto de la transcripción...",
      "start": 0,
      "end": 10.5
    }
  ],
  "summary": "Resumen de la reunión...",
  "keyPoints": [
    "Punto clave 1",
    "Punto clave 2"
  ]
}
```

### Tabla `transcriptions_temp`:
- `user_id`: ID del usuario BNI
- `title`: Nombre de la reunión
- `audio_path`: Ruta del audio en almacenamiento local
- `transcription_path`: Ruta del .ju en almacenamiento local
- `expires_at`: Fecha de expiración
- `metadata`: Información adicional

---

**Implementado por**: GitHub Copilot  
**Fecha**: 29 de octubre de 2025  
**Estado**: ✅ Completado y probado
