# 📋 Guía de Migración de Datos - Juntify

## ✅ SISTEMA COMPLETADO Y LISTO PARA USAR

Esta guía te ayudará a migrar datos de tu base de datos antigua local a la nueva base de datos de producción.

### 📊 Estado Final del Análisis

- **BD Antigua**: 71 tablas, 1,481 registros para migrar
- **BD Nueva**: 43 tablas unificadas  
- **Migraciones Directas**: 35 tablas (1,408 registros)
- **Transformaciones Especiales**: 4 tablas → 2 tablas destino (73 registros)
- **Tiempo Estimado**: 1-5 minutos

### 🚀 Scripts Disponibles

1. **Script Maestro**: `php scripts/migration_master.php` - Menú interactivo completo
2. **Análisis de Tablas**: `php artisan analyze:tables` - Ver qué se puede migrar
3. **Cambiar Configuración**: `php scripts/switch_db_config.php [local|production]`
4. **Migración de Datos**: `php artisan migrate:old-data [--dry-run]`
5. **Verificación**: `php artisan verify:migration`

## 🔧 Configuración Inicial

### 1. Variables de Entorno

Añade estas variables a tu archivo `.env`:

```bash
# Base de datos antigua local (origen)
OLD_LOCAL_DB_HOST=127.0.0.1
OLD_LOCAL_DB_PORT=3306
OLD_LOCAL_DB_DATABASE=juntify_old
OLD_LOCAL_DB_USERNAME=root
OLD_LOCAL_DB_PASSWORD=

# Opcional: socket para conexión local
OLD_LOCAL_DB_SOCKET=
```

### 2. Verificar Conexiones

Antes de migrar, asegúrate de que ambas bases de datos sean accesibles:

```bash
php artisan migrate:old-data --dry-run
```

## 🚀 Comandos de Migración

### Migración General de Datos

```bash
# Ver qué se migraría sin ejecutar
php artisan migrate:old-data --dry-run

# Migrar todas las tablas
php artisan migrate:old-data

# Migrar solo una tabla específica
php artisan migrate:old-data --table=users

# Cambiar tamaño del batch para tablas grandes
php artisan migrate:old-data --batch-size=500
```

### Migración Específica de Usuarios

Para usuarios con transformaciones avanzadas (IDs a UUIDs, passwords, roles):

```bash
# Ver usuarios que se migrarían
php artisan migrate:users --dry-run

# Migrar usuarios generando nuevos UUIDs
php artisan migrate:users --generate-uuids

# Migrar con password por defecto personalizada
php artisan migrate:users --default-password=nuevapassword

# Migrar usuarios con todas las opciones
php artisan migrate:users --generate-uuids --default-password=temporal123
```

## 📊 Mapeo de Tablas

### Migración Directa (Mismo nombre y estructura)

| Categoría | Tabla Antigua | Tabla Nueva | Estado |
|-----------|---------------|-------------|--------|
| **Usuarios y Permisos** | `users` | `users` | ✅ Migración con UUID |
| | `permissions` | `permissions` | ✅ Directa |
| | `notifications` | `notifications` | ✅ Directa |
| | `contacts` | `contacts` | ✅ Directa |
| **Organización** | `organizations` | `organizations` | ✅ Directa |
| | `groups` | `groups` | ✅ Directa |
| | `organization_user` | `organization_user` | ✅ Directa |
| | `group_user` | `group_user` | ✅ Directa |
| **Reuniones** | `transcriptions_laravel` | `transcriptions_laravel` | ✅ Directa |
| | `meeting_content_containers` | `meeting_content_containers` | ✅ Directa |
| | `shared_meetings` | `shared_meetings` | ✅ Directa |
| **Tareas** | `tasks` | `tasks` | ✅ Directa |
| | `tasks_laravel` | `tasks_laravel` | ✅ Directa |
| **Archivos** | `google_tokens` | `google_tokens` | ✅ Directa |
| | `folders` | `folders` | ✅ Directa |
| **Planes** | `plans` | `plans` | ✅ Directa |
| | `user_subscriptions` | `user_subscriptions` | ✅ Directa |
| | `payments` | `payments` | ✅ Directa |

### Transformaciones Especiales (Consolidación)

| Tabla Antigua | Tabla Nueva | Transformación |
|---------------|-------------|----------------|
| `chats` | `conversations` | 🔄 type='chat' |
| `ai_chat_sessions` | `conversations` | 🔄 type='ai_assistant' |
| `chat_messages` | `conversation_messages` | 🔄 chat_id → conversation_id |
| `ai_chat_messages` | `conversation_messages` | 🔄 session_id → conversation_id |

## 🔄 Transformaciones Automáticas

### Usuarios (users)
- **IDs**: Conversión automática de IDs numéricos a UUIDs
- **Passwords**: Verificación de hash existente o generación nueva
- **Roles**: Mapeo automático de roles antiguos
- **Fechas**: Normalización de timestamps
- **Campos nuevos**: `is_role_protected`, `plan_code` con valores por defecto

### Consolidación de Conversaciones
El sistema consolida dos tipos de conversaciones en una sola tabla:

#### Chats de Usuarios (`chats` → `conversations`)
```sql
INSERT INTO conversations (id, type, user_one_id, user_two_id, ...)
SELECT id, 'chat', user_one_id, user_two_id, ...
FROM chats;
```

#### Sesiones de IA (`ai_chat_sessions` → `conversations`)
```sql
INSERT INTO conversations (id, type, username, title, context_data, ...)
SELECT (id + offset), 'ai_assistant', username, title, context_data, ...
FROM ai_chat_sessions;
```

### Consolidación de Mensajes
Unifica mensajes de chat y IA en una sola tabla:

#### Mensajes de Chat (`chat_messages` → `conversation_messages`)
- `body` → `content`
- `chat_id` → `conversation_id`
- `sender_id` conservado
- `legacy_chat_message_id` para referencia

#### Mensajes de IA (`ai_chat_messages` → `conversation_messages`)
- `content` → `content`
- `session_id` → `conversation_id` (con mapeo)
- `role` conservado
- `legacy_ai_message_id` para referencia

### Datos Generales
- **Timestamps**: Conversión automática de formatos
- **Valores nulos**: Limpieza de strings vacíos
- **JSON**: Preservación de campos JSON como `context_data`, `metadata`
- **Relaciones**: Mantenimiento de integridad referencial

## 📁 Archivos Generados

### Mapeo de IDs de Usuarios
Se guarda en: `storage/app/user_id_mapping.json`

```json
{
    "123": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "124": "6ba7b810-9dad-11d1-80b4-00c04fd430c8"
}
```

Este archivo es útil para migrar tablas relacionadas que referencien IDs de usuarios.

## ⚠️ Consideraciones Importantes

### Antes de Migrar
1. **Backup**: Siempre haz backup de ambas bases de datos
2. **Dry-run**: Ejecuta `--dry-run` para verificar qué se migrará
3. **Conexiones**: Verifica que ambas BDs sean accesibles
4. **Espacio**: Asegúrate de tener suficiente espacio en disco

### Durante la Migración
- **Performance**: Usa `--batch-size` para tablas muy grandes
- **Memoria**: Monitorea el uso de memoria para datasets grandes
- **Logs**: Los errores se guardan en `storage/logs/laravel.log`

### Después de Migrar
- **Verificación**: Compara conteos de registros entre BDs
- **Índices**: Verifica que los índices estén correctos
- **Relaciones**: Prueba que las foreign keys funcionen
- **Aplicación**: Prueba la funcionalidad de la aplicación

## 🛠️ Personalización

### Añadir Nuevas Tablas
Edita el array `$tableMappings` en `MigrateOldDataCommand.php`:

```php
protected $tableMappings = [
    'old_table_name' => 'new_table_name',
    'mi_tabla_antigua' => 'mi_tabla_nueva',
    // ...
];
```

### Transformaciones Personalizadas
Modifica el método `transformRecord()` en `MigrateOldDataCommand.php`:

```php
protected function transformRecord(array $record): array
{
    // Tus transformaciones personalizadas aquí
    
    if (isset($record['old_field'])) {
        $record['new_field'] = $this->customTransformation($record['old_field']);
        unset($record['old_field']);
    }
    
    return $record;
}
```

## 🔍 Solución de Problemas

### Error de Conexión
```bash
❌ Error de conexión: SQLSTATE[HY000] [2002] Connection refused
```
**Solución**: Verifica las variables `OLD_LOCAL_DB_*` en el `.env`

### Tabla no Existe
```bash
⚠️ Tabla 'users' no existe en BD antigua
```
**Solución**: Verifica el nombre de la tabla o añádela al mapeo

### Registros Duplicados
```bash
⚠️ Usuario ya existe: user@example.com
```
**Solución**: El comando evita duplicados automáticamente

### Memoria Insuficiente
```bash
PHP Fatal error: Allowed memory size exhausted
```
**Solución**: Reduce el `--batch-size` o aumenta `memory_limit` en PHP

## 📞 Soporte

Si encuentras problemas:
1. Revisa los logs en `storage/logs/laravel.log`
2. Usa `--dry-run` para diagnosticar problemas
3. Ejecuta migraciones tabla por tabla con `--table=`
4. Verifica la estructura de ambas bases de datos

---

🎉 **¡Migración completada exitosamente!** Tu sistema Juntify ahora tiene todos los datos migrados y está listo para producción.
