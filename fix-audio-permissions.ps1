# Script para solucionar problemas de permisos con audio

Write-Host "Verificando permisos y configuraciones..." -ForegroundColor Cyan

# 1. Desbloquear ffmpeg
Write-Host "`n1. Desbloqueando FFmpeg..." -ForegroundColor Yellow
$ffmpegPath = (Get-Command ffmpeg -ErrorAction SilentlyContinue).Source
if ($ffmpegPath) {
    Unblock-File -Path $ffmpegPath -ErrorAction SilentlyContinue
    Write-Host "   FFmpeg desbloqueado: $ffmpegPath" -ForegroundColor Green
} else {
    Write-Host "   FFmpeg no encontrado en PATH" -ForegroundColor Red
}

# 2. Verificar carpeta temporal
Write-Host "`n2. Verificando carpeta temporal..." -ForegroundColor Yellow
$tempPath = [System.IO.Path]::GetTempPath()
Write-Host "   Carpeta temporal: $tempPath" -ForegroundColor Cyan

# 3. Crear carpeta temporal local para el proyecto
$projectTemp = Join-Path $PSScriptRoot "temp"
if (-not (Test-Path $projectTemp)) {
    New-Item -Path $projectTemp -ItemType Directory -Force | Out-Null
    Write-Host "   Carpeta temporal del proyecto creada: $projectTemp" -ForegroundColor Green
} else {
    Write-Host "   Carpeta temporal del proyecto existe: $projectTemp" -ForegroundColor Green
}

# 4. Configurar variable de entorno para usar carpeta local
Write-Host "`n3. Configurando variables de entorno..." -ForegroundColor Yellow
[System.Environment]::SetEnvironmentVariable("TEMP", $projectTemp, "Process")
[System.Environment]::SetEnvironmentVariable("TMP", $projectTemp, "Process")
Write-Host "   Variables TEMP y TMP configuradas para esta sesion" -ForegroundColor Green

Write-Host "`n=== Solucion aplicada ===" -ForegroundColor Green
Write-Host "Los archivos temporales ahora se crearan en: $projectTemp" -ForegroundColor Cyan
Write-Host "`nEjecuta tu proceso de audio nuevamente en ESTA MISMA terminal" -ForegroundColor Yellow
