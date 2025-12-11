# Script de configuración rápida para migración - Windows PowerShell
Write-Host "🔧 Configuración Rápida de Migración - Juntify" -ForegroundColor Yellow
Write-Host "==============================================`n" -ForegroundColor Yellow

# Verificar si el archivo .env existe
if (-not (Test-Path ".env")) {
    Write-Host "❌ Archivo .env no encontrado. Copia .env.example a .env primero." -ForegroundColor Red
    exit 1
}

Write-Host "Por favor, proporciona la información de tu base de datos antigua:`n"

# Solicitar información de BD antigua
$OLD_HOST = Read-Host "🌐 Host de BD antigua (default: 127.0.0.1)"
if ([string]::IsNullOrWhiteSpace($OLD_HOST)) { $OLD_HOST = "127.0.0.1" }

$OLD_PORT = Read-Host "🔌 Puerto de BD antigua (default: 3306)"
if ([string]::IsNullOrWhiteSpace($OLD_PORT)) { $OLD_PORT = "3306" }

$OLD_DATABASE = Read-Host "🗄️ Nombre de BD antigua"

$OLD_USERNAME = Read-Host "👤 Usuario de BD antigua (default: root)"
if ([string]::IsNullOrWhiteSpace($OLD_USERNAME)) { $OLD_USERNAME = "root" }

$OLD_PASSWORD = Read-Host "🔒 Password de BD antigua (presiona Enter si no tiene)" -AsSecureString
$OLD_PASSWORD = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($OLD_PASSWORD))

# Verificar si ya existen las variables en .env
$envContent = Get-Content ".env" -ErrorAction SilentlyContinue
if ($envContent -match "OLD_LOCAL_DB_HOST") {
    Write-Host "`n⚠️ Las variables de migración ya existen en .env" -ForegroundColor Yellow
    $overwrite = Read-Host "¿Sobreescribir? (y/N)"
    if ($overwrite -notmatch "^[Yy]$") {
        Write-Host "Configuración cancelada." -ForegroundColor Yellow
        exit 0
    }
    # Remover variables existentes
    $newContent = $envContent | Where-Object { $_ -notmatch "OLD_LOCAL_DB_" -and $_ -notmatch "# .*MIGRACIÓN" -and $_ -notmatch "# =+" }
    $newContent | Set-Content ".env"
}

# Añadir variables al .env
$migrationConfig = @"

# ========================================
# CONFIGURACIÓN PARA MIGRACIÓN DE DATOS
# ========================================
OLD_LOCAL_DB_HOST=$OLD_HOST
OLD_LOCAL_DB_PORT=$OLD_PORT
OLD_LOCAL_DB_DATABASE=$OLD_DATABASE
OLD_LOCAL_DB_USERNAME=$OLD_USERNAME
OLD_LOCAL_DB_PASSWORD=$OLD_PASSWORD
OLD_LOCAL_DB_SOCKET=
"@

Add-Content ".env" $migrationConfig

Write-Host "`n✅ Configuración añadida al archivo .env" -ForegroundColor Green
Write-Host "`n🧪 Probando conexión a BD antigua..."

# Probar la conexión
try {
    $result = & php artisan migrate:old-data --dry-run 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Conexión exitosa!" -ForegroundColor Green
        Write-Host "`n🚀 Comandos disponibles:" -ForegroundColor Cyan
        Write-Host "   php artisan migrate:old-data --dry-run    # Ver qué se migraría"
        Write-Host "   php artisan migrate:old-data              # Migrar todas las tablas"
        Write-Host "   php artisan migrate:users --dry-run       # Ver usuarios a migrar"
        Write-Host "   php artisan verify:migration              # Verificar migración"
        Write-Host "`n📖 Lee MIGRATION_GUIDE.md para más detalles" -ForegroundColor Blue
    } else {
        Write-Host "❌ Error de conexión. Verifica los datos ingresados." -ForegroundColor Red
        Write-Host "💡 Puedes editar manualmente las variables OLD_LOCAL_DB_* en .env" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ Error probando la conexión: $_" -ForegroundColor Red
}

Write-Host "`n🎉 Configuración completada!" -ForegroundColor Green
