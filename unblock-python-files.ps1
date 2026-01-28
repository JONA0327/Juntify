# Script para desbloquear archivos de Python bloqueados por Windows

Write-Host "Desbloqueando archivos de Python en el entorno virtual..." -ForegroundColor Cyan

# Obtener la ruta del entorno virtual
$venvPath = ".\.venv"

if (-not (Test-Path $venvPath)) {
    Write-Host "No se encontro el entorno virtual en $venvPath" -ForegroundColor Red
    exit 1
}

Write-Host "Buscando archivos bloqueados..." -ForegroundColor Yellow

# Desbloquear todos los archivos .pyd, .dll y .exe en el entorno virtual
$count = 0
Get-ChildItem -Path $venvPath -Recurse -Include *.pyd,*.dll,*.exe -ErrorAction SilentlyContinue | ForEach-Object {
    try {
        Unblock-File -Path $_.FullName -ErrorAction Stop
        $count++
        Write-Host "Desbloqueado: $($_.Name)" -ForegroundColor Green
    } catch {
        # Silenciosamente ignorar archivos que no estan bloqueados
    }
}

if ($count -eq 0) {
    Write-Host "No se encontraron archivos bloqueados" -ForegroundColor Green
} else {
    Write-Host "Se desbloquearon $count archivos" -ForegroundColor Green
}

Write-Host "Ahora intenta ejecutar el proceso de audio nuevamente" -ForegroundColor Cyan
