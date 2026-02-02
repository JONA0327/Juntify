@echo off
REM Script de instalación rápida para Windows
REM Ejecutar como Administrador: install-windows.bat

echo 🚀 Instalando Juntify en Windows...

REM Verificar si Chocolatey está instalado
choco -v >nul 2>&1
if errorlevel 1 (
    echo ❌ Chocolatey no está instalado
    echo 📋 Instala Chocolatey primero: https://chocolatey.org/install
    echo 💡 O usa Laragon/XAMPP como alternativa
    pause
    exit /b 1
)

echo ✅ Chocolatey detectado

REM Instalar dependencias base
echo 📦 Instalando dependencias base...
choco install php composer nodejs python3 mysql ffmpeg git -y

REM Verificar si estamos en el directorio del proyecto
if not exist "artisan" (
    echo ❌ No se detectó proyecto Laravel
    echo 📁 Asegúrate de ejecutar desde el directorio del proyecto
    pause
    exit /b 1
)

echo 📁 Proyecto Laravel detectado

REM Instalar dependencias PHP
if exist "composer.json" (
    echo 📦 Instalando dependencias PHP...
    composer install
)

REM Instalar dependencias Node.js
if exist "package.json" (
    echo 📦 Instalando dependencias Node.js...
    npm install
)

REM Configurar archivo .env
if not exist ".env" (
    if exist ".env.example" (
        echo ⚙️ Configurando archivo .env...
        copy .env.example .env
        php artisan key:generate
    )
)

REM Configurar Python
if exist "requirements.txt" (
    echo 🐍 Configurando entorno Python...
    python -m venv python_env
    call python_env\Scripts\activate.bat
    pip install --upgrade pip
    pip install -r requirements.txt
    call python_env\Scripts\deactivate.bat
    echo ✅ Entorno Python configurado
)

echo.
echo 🎉 Instalación completada!
echo.
echo 📋 Próximos pasos:
echo 1. Configurar MySQL y crear bases de datos:
echo    - juntify_new
echo    - Juntify_Panels
echo.
echo 2. Configurar archivo .env con credenciales
echo.
echo 3. Ejecutar migraciones:
echo    php artisan migrate
echo.
echo 4. Verificar configuración:
echo    php artisan google:check
echo.
echo 5. Iniciar servidor:
echo    php artisan serve
echo.
echo ⚠️ Consulta INSTALACION.md para configuración detallada
echo.
pause