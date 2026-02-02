# 📋 Guía Completa de Instalación - Juntify

## 📑 Índice
1. [Configuración Inicial](#configuración-inicial)
2. [Windows](#-instalación-en-windows)
3. [Linux (Debian/Ubuntu)](#-instalación-en-linux-debianubuntu)
4. [Configuración del Archivo .env](#-configuración-del-archivo-env)
5. [APIs y Servicios Externos](#-configuración-de-apis-y-servicios-externos)
6. [Verificación](#-verificación-de-la-instalación)
7. [Solución de Problemas](#-solución-de-problemas)

---

## 📋 Configuración Inicial

### Requisitos Base
- **PHP**: 8.1 o superior
- **MySQL**: 8.0 o superior
- **Node.js**: 18.x o superior
- **Composer**: 2.x
- **Python**: 3.8 o superior
- **FFmpeg**: Para procesamiento de audio

---

## 🪟 Instalación en Windows

### 1. Preparación del Sistema

#### Instalación de Dependencias Base
```powershell
# Instalar Chocolatey (si no está instalado)
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Instalar dependencias via Chocolatey
choco install php composer nodejs python3 mysql ffmpeg git -y
```

#### Alternativa con Laragon/XAMPP
- **Laragon**: [Descargar](https://laragon.org/download/) - Incluye PHP, MySQL, Composer
- **XAMPP**: [Descargar](https://www.apachefriends.org/download.html) - Configuración adicional requerida

### 2. Configuración del Proyecto

```powershell
# Clonar el repositorio
git clone [URL_DEL_REPOSITORIO] juntify
cd juntify

# Instalar dependencias PHP
composer install

# Instalar dependencias Node.js
npm install

# Copiar archivo de configuración
copy .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

### 3. Configuración Python

```powershell
# Crear entorno virtual
python -m venv python_env

# Activar entorno virtual
.\python_env\Scripts\Activate.ps1

# Instalar dependencias Python
pip install -r requirements.txt

# Desactivar entorno virtual (cuando termines)
deactivate
```

### 4. Base de Datos

```powershell
# Crear bases de datos en MySQL
mysql -u root -p
```

```sql
CREATE DATABASE juntify_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE Juntify_Panels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

```powershell
# Ejecutar migraciones
php artisan migrate

# (Opcional) Ejecutar seeders
php artisan db:seed
```

### 5. Configuración del .env (Windows)

```env
# Rutas Windows específicas
PYTHON_BIN="C:/ruta/completa/al/proyecto/python_env/Scripts/python.exe"
GOOGLE_APPLICATION_CREDENTIALS="C:/ruta/completa/al/proyecto/storage/app/Drive/archivo-credenciales.json"

# Herramientas (si están en PATH)
FFMPEG_BIN=ffmpeg
FFPROBE_BIN=ffprobe
GHOSTSCRIPT_PATH=gswin64c
```

### 6. Inicialización

```powershell
# Compilar assets
npm run build

# Iniciar servidor de desarrollo
php artisan serve

# En otra terminal - Iniciar Vite (desarrollo)
npm run dev
```

---

## 🐧 Instalación en Linux (Debian/Ubuntu)

### 1. Preparación del Sistema

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependencias base
sudo apt install -y software-properties-common ca-certificates lsb-release apt-transport-https

# Agregar repositorio PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP y extensiones
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-gd php8.2-mbstring php8.2-zip php8.2-intl php8.2-bcmath

# Instalar MySQL
sudo apt install -y mysql-server

# Configurar MySQL (opcional)
sudo mysql_secure_installation

# Instalar Node.js (vía NodeSource)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Python y pip
sudo apt install -y python3 python3-pip python3-venv

# Instalar FFmpeg y herramientas
sudo apt install -y ffmpeg tesseract-ocr poppler-utils ghostscript

# Instalar Git
sudo apt install -y git
```

### 2. Configuración del Proyecto

```bash
# Clonar repositorio
git clone [URL_DEL_REPOSITORIO] /var/www/juntify
cd /var/www/juntify

# Configurar permisos
sudo chown -R www-data:www-data /var/www/juntify
sudo chmod -R 755 /var/www/juntify
sudo chmod -R 775 /var/www/juntify/storage
sudo chmod -R 775 /var/www/juntify/bootstrap/cache

# Instalar dependencias PHP
composer install --no-dev --optimize-autoloader

# Instalar dependencias Node.js
npm ci --production

# Copiar configuración
cp .env.example .env

# Generar clave
php artisan key:generate
```

### 3. Configuración Python

```bash
# Crear entorno virtual
python3 -m venv python_env

# Activar entorno virtual
source python_env/bin/activate

# Actualizar pip
pip install --upgrade pip

# Instalar dependencias Python
pip install -r requirements.txt

# Desactivar entorno virtual
deactivate
```

### 4. Base de Datos

```bash
# Conectar a MySQL
sudo mysql -u root -p
```

```sql
-- Crear usuario para la aplicación
CREATE USER 'juntify'@'localhost' IDENTIFIED BY 'password_seguro';

-- Crear bases de datos
CREATE DATABASE juntify_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE Juntify_Panels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Otorgar permisos
GRANT ALL PRIVILEGES ON juntify_new.* TO 'juntify'@'localhost';
GRANT ALL PRIVILEGES ON Juntify_Panels.* TO 'juntify'@'localhost';
FLUSH PRIVILEGES;
exit;
```

```bash
# Ejecutar migraciones
php artisan migrate

# (Opcional) Ejecutar seeders
php artisan db:seed
```

### 5. Configuración del .env (Linux)

```env
# Rutas Linux específicas
PYTHON_BIN="/var/www/juntify/python_env/bin/python"
GOOGLE_APPLICATION_CREDENTIALS="/var/www/juntify/storage/app/Drive/archivo-credenciales.json"

# Herramientas con rutas completas
FFMPEG_BIN=/usr/bin/ffmpeg
FFPROBE_BIN=/usr/bin/ffprobe
TESSERACT_PATH=/usr/bin/tesseract
PDFTOTEXT_PATH=/usr/bin/pdftotext
PDFTOPPM_PATH=/usr/bin/pdftoppm
GHOSTSCRIPT_PATH=/usr/bin/gs

# Base de datos
DB_HOST=127.0.0.1
DB_DATABASE=juntify_new
DB_USERNAME=juntify
DB_PASSWORD=password_seguro
```

### 6. Configuración de Nginx (Producción)

```bash
# Crear configuración de Nginx
sudo nano /etc/nginx/sites-available/juntify
```

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/juntify/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
# Habilitar sitio
sudo ln -s /etc/nginx/sites-available/juntify /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. Servicios del Sistema (Opcional)

```bash
# Crear servicio para Queue Worker
sudo nano /etc/systemd/system/juntify-worker.service
```

```ini
[Unit]
Description=Juntify Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/juntify
ExecStart=/usr/bin/php /var/www/juntify/artisan queue:work --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
# Habilitar servicio
sudo systemctl enable juntify-worker
sudo systemctl start juntify-worker
```

---

## 🔧 Configuración del Archivo .env

### Pasos Comunes (Windows/Linux)

1. **Copiar el archivo de ejemplo**:
   ```bash
   cp .env.example .env
   ```

2. **Configurar variables básicas**:
   ```env
   APP_NAME=Juntify
   APP_ENV=production  # o 'local' para desarrollo
   APP_KEY=            # Se genera con: php artisan key:generate
   APP_DEBUG=false     # true solo en desarrollo
   APP_URL=https://tu-dominio.com
   ```

3. **Base de datos**:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=juntify_new
   DB_USERNAME=juntify
   DB_PASSWORD=tu_password_seguro
   ```

4. **Rutas específicas del sistema**:

   **Windows**:
   ```env
   PYTHON_BIN="C:/ruta/completa/python_env/Scripts/python.exe"
   GOOGLE_APPLICATION_CREDENTIALS="C:/ruta/completa/storage/app/Drive/credentials.json"
   ```

   **Linux**:
   ```env
   PYTHON_BIN="/var/www/juntify/python_env/bin/python"
   GOOGLE_APPLICATION_CREDENTIALS="/var/www/juntify/storage/app/Drive/credentials.json"
   ```

---

## 🔑 Configuración de APIs y Servicios Externos

### 1. Google Cloud Console

1. **Crear proyecto** en [Google Cloud Console](https://console.cloud.google.com)
2. **Habilitar APIs**:
   - Google Drive API
   - Google Calendar API
   - Gmail API (opcional)

3. **Crear credenciales OAuth2**:
   ```env
   GOOGLE_OAUTH_CLIENT_ID=tu-client-id
   GOOGLE_OAUTH_CLIENT_SECRET=tu-client-secret
   GOOGLE_OAUTH_REDIRECT_URI=https://tu-dominio.com/auth/google/callback
   ```

4. **Crear Service Account**:
   - Descargar archivo JSON de credenciales
   - Colocar en `storage/app/Drive/`
   ```env
   GOOGLE_SERVICE_ACCOUNT_EMAIL=tu-service@proyecto.iam.gserviceaccount.com
   GOOGLE_APPLICATION_CREDENTIALS=/ruta/completa/al/archivo.json
   ```

### 2. OpenAI API
```env
OPENAI_API_KEY=sk-...tu-api-key
```

### 3. AssemblyAI
```env
ASSEMBLYAI_API_KEY=tu-api-key-assemblyai
```

### 4. Mercado Pago
```env
MERCADO_PAGO_ACCESS_TOKEN=tu-access-token
MERCADO_PAGO_PUBLIC_KEY=tu-public-key
```

### 5. Correo SMTP
```env
MAIL_MAILER=smtp
MAIL_HOST=tu-smtp-server.com
MAIL_PORT=465
MAIL_USERNAME=tu-email@dominio.com
MAIL_PASSWORD=tu-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="tu-email@dominio.com"
MAIL_FROM_NAME="Juntify"
```

---

## ✅ Verificación de la Instalación

### Comandos de Diagnóstico

```bash
# Verificar configuración de Google
php artisan google:check

# Verificar tokens de Google
php artisan google:tokens

# Verificar estado general
php artisan about

# Verificar permisos (Linux)
ls -la storage/
ls -la bootstrap/cache/
```

### Pruebas Funcionales

1. **Servidor web**: Acceder a `http://tu-dominio.com` o `http://127.0.0.1:8000`
2. **Base de datos**: Registrar usuario, iniciar sesión
3. **Python**: Probar procesamiento de audio
4. **Google Drive**: Conectar cuenta desde perfil de usuario

---

## 🔧 Solución de Problemas

### Problemas Comunes Windows

**Error: "Class 'ZipArchive' not found"**
```powershell
# Habilitar extensión zip en php.ini
extension=zip
```

**Error de permisos en storage/**
```powershell
# Dar permisos completos a las carpetas
icacls storage /grant Everyone:(OI)(CI)F /T
icacls bootstrap\cache /grant Everyone:(OI)(CI)F /T
```

**FFmpeg no encontrado**
```powershell
# Instalar FFmpeg vía Chocolatey
choco install ffmpeg
# O descargar desde https://ffmpeg.org/download.html
```

### Problemas Comunes Linux

**Error de permisos**
```bash
sudo chown -R www-data:www-data /var/www/juntify
sudo chmod -R 775 storage bootstrap/cache
```

**MySQL connection refused**
```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

**Python module not found**
```bash
# Activar entorno virtual primero
source python_env/bin/activate
pip install -r requirements.txt
```

**Nginx 502 Bad Gateway**
```bash
sudo systemctl status php8.2-fpm
sudo systemctl start php8.2-fpm
sudo systemctl reload nginx
```

### Logs de Depuración

```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# Ver logs de Nginx (Linux)
sudo tail -f /var/log/nginx/error.log

# Ver logs de PHP-FPM (Linux)  
sudo tail -f /var/log/php8.2-fpm.log
```

### Comandos de Limpieza

```bash
# Limpiar cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload --optimize
```

---

## 📞 Soporte

Para problemas específicos:

1. **Verificar logs**: `storage/logs/laravel.log`
2. **Ejecutar diagnósticos**: `php artisan google:check`
3. **Revisar configuración**: `php artisan config:show`
4. **Verificar permisos**: Especialmente en `storage/` y `bootstrap/cache/`

---

*Documentación actualizada - Febrero 2026*