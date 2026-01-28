# Script para descargar ffmpeg portable en el proyecto (sin permisos de admin)

Write-Host "=== INSTALANDO FFMPEG PORTABLE ===" -ForegroundColor Cyan

$projectDir = $PSScriptRoot
$ffmpegDir = Join-Path $projectDir "ffmpeg-portable"
$ffmpegExe = Join-Path $ffmpegDir "bin\ffmpeg.exe"

# 1. Crear directorio si no existe
if (-not (Test-Path $ffmpegDir)) {
    New-Item -Path $ffmpegDir -ItemType Directory -Force | Out-Null
}

# 2. Verificar si ya existe
if (Test-Path $ffmpegExe) {
    Write-Host "FFmpeg portable ya existe en: $ffmpegExe" -ForegroundColor Yellow
    Write-Host "Eliminando version antigua..." -ForegroundColor Yellow
    Remove-Item $ffmpegDir -Recurse -Force
}

# 3. Descargar ffmpeg portable
Write-Host "Descargando ffmpeg portable..." -ForegroundColor Yellow
$ffmpegUrl = "https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-win64-gpl.zip"
$zipFile = Join-Path $projectDir "ffmpeg.zip"

try {
    Invoke-WebRequest -Uri $ffmpegUrl -OutFile $zipFile -UseBasicParsing
    Write-Host "Descarga completada" -ForegroundColor Green
} catch {
    Write-Host "Error al descargar: $_" -ForegroundColor Red
    exit 1
}

# 4. Extraer
Write-Host "Extrayendo archivos..." -ForegroundColor Yellow
Expand-Archive -Path $zipFile -DestinationPath $ffmpegDir -Force

# Mover archivos de la subcarpeta al directorio principal
$subFolder = Get-ChildItem $ffmpegDir -Directory | Select-Object -First 1
if ($subFolder) {
    Get-ChildItem $subFolder.FullName | Move-Item -Destination $ffmpegDir -Force
    Remove-Item $subFolder.FullName -Recurse -Force
}

# Limpiar zip
Remove-Item $zipFile -Force

# 5. Verificar
if (Test-Path $ffmpegExe) {
    Write-Host "FFmpeg instalado en: $ffmpegExe" -ForegroundColor Green
    
    # 6. Configurar variable de entorno para Laravel
    Write-Host "Configurando .env..." -ForegroundColor Yellow
    $envFile = Join-Path $projectDir ".env"
    $ffmpegPath = $ffmpegExe -replace '\\', '/'
    
    $envContent = Get-Content $envFile -Raw
    if ($envContent -match 'FFMPEG_BIN=') {
        $envContent = $envContent -replace 'FFMPEG_BIN=.*', "FFMPEG_BIN=$ffmpegPath"
    } else {
        $envContent += "`nFFMPEG_BIN=$ffmpegPath"
    }
    Set-Content -Path $envFile -Value $envContent -NoNewline
    
    Write-Host "Variable FFMPEG_BIN configurada en .env" -ForegroundColor Green
    
    # 7. Probar ffmpeg
    Write-Host "Probando ffmpeg..." -ForegroundColor Yellow
    & $ffmpegExe -version 2>&1 | Select-Object -First 1
    
    Write-Host "`n=== INSTALACION EXITOSA ===" -ForegroundColor Green
    Write-Host "Ahora intenta procesar audio nuevamente" -ForegroundColor Cyan
} else {
    Write-Host "Error: ffmpeg no se encontro despues de la instalacion" -ForegroundColor Red
}
