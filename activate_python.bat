@echo off
echo Activando entorno virtual de Python para Juntify...
call python_env\Scripts\activate.bat
echo.
echo ====================================
echo   ENTORNO PYTHON PARA JUNTIFY
echo ====================================
echo.
echo Entorno virtual activado!
echo.
echo Comandos disponibles:
echo   python test_identification.py      - Test de identificacion de speakers
echo   python test_self_similarity.py     - Test de similaridad
echo   python tools\identify_speakers.py  - Identificar speakers en audio
echo   python tools\enroll_voice.py       - Enrollar nueva voz
echo   python tools\convert_to_ogg.py     - Convertir audio a OGG
echo.
echo Para desactivar el entorno, usa: deactivate
echo.
cmd /k