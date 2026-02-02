#!/bin/bash
# Script de instalación rápida para Linux (Debian/Ubuntu)
# Ejecutar con: chmod +x install-linux.sh && ./install-linux.sh

set -e

echo "🚀 Instalando Juntify en Linux..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar si es root
if [[ $EUID -eq 0 ]]; then
   print_error "No ejecutes este script como root. Usa un usuario normal."
   exit 1
fi

# Detectar distribución
if [[ -f /etc/debian_version ]]; then
    DISTRO="debian"
    print_status "Distribución detectada: Debian/Ubuntu"
else
    print_warning "Distribución no verificada. Continuando con comandos de Debian/Ubuntu..."
    DISTRO="unknown"
fi

# Actualizar sistema
print_status "Actualizando paquetes del sistema..."
sudo apt update && sudo apt upgrade -y

# Instalar dependencias base
print_status "Instalando dependencias base..."
sudo apt install -y software-properties-common ca-certificates lsb-release apt-transport-https curl wget unzip git

# Agregar repositorio PHP
print_status "Agregando repositorio PHP..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP y extensiones
print_status "Instalando PHP 8.2 y extensiones..."
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-curl \
    php8.2-gd php8.2-mbstring php8.2-zip php8.2-intl php8.2-bcmath php8.2-json \
    php8.2-tokenizer php8.2-ctype php8.2-fileinfo

# Instalar MySQL
print_status "Instalando MySQL Server..."
sudo apt install -y mysql-server

# Instalar Node.js
print_status "Instalando Node.js 18..."
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Instalar Composer
print_status "Instalando Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Python y herramientas
print_status "Instalando Python y herramientas adicionales..."
sudo apt install -y python3 python3-pip python3-venv python3-dev

# Instalar herramientas multimedia
print_status "Instalando FFmpeg y herramientas multimedia..."
sudo apt install -y ffmpeg tesseract-ocr poppler-utils ghostscript

# Instalar Nginx (opcional)
read -p "¿Instalar Nginx? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_status "Instalando Nginx..."
    sudo apt install -y nginx
    sudo systemctl enable nginx
    print_success "Nginx instalado y habilitado"
fi

# Configurar directorio del proyecto
PROJECT_DIR="/var/www/juntify"
print_status "Configurando directorio del proyecto en $PROJECT_DIR"

# Crear directorio si no existe
if [ ! -d "$PROJECT_DIR" ]; then
    sudo mkdir -p "$PROJECT_DIR"
    sudo chown -R $USER:$USER "$PROJECT_DIR"
fi

# Si estamos en el directorio del proyecto, usar el directorio actual
if [ -f "artisan" ]; then
    PROJECT_DIR=$(pwd)
    print_status "Usando directorio actual: $PROJECT_DIR"
else
    print_warning "No se detectó proyecto Laravel. Clona el repositorio en $PROJECT_DIR"
    print_status "Comando: git clone [URL_REPO] $PROJECT_DIR"
fi

cd "$PROJECT_DIR" || exit 1

# Instalar dependencias si composer.json existe
if [ -f "composer.json" ]; then
    print_status "Instalando dependencias PHP..."
    composer install --no-dev --optimize-autoloader
    
    if [ -f "package.json" ]; then
        print_status "Instalando dependencias Node.js..."
        npm ci --production
    fi
    
    # Configurar archivo .env
    if [ ! -f ".env" ] && [ -f ".env.example" ]; then
        print_status "Configurando archivo .env..."
        cp .env.example .env
        php artisan key:generate
        print_warning "Recuerda configurar las variables en .env antes de continuar"
    fi
    
    # Configurar Python
    if [ -f "requirements.txt" ]; then
        print_status "Configurando entorno Python..."
        python3 -m venv python_env
        source python_env/bin/activate
        pip install --upgrade pip
        pip install -r requirements.txt
        deactivate
        print_success "Entorno Python configurado"
    fi
    
    # Configurar permisos
    print_status "Configurando permisos..."
    sudo chown -R www-data:www-data "$PROJECT_DIR"
    sudo chmod -R 755 "$PROJECT_DIR"
    sudo chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
    
    print_success "Instalación base completada"
else
    print_warning "No se encontró composer.json. Asegúrate de estar en el directorio del proyecto."
fi

# Mostrar siguiente pasos
echo
print_success "🎉 Instalación completada!"
echo
echo -e "${YELLOW}Próximos pasos:${NC}"
echo "1. Configurar base de datos MySQL:"
echo "   sudo mysql -u root -p"
echo "   CREATE DATABASE juntify_new;"
echo "   CREATE USER 'juntify'@'localhost' IDENTIFIED BY 'password';"
echo "   GRANT ALL ON juntify_new.* TO 'juntify'@'localhost';"
echo
echo "2. Configurar archivo .env con credenciales de BD y APIs"
echo
echo "3. Ejecutar migraciones:"
echo "   php artisan migrate"
echo
echo "4. Compilar assets:"
echo "   npm run build"
echo
echo "5. Verificar configuración:"
echo "   php artisan google:check"
echo
echo "6. Iniciar servidor de desarrollo:"
echo "   php artisan serve"
echo
print_warning "Consulta INSTALACION.md para configuración detallada"