#!/bin/bash
# =====================================================
# SCRIPT PARA CONFIGURAR WORKERS DE COLA EN PRODUCCIÓN
# =====================================================
# Ejecutar en el servidor de producción como root

echo "🚀 Configurando workers de Laravel Queue para Juntify..."

# Crear el archivo de configuración del supervisor
cat > /etc/supervisor/conf.d/juntify-worker.conf << 'EOF'
[program:juntify-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/Juntify/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/Juntify/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
EOF

# Crear archivo de configuración adicional para archivos grandes
cat > /etc/supervisor/conf.d/juntify-heavy-worker.conf << 'EOF'
[program:juntify-heavy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/Juntify/artisan queue:work database --queue=heavy --sleep=1 --tries=2 --max-time=7200 --timeout=1200 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/Juntify/storage/logs/heavy-worker.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=3
stopwaitsecs=7200
EOF

echo "📁 Archivos de configuración creados en /etc/supervisor/conf.d/"

# Recargar supervisor
echo "🔄 Recargando configuración de supervisor..."
supervisorctl reread
supervisorctl update

# Iniciar workers
echo "▶️ Iniciando workers..."
supervisorctl start juntify-worker:*
supervisorctl start juntify-heavy-worker:*

# Mostrar estado
echo "📊 Estado actual de los workers:"
supervisorctl status juntify-worker:*
supervisorctl status juntify-heavy-worker:*

echo "✅ Configuración completada!"
echo "📋 Comandos útiles:"
echo "   - Ver logs: tail -f /var/www/Juntify/storage/logs/worker.log"
echo "   - Estado workers: supervisorctl status"
echo "   - Reiniciar workers: supervisorctl restart juntify-worker:* juntify-heavy-worker:*"
echo "   - Ver cola: php artisan queue:monitor"