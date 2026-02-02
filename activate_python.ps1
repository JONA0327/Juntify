# Activar entorno virtual de Python para Juntify
Write-Host "Activando entorno virtual de Python para Juntify..." -ForegroundColor Green

# Activar el entorno virtual
& ".\python_env\Scripts\Activate.ps1"

Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "   ENTORNO PYTHON PARA JUNTIFY" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Entorno virtual activado!" -ForegroundColor Green
Write-Host ""
Write-Host "Comandos disponibles:" -ForegroundColor Yellow
Write-Host "  python test_identification.py      - Test de identificacion de speakers" -ForegroundColor White
Write-Host "  python test_self_similarity.py     - Test de similaridad" -ForegroundColor White
Write-Host "  python tools\identify_speakers.py  - Identificar speakers en audio" -ForegroundColor White
Write-Host "  python tools\enroll_voice.py       - Enrollar nueva voz" -ForegroundColor White
Write-Host "  python tools\convert_to_ogg.py     - Convertir audio a OGG" -ForegroundColor White
Write-Host ""
Write-Host "Para desactivar el entorno, usa: deactivate" -ForegroundColor Yellow
Write-Host ""