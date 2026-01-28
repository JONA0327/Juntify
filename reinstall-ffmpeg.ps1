# Script para descargar e instalar ffmpeg sin bloqueos

Write-Host "=== REINSTALACION DE FFMPEG ===" -ForegroundColor Cyan

# 1. Desinstalar ffmpeg actual si existe
Write-Host "1. Desinstalando ffmpeg actual..." -ForegroundColor Yellow
choco uninstall ffmpeg -y
Write-Host "   FFmpeg desinstalado" -ForegroundColor Green

# 2. Limpiar cache de chocolatey
Write-Host "2. Limpiando cache..." -ForegroundColor Yellow
Remove-Item "C:\ProgramData\chocolatey\lib\ffmpeg" -Recurse -Force -ErrorAction SilentlyContinue
Write-Host "   Cache limpiado" -ForegroundColor Green

# 3. Reinstalar ffmpeg
Write-Host "3. Reinstalando ffmpeg..." -ForegroundColor Yellow
choco install ffmpeg -y --force

Write-Host "   FFmpeg instalado" -ForegroundColor Green

# 4. Desbloquear todos los archivos de ffmpeg
Write-Host "4. Desbloqueando archivos de ffmpeg..." -ForegroundColor Yellow
Get-ChildItem "C:\ProgramData\chocolatey\lib\ffmpeg" -Recurse -ErrorAction SilentlyContinue | Unblock-File -ErrorAction SilentlyContinue
Get-ChildItem "C:\ProgramData\chocolatey\bin" -Filter "ffmpeg*" -ErrorAction SilentlyContinue | Unblock-File -ErrorAction SilentlyContinue
Write-Host "   Archivos desbloqueados" -ForegroundColor Green

# 5. Probar ffmpeg
Write-Host "5. Probando ffmpeg..." -ForegroundColor Yellow
ffmpeg -version

Write-Host "=== PROCESO COMPLETADO ===" -ForegroundColor Green
