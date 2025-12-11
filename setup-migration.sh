#!/bin/bash

# Script para configurar rápidamente la migración de datos
echo "🔧 Configuración Rápida de Migración - Juntify"
echo "=============================================="

# Verificar si el archivo .env existe
if [ ! -f ".env" ]; then
    echo "❌ Archivo .env no encontrado. Copia .env.example a .env primero."
    exit 1
fi

echo ""
echo "Por favor, proporciona la información de tu base de datos antigua:"
echo ""

# Solicitar información de BD antigua
read -p "🌐 Host de BD antigua (default: 127.0.0.1): " OLD_HOST
OLD_HOST=${OLD_HOST:-127.0.0.1}

read -p "🔌 Puerto de BD antigua (default: 3306): " OLD_PORT
OLD_PORT=${OLD_PORT:-3306}

read -p "🗄️  Nombre de BD antigua: " OLD_DATABASE

read -p "👤 Usuario de BD antigua (default: root): " OLD_USERNAME
OLD_USERNAME=${OLD_USERNAME:-root}

read -s -p "🔒 Password de BD antigua (presiona Enter si no tiene): " OLD_PASSWORD
echo ""

# Verificar si ya existen las variables en .env
if grep -q "OLD_LOCAL_DB_HOST" .env; then
    echo ""
    echo "⚠️  Las variables de migración ya existen en .env"
    read -p "¿Sobreescribir? (y/N): " OVERWRITE
    if [[ ! $OVERWRITE =~ ^[Yy]$ ]]; then
        echo "Configuración cancelada."
        exit 0
    fi
    # Remover variables existentes
    sed -i '/OLD_LOCAL_DB_/d' .env
fi

# Añadir variables al .env
echo "" >> .env
echo "# ========================================" >> .env
echo "# CONFIGURACIÓN PARA MIGRACIÓN DE DATOS" >> .env
echo "# ========================================" >> .env
echo "OLD_LOCAL_DB_HOST=$OLD_HOST" >> .env
echo "OLD_LOCAL_DB_PORT=$OLD_PORT" >> .env
echo "OLD_LOCAL_DB_DATABASE=$OLD_DATABASE" >> .env
echo "OLD_LOCAL_DB_USERNAME=$OLD_USERNAME" >> .env
echo "OLD_LOCAL_DB_PASSWORD=$OLD_PASSWORD" >> .env
echo "OLD_LOCAL_DB_SOCKET=" >> .env

echo ""
echo "✅ Configuración añadida al archivo .env"
echo ""
echo "🧪 Probando conexión a BD antigua..."

# Probar la conexión
php artisan migrate:old-data --dry-run > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "✅ Conexión exitosa!"
    echo ""
    echo "🚀 Comandos disponibles:"
    echo "   php artisan migrate:old-data --dry-run    # Ver qué se migraría"
    echo "   php artisan migrate:old-data              # Migrar todas las tablas"
    echo "   php artisan migrate:users --dry-run       # Ver usuarios a migrar"
    echo "   php artisan verify:migration              # Verificar migración"
    echo ""
    echo "📖 Lee MIGRATION_GUIDE.md para más detalles"
else
    echo "❌ Error de conexión. Verifica los datos ingresados."
    echo "💡 Puedes editar manualmente las variables OLD_LOCAL_DB_* en .env"
fi

echo ""
echo "🎉 Configuración completada!"
