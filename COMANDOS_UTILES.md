# 🛠️ Comandos Útiles - Juntify

## 🔍 Diagnóstico y Verificación

```bash
# Verificar configuración de Google
php artisan google:check

# Verificar tokens de Google almacenados
php artisan google:tokens

# Ver información general del sistema
php artisan about

# Ver configuración actual
php artisan config:show

# Verificar estado de la aplicación
php artisan route:list | grep google
```

## 🧹 Limpieza de Cache

```bash
# Limpiar todos los caches
php artisan optimize:clear

# Limpiar caches individuales
php artisan cache:clear           # Cache de aplicación
php artisan config:clear          # Cache de configuración
php artisan route:clear           # Cache de rutas
php artisan view:clear            # Cache de vistas
php artisan event:clear           # Cache de eventos
```

## ⚡ Optimización para Producción

```bash
# Optimizar aplicación
php artisan optimize

# Cachear configuración
php artisan config:cache

# Cachear rutas
php artisan route:cache

# Cachear vistas
php artisan view:cache

# Optimizar Composer
composer dump-autoload --optimize
```

## 🗄️ Base de Datos

```bash
# Ejecutar migraciones
php artisan migrate

# Rollback de migraciones
php artisan migrate:rollback

# Refrecar base de datos (CUIDADO: Borra datos)
php artisan migrate:fresh

# Ejecutar seeders
php artisan db:seed

# Crear nueva migración
php artisan make:migration nombre_de_la_migracion

# Ver estado de migraciones
php artisan migrate:status
```

## 🐍 Gestión del Entorno Python

### Windows
```powershell
# Activar entorno virtual
.\python_env\Scripts\Activate.ps1

# Desactivar entorno virtual
deactivate

# Actualizar dependencias
pip install --upgrade -r requirements.txt

# Ver dependencias instaladas
pip list
```

### Linux
```bash
# Activar entorno virtual
source python_env/bin/activate

# Desactivar entorno virtual
deactivate

# Actualizar dependencias
pip install --upgrade -r requirements.txt

# Ver dependencias instaladas
pip list
```

## 🌐 Servidor de Desarrollo

```bash
# Iniciar servidor Laravel
php artisan serve

# Iniciar servidor con host y puerto específicos
php artisan serve --host=0.0.0.0 --port=8080

# Compilar assets para desarrollo
npm run dev

# Compilar assets para producción
npm run build

# Watch mode para desarrollo
npm run dev -- --watch
```

## 🔐 Gestión de Usuarios y Permisos

```bash
# Crear usuario administrador
php artisan make:command CreateAdminUser

# Listar usuarios
php artisan tinker
>>> App\Models\User::all();

# Resetear password de usuario
php artisan tinker
>>> $user = App\Models\User::where('email', 'usuario@email.com')->first();
>>> $user->password = Hash::make('nueva_password');
>>> $user->save();
```

## 📊 Logs y Monitoreo

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Ver logs de una fecha específica
cat storage/logs/laravel-2026-02-02.log

# Limpiar logs antiguos
find storage/logs -name "laravel-*.log" -mtime +30 -delete
```

## 🔄 Tareas de Mantenimiento

```bash
# Limpiar archivos temporales
php artisan schedule:run

# Verificar tareas programadas
php artisan schedule:list

# Ejecutar una tarea específica
php artisan queue:work

# Ver trabajos fallidos
php artisan queue:failed

# Reintentar trabajos fallidos
php artisan queue:retry all
```

## 🛠️ Resolución de Problemas

### Problemas de Permisos (Linux)
```bash
# Restaurar permisos correctos
sudo chown -R www-data:www-data /var/www/juntify
sudo chmod -R 755 /var/www/juntify
sudo chmod -R 775 /var/www/juntify/storage
sudo chmod -R 775 /var/www/juntify/bootstrap/cache
```

### Problemas de Permisos (Windows)
```powershell
# Dar permisos completos a carpetas críticas
icacls storage /grant Everyone:(OI)(CI)F /T
icacls bootstrap\cache /grant Everyone:(OI)(CI)F /T
```

### Regenerar Archivo de Configuración
```bash
# Si hay problemas con .env
cp .env.example .env
php artisan key:generate
# Configurar variables manualmente
```

### Problemas con Google Drive
```bash
# Verificar configuración
php artisan google:check

# Ver tokens actuales
php artisan google:tokens

# Limpiar tokens corruptos (si es necesario)
php artisan tinker
>>> App\Models\GoogleToken::truncate();
>>> App\Models\OrganizationGoogleToken::truncate();
```

## 📋 Comandos Personalizados Útiles

```bash
# Crear comando personalizado
php artisan make:command NombreComando

# Listar comandos disponibles
php artisan list

# Ayuda de un comando específico
php artisan help google:check
```

## 🔍 Debugging

```bash
# Activar modo debug (solo desarrollo)
# En .env: APP_DEBUG=true

# Ver información de la aplicación
php artisan about

# Ver variables de entorno
php artisan tinker
>>> dd(config('services.google'));

# Verificar conexión a base de datos
php artisan tinker
>>> DB::connection()->getPdo();
```

## 💾 Backup y Restauración

```bash
# Crear backup de base de datos
mysqldump -u username -p juntify_new > backup_$(date +%Y%m%d).sql

# Restaurar desde backup
mysql -u username -p juntify_new < backup_20260202.sql

# Backup de archivos importantes
tar -czf juntify_backup_$(date +%Y%m%d).tar.gz .env storage/ public/
```

---

*Comandos útiles para Juntify - Febrero 2026*